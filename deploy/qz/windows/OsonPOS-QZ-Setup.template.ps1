param()

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$setupUrl = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String('__POS_SETUP_URL_BASE64__'))
$rootCertificate = [Text.Encoding]::ASCII.GetString([Convert]::FromBase64String('__ROOT_CERT_BASE64__'))
$signingCertificate = [Text.Encoding]::ASCII.GetString([Convert]::FromBase64String('__SIGNING_CERT_BASE64__'))
$qzVersion = '2.2.6'

function Test-IsAdministrator {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = [Security.Principal.WindowsPrincipal]::new($identity)

    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Start-Elevated {
    $arguments = @(
        '-NoProfile',
        '-ExecutionPolicy', 'Bypass',
        '-File', ('"{0}"' -f $PSCommandPath)
    )

    Start-Process -FilePath 'powershell.exe' -Verb RunAs -ArgumentList $arguments | Out-Null
}

function Write-Step([string] $message) {
    Write-Host "`n[$([DateTime]::Now.ToString('HH:mm:ss'))] $message" -ForegroundColor Cyan
}

function Stop-QzTray {
    Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
        Where-Object {
            $_.Name -in @('qz-tray.exe', 'qz-tray-console.exe', 'java.exe', 'javaw.exe') -and
            $_.CommandLine -like '*QZ Tray*'
        } |
        ForEach-Object {
            Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
        }
}

if (-not (Test-IsAdministrator)) {
    Write-Host "OsonPOS printer sozlamasi administrator huquqini so'raydi..." -ForegroundColor Yellow
    Start-Elevated
    exit 0
}

$workDirectory = Join-Path $env:TEMP ("osonpos-qz-setup-{0}" -f [Guid]::NewGuid().ToString('N'))
$logDirectory = Join-Path $env:ProgramData 'OsonPOS'
$logPath = Join-Path $logDirectory 'qz-install.log'
$qzDirectory = Join-Path $env:ProgramFiles 'QZ Tray'
$qzConsole = Join-Path $qzDirectory 'qz-tray-console.exe'
$qzGui = Join-Path $qzDirectory 'qz-tray.exe'

New-Item -ItemType Directory -Path $workDirectory -Force | Out-Null
New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null
Start-Transcript -Path $logPath -Append | Out-Null

try {
    $isArm64 = $env:PROCESSOR_ARCHITECTURE -eq 'ARM64' -or $env:PROCESSOR_IDENTIFIER -like '*ARM*'
    $architecture = if ($isArm64) { 'arm64' } else { 'x86_64' }
    $expectedInstallerHash = if ($isArm64) {
        'EC08EEE87768753651C9F3E1EF0E83A297A0B9E8950793875D686931228A7069'
    } else {
        'AEB93A601C27F5FA6BB464F63471E7ACD43052BA384FEF49DCEEC8290D4F7587'
    }
    $installerUrl = "https://github.com/qzind/tray/releases/download/v$qzVersion/qz-tray-$qzVersion-$architecture.exe"
    $installerPath = Join-Path $workDirectory 'qz-tray-installer.exe'
    $rootPath = Join-Path $workDirectory 'override.crt'
    $signingPath = Join-Path $workDirectory 'digital-certificate.txt'

    Write-Step "QZ Tray $qzVersion yuklab olinmoqda"
    Invoke-WebRequest -Uri $installerUrl -OutFile $installerPath -UseBasicParsing

    $actualInstallerHash = (Get-FileHash -Path $installerPath -Algorithm SHA256).Hash
    if ($actualInstallerHash -ne $expectedInstallerHash) {
        throw "QZ Tray installer nazorat summasi mos kelmadi. O'rnatish xavfsizlik sabab to'xtatildi."
    }

    $authenticode = Get-AuthenticodeSignature -FilePath $installerPath
    if ($authenticode.Status -ne [Management.Automation.SignatureStatus]::Valid -or
        $authenticode.SignerCertificate.Subject -notmatch 'QZ Industries') {
        throw 'QZ Tray installer Windows raqamli imzosi haqiqiy emas.'
    }

    [IO.File]::WriteAllText($rootPath, $rootCertificate, [Text.Encoding]::ASCII)
    [IO.File]::WriteAllText($signingPath, $signingCertificate, [Text.Encoding]::ASCII)

    Write-Step "QZ Tray silent rejimda o'rnatilmoqda"
    $installProcess = Start-Process -FilePath $installerPath -ArgumentList '/S' -Wait -PassThru
    if ($installProcess.ExitCode -ne 0) {
        throw "QZ Tray o'rnatilmadi. Exit code: $($installProcess.ExitCode)"
    }

    if (-not (Test-Path -LiteralPath $qzConsole)) {
        throw "QZ Tray konsoli topilmadi: $qzConsole"
    }

    Write-Step "OsonPOS ishonch sertifikati o'rnatilmoqda"
    Stop-QzTray
    Copy-Item -LiteralPath $rootPath -Destination (Join-Path $qzDirectory 'override.crt') -Force

    $propertiesPath = Join-Path $qzDirectory 'qz-tray.properties'
    $properties = if (Test-Path -LiteralPath $propertiesPath) {
        Get-Content -LiteralPath $propertiesPath -Raw
    } else {
        ''
    }

    if ($properties -match '(?m)^authcert\.override=') {
        $properties = $properties -replace '(?m)^authcert\.override=.*$', 'authcert.override=override.crt'
    } else {
        $properties = "$properties`r`nauthcert.override=override.crt`r`n"
    }
    [IO.File]::WriteAllText($propertiesPath, $properties, [Text.UTF8Encoding]::new($false))

    Write-Step "OsonPOS sertifikati barcha Windows foydalanuvchilari uchun ruxsat etilmoqda"
    $whitelistOutput = & $qzConsole --whitelist $signingPath 2>&1 | Out-String
    Add-Content -LiteralPath $logPath -Value $whitelistOutput
    if ($LASTEXITCODE -ne 0) {
        throw "QZ Tray whitelist sozlamasi bajarilmadi. Exit code: $LASTEXITCODE"
    }

    $userAllowedPath = Join-Path $env:APPDATA 'qz\allowed.dat'
    $systemQzDirectory = Join-Path $env:ProgramData 'qz'
    $systemAllowedPath = Join-Path $systemQzDirectory 'allowed.dat'
    if (-not (Test-Path -LiteralPath $userAllowedPath)) {
        throw 'QZ Tray allowed.dat faylini yaratmadi.'
    }

    New-Item -ItemType Directory -Path $systemQzDirectory -Force | Out-Null
    Copy-Item -LiteralPath $userAllowedPath -Destination $systemAllowedPath -Force

    Write-Step 'QZ Tray ishga tushirilmoqda'
    if (Test-Path -LiteralPath $qzGui) {
        Start-Process -FilePath $qzGui | Out-Null
    } else {
        Start-Process -FilePath $qzConsole -WindowStyle Hidden | Out-Null
    }

    Write-Host "`nQZ Tray muvaffaqiyatli o'rnatildi va OsonPOS uchun sozlandi." -ForegroundColor Green
    Write-Host "Endi printer drayveri Windows'da o'rnatilganini tekshiring va POS ichida fizik printerni tanlang."
    Write-Host "Log: $logPath"

    Start-Process $setupUrl | Out-Null
    Read-Host 'Yakunlash uchun Enter tugmasini bosing'
} catch {
    Write-Host "`nXatolik: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "Log: $logPath"
    Read-Host 'Yopish uchun Enter tugmasini bosing'
    exit 1
} finally {
    Stop-Transcript -ErrorAction SilentlyContinue | Out-Null
    if (Test-Path -LiteralPath $workDirectory) {
        Remove-Item -LiteralPath $workDirectory -Recurse -Force -ErrorAction SilentlyContinue
    }
}
