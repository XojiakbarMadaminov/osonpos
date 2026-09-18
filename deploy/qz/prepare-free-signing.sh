#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
TEMPLATE_PATH="${SCRIPT_DIR}/windows/OsonPOS-QZ-Setup.template.ps1"

APP_DIR=""
POS_SETUP_URL=""
COMPANY_NAME="OsonPOS"
FORCE=0
REBUILD_INSTALLER=0

usage() {
    cat <<'EOF'
Foydalanish:
  bash deploy/qz/prepare-free-signing.sh \
    --app-dir /var/www/osonpos \
    --pos-url https://pos.example.uz/pos/device-setup \
    [--company OsonPOS] [--rebuild-installer | --force]

Natija:
  storage/app/private/qz/digital-certificate.txt
  storage/app/private/qz/private-key.pem
  storage/app/private/qz/override.crt
  storage/app/private/qz/OsonPOS-QZ-Setup.ps1

--force mavjud sertifikatlarni almashtiradi. Bunda oldingi installer o‘rnatilgan
qurilmalar yangi installer bilan qayta sozlanmaguncha silent print ishlamaydi.

--rebuild-installer mavjud sertifikatlarni o‘zgartirmasdan faqat Windows
installerini yangidan yig‘adi.
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --app-dir)
            APP_DIR="${2:-}"
            shift 2
            ;;
        --pos-url)
            POS_SETUP_URL="${2:-}"
            shift 2
            ;;
        --company)
            COMPANY_NAME="${2:-}"
            shift 2
            ;;
        --force)
            FORCE=1
            shift
            ;;
        --rebuild-installer)
            REBUILD_INSTALLER=1
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Noma'lum parametr: $1" >&2
            usage >&2
            exit 2
            ;;
    esac
done

if [[ -z "$APP_DIR" || -z "$POS_SETUP_URL" ]]; then
    usage >&2
    exit 2
fi

if [[ "$FORCE" -eq 1 && "$REBUILD_INSTALLER" -eq 1 ]]; then
    echo "--force va --rebuild-installer bir vaqtda ishlatilmaydi." >&2
    exit 2
fi

