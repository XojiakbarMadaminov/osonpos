# QZ Tray: bepul silent printing

Ushbu yo‘l QZ Tray’ning ochiq kodli distributivi va OsonPOS’ning o‘z root
sertifikatidan foydalanadi. Bitta server signing kaliti barcha organizationlarga
xizmat qiladi. Har bir organization yoki filial uchun alohida sertifikat
yaratilmaydi.

## Xavfsizlik modeli

- `private-key.pem` faqat Laravel production serverda saqlanadi.
- `root-ca-private-key.pem` sertifikat yangilash uchun kerak va offline,
  shifrlangan backupda saqlanishi lozim.
- Kassalarga tarqatiladigan installer faqat public sertifikatlarni saqlaydi.
- Installer administrator huquqi bilan QZ Tray’ga OsonPOS root sertifikatini
  trust qiladi va signing sertifikatini system-wide whitelist’ga qo‘shadi.
- Installer faqat organization egasi ruxsat bergan POS kompyuterida ishlatiladi.

OsonPOS private key sizib chiqsa, tajovuzkor OsonPOS nomidan QZ buyruqlarini
imzolashi mumkin. Kalitlarni Git, public web katalogi, Telegram yoki installer
ichiga joylash qat’iyan mumkin emas.

## 1. Production sertifikati va yagona installer yaratish

Ubuntu production serverda loyiha katalogidan:

```bash
bash deploy/qz/prepare-free-signing.sh \
  --app-dir /var/www/osonpos \
  --pos-url https://pos.example.uz/pos/device-setup \
  --company OsonPOS
```

Skript quyidagilarni yaratadi:

```text
storage/app/private/qz/
├── digital-certificate.txt
├── private-key.pem
├── override.crt
├── root-ca-private-key.pem
├── signing-certificate.crt
└── OsonPOS-QZ-Setup.ps1
```

`--force` parametrini oddiy deployda ishlatmang. U butun certificate authority’ni
almashtiradi va avval o‘rnatilgan barcha kassalarni qayta sozlashni talab qiladi.

## 2. Production env

```dotenv
QZ_SIGNING_ENABLED=true
QZ_CERTIFICATE_PATH=/var/www/osonpos/storage/app/private/qz/digital-certificate.txt
QZ_PRIVATE_KEY_PATH=/var/www/osonpos/storage/app/private/qz/private-key.pem
QZ_PRIVATE_KEY_PASSPHRASE=
VITE_QZ_SIGNED_PRINTING=true
```

`VITE_*` build vaqtida olinadi. `.env` sozlangandan keyin:

```bash
corepack enable
pnpm install --frozen-lockfile
pnpm build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 3. Organizationga beriladigan fayl

Faqat mana bu fayl tarqatiladi:

```text
storage/app/private/qz/OsonPOS-QZ-Setup.ps1
```

`private-key.pem` va `root-ca-private-key.pem` tarqatilmaydi.

POS kompyuterida:

1. Termal printerning ishlab chiqaruvchi drayveri o‘rnatiladi.
2. Windows test page muvaffaqiyatli chiqariladi.
3. `OsonPOS-QZ-Setup.ps1` ustida o‘ng tugma bosilib, PowerShell orqali ishga
   tushiriladi.
4. Windows UAC so‘roviga ruxsat beriladi.
5. Installer rasmiy QZ Tray 2.2.6 distributivini yuklaydi, SHA-256 va Windows
   raqamli imzosini tekshiradi, silent o‘rnatadi, trust va whitelist’ni sozlaydi.
6. Installer `/pos/device-setup` sahifasini ochadi.
7. User login qiladi va admin yaratgan olti xonali device kodini kiritadi.
8. `printers.manage` huquqiga ega user fizik printerlarni mantiqiy printerlarga
   bog‘laydi va test chop etadi.

PowerShell script ishga tushishi Windows policy sabab bloklansa, Administrator
PowerShell’dan faqat shu ishga tushirish uchun:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\OsonPOS-QZ-Setup.ps1
```

Installer logi:

```text
C:\ProgramData\OsonPOS\qz-install.log
```

## 4. Nima avtomatlashtirilmaydi

Har xil termal printer modeli turli drayver ishlatgani sabab universal installer
printer drayverini xavfsiz tanlay olmaydi. Drayver oldindan o‘rnatilishi kerak.

Device aktivatsiya kodi va fizik printer mapping installer ichiga yozilmaydi.
Ular organization/store access va `printers.manage` tekshiruvlarini saqlash uchun
POS ichida bajariladi.

## 5. Har bir yangi organization uchun qisqa tartib

1. Admin panelda device va olti xonali aktivatsiya kodi yaratiladi.
2. Mantiqiy kassa/oshxona printerlari va print route’lar yaratiladi.
3. Printer drayveri o‘rnatiladi.
4. Yagona `OsonPOS-QZ-Setup.ps1` ishlatiladi.
5. `/pos/device-setup` orqali device aktivatsiya qilinadi.
6. Fizik printer mapping va test print bajariladi.

Sertifikatlar barcha organizationlar uchun umumiy, device credential va printer
mapping esa organization, store va device bo‘yicha alohida qoladi.