if [[ ! "$POS_SETUP_URL" =~ ^https:// ]]; then
    echo "Production POS manzili HTTPS bilan boshlanishi kerak." >&2
    exit 2
fi

for command in openssl base64 sed mktemp; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Kerakli buyruq topilmadi: $command" >&2
        exit 1
    fi
done

if [[ ! -f "$TEMPLATE_PATH" ]]; then
    echo "Installer shabloni topilmadi: $TEMPLATE_PATH" >&2
    exit 1
fi

APP_DIR="$(cd -- "$APP_DIR" && pwd)"
OUTPUT_DIR="${APP_DIR}/storage/app/private/qz"
ROOT_KEY="${OUTPUT_DIR}/root-ca-private-key.pem"
ROOT_CERT="${OUTPUT_DIR}/override.crt"
SIGNING_KEY="${OUTPUT_DIR}/private-key.pem"
SIGNING_CERT_ONLY="${OUTPUT_DIR}/signing-certificate.crt"
DIGITAL_CERT="${OUTPUT_DIR}/digital-certificate.txt"
INSTALLER_OUTPUT="${OUTPUT_DIR}/OsonPOS-QZ-Setup.ps1"

if [[ -d "$OUTPUT_DIR" && "$FORCE" -ne 1 && "$REBUILD_INSTALLER" -ne 1 ]] &&
   [[ -e "$ROOT_KEY" || -e "$ROOT_CERT" || -e "$SIGNING_KEY" || -e "$DIGITAL_CERT" ]]; then
    echo "QZ sertifikatlari allaqachon mavjud. Ularni tasodifan almashtirmaslik uchun jarayon to'xtatildi." >&2
    echo "Ataylab yangilamoqchi bo'lsangiz --force parametridan foydalaning." >&2
    exit 1
fi


if [[ "$REBUILD_INSTALLER" -eq 1 ]]; then
    for required_file in "$ROOT_CERT" "$SIGNING_KEY" "$SIGNING_CERT_ONLY" "$DIGITAL_CERT"; do
        if [[ ! -f "$required_file" ]]; then
            echo "Installer qayta yig‘ilmadi, fayl topilmadi: $required_file" >&2
            exit 1
        fi
    done
fi

mkdir -p "$OUTPUT_DIR"
chmod 700 "$OUTPUT_DIR"

WORK_DIR="$(mktemp -d)"
cleanup() {
    rm -rf -- "$WORK_DIR"
}
trap cleanup EXIT

safe_company="$(printf '%s' "$COMPANY_NAME" | tr -cd '[:alnum:] ._-')"
if [[ -z "$safe_company" ]]; then
    echo "Company nomida yaroqli belgi qolmadi." >&2
    exit 2
fi

cat >"${WORK_DIR}/root.cnf" <<EOF
[req]
distinguished_name = dn
prompt = no
x509_extensions = v3_ca

[dn]
C = UZ
O = ${safe_company}
OU = Printer Security
CN = ${safe_company} QZ Root CA

[v3_ca]
basicConstraints = critical,CA:TRUE,pathlen:1
keyUsage = critical,keyCertSign,cRLSign
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid:always,issuer
EOF

cat >"${WORK_DIR}/signing.cnf" <<EOF
[req]
distinguished_name = dn
prompt = no

[dn]
C = UZ
O = ${safe_company}
OU = POS Printing
CN = ${safe_company}
EOF

cat >"${WORK_DIR}/signing.ext" <<'EOF'
basicConstraints = critical,CA:FALSE
keyUsage = critical,digitalSignature
extendedKeyUsage = codeSigning
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid,issuer
EOF

if [[ "$REBUILD_INSTALLER" -ne 1 ]]; then
    echo "QZ root sertifikati yaratilmoqda..."
    openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:4096 -out "$ROOT_KEY"
    openssl req -x509 -new -sha256 -days 3650 \
        -key "$ROOT_KEY" \
        -out "$ROOT_CERT" \
        -config "${WORK_DIR}/root.cnf"

    echo "Server-side signing sertifikati yaratilmoqda..."
    openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out "$SIGNING_KEY"
    openssl req -new -sha256 \
        -key "$SIGNING_KEY" \
        -out "${WORK_DIR}/signing.csr" \
        -config "${WORK_DIR}/signing.cnf"
    openssl x509 -req -sha256 -days 1825 \
        -in "${WORK_DIR}/signing.csr" \
        -CA "$ROOT_CERT" \
        -CAkey "$ROOT_KEY" \
        -CAcreateserial \
        -out "$SIGNING_CERT_ONLY" \
        -extfile "${WORK_DIR}/signing.ext"

    cat "$SIGNING_CERT_ONLY" "$ROOT_CERT" >"$DIGITAL_CERT"
    chmod 600 "$ROOT_KEY" "$SIGNING_KEY"
    chmod 644 "$ROOT_CERT" "$SIGNING_CERT_ONLY" "$DIGITAL_CERT"
else
    echo "Mavjud sertifikatlardan Windows installer qayta yig‘ilmoqda..."
fi

root_b64="$(base64 -w 0 "$ROOT_CERT")"
signing_b64="$(base64 -w 0 "$DIGITAL_CERT")"
url_b64="$(printf '%s' "$POS_SETUP_URL" | base64 -w 0)"

sed \
    -e "s|__ROOT_CERT_BASE64__|${root_b64}|g" \
    -e "s|__SIGNING_CERT_BASE64__|${signing_b64}|g" \
    -e "s|__POS_SETUP_URL_BASE64__|${url_b64}|g" \
    "$TEMPLATE_PATH" >"$INSTALLER_OUTPUT"
chmod 644 "$INSTALLER_OUTPUT"

openssl verify -CAfile "$ROOT_CERT" "$SIGNING_CERT_ONLY" >/dev/null
openssl pkey -in "$SIGNING_KEY" -check -noout >/dev/null

cat <<EOF

Tayyor.

Production .env:
QZ_SIGNING_ENABLED=true
QZ_CERTIFICATE_PATH=${DIGITAL_CERT}
QZ_PRIVATE_KEY_PATH=${SIGNING_KEY}
QZ_PRIVATE_KEY_PASSPHRASE=
VITE_QZ_SIGNED_PRINTING=true

Organizationlarga tarqatiladigan yagona fayl:
${INSTALLER_OUTPUT}

MUHIM:
- private-key.pem va root-ca-private-key.pem fayllarini hech kimga bermang.
- root-ca-private-key.pem ni shifrlangan offline backupga ko'chiring.
- Sertifikatlarni qayta yaratish barcha kassalarda installerni qayta ishlatishni talab qiladi.
EOF
