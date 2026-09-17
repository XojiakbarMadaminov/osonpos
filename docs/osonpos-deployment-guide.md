# OsonPOS Deployment Guide
Ubuntu 24.04 + Nginx + PHP 8.4 + PostgreSQL + Redis + Supervisor + SSL

Server IP: `91.213.99.169`  
Domain: `https://osonpos.uz`  
Project path: `/var/www/osonpos`

---

## 1. Serverga SSH orqali ulanish

```bash
ssh gptuchun1129@91.213.99.169
```

Agar boshqa user ishlatilsa:

```bash
ssh USERNAME@91.213.99.169
```

---

## 2. Serverni yangilash

```bash
sudo apt update
sudo apt upgrade -y
```

Asosiy paketlar:

```bash
sudo apt install -y \
curl \
git \
unzip \
zip \
ca-certificates \
gnupg \
software-properties-common \
supervisor \
nginx \
postgresql \
postgresql-contrib \
redis-server
```

---

## 3. RAM va swap holatini tekshirish

```bash
free -h
```

Ushbu server konfiguratsiyasi:

- 2 vCPU
- 2 GB RAM
- 30 GB SSD
- ~2 GB swap

Swap mavjud bo‘lsa qayta yaratish shart emas.

---

## 4. PHP 8.4 o‘rnatish

Repository qo‘shish:

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
```

PHP va kerakli extensionlar:

```bash
sudo apt install -y \
php8.4 \
php8.4-cli \
php8.4-fpm \
php8.4-common \
php8.4-pgsql \
php8.4-mbstring \
php8.4-xml \
php8.4-curl \
php8.4-zip \
php8.4-gd \
php8.4-bcmath \
php8.4-intl \
php8.4-redis \
php8.4-opcache
```

Tekshirish:

```bash
php -v
sudo systemctl status php8.4-fpm
```

---

## 5. PHP-FPM ni 2 GB RAM uchun sozlash

Config:

```bash
sudo nano /etc/php/8.4/fpm/pool.d/www.conf
```

Quyidagicha sozlash:

```ini
pm = dynamic
pm.max_children = 8
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500
```

Restart:

```bash
sudo systemctl restart php8.4-fpm
```

---

## 6. PHP limitlarini sozlash

```bash
sudo nano /etc/php/8.4/fpm/php.ini
```

Masalan:

```ini
memory_limit = 256M
upload_max_filesize = 20M
post_max_size = 25M
max_execution_time = 60
```

Restart:

```bash
sudo systemctl restart php8.4-fpm
```

---

## 7. OPcache sozlash

```bash
sudo nano /etc/php/8.4/fpm/conf.d/10-opcache.ini
```

Qo‘shish:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
```

Restart:

```bash
sudo systemctl restart php8.4-fpm
```

Deploy paytida PHP-FPM reload/restart qilish kerak, chunki `opcache.validate_timestamps=0`.

---

## 8. Composer o‘rnatish

```bash
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
rm composer-setup.php
```

Tekshirish:

```bash
composer --version
```

---

## 9. PostgreSQL sozlash

PostgreSQL holati:

```bash
sudo systemctl enable postgresql
sudo systemctl start postgresql
sudo systemctl status postgresql
```

PostgreSQL shell:

```bash
sudo -u postgres psql
```

User yaratish:

```sql
CREATE USER osonpos WITH PASSWORD 'STRONG_PASSWORD_HERE';
```

Database yaratish:

```sql
CREATE DATABASE osonpos OWNER osonpos;
```

Chiqish:

```sql
\q
```

Ulanishni tekshirish:

```bash
psql -h 127.0.0.1 -U osonpos -d osonpos
```

Ichida:

```sql
SELECT current_database();
```

Chiqish:

```sql
\q
```

Muhim:

- PostgreSQL port `5432` public internetga ochilmasin
- `DB_HOST=127.0.0.1` ishlatiladi

---

## 10. Redis sozlash

Enable va start:

```bash
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

Tekshirish:

```bash
redis-cli ping
```

Natija:

```text
PONG
```

2 GB RAM uchun memory limit:

```bash
sudo nano /etc/redis/redis.conf
```

Qo‘shish yoki o‘zgartirish:

```ini
maxmemory 128mb
maxmemory-policy allkeys-lru
```

Restart:

```bash
sudo systemctl restart redis-server
```

---

## 11. Node.js o‘rnatish

Node.js 22:

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install nodejs -y
```

Tekshirish:

```bash
node -v
npm -v
```

---

## 12. Project papkasini tayyorlash

```bash
sudo mkdir -p /var/www/osonpos
sudo chown -R $USER:$USER /var/www/osonpos
```

Agar clone home papkada qilinsa:

```bash
git clone https://github.com/XojiakbarMadaminov/osonpos.git ~/osonpos
```

Keyin:

```bash
sudo rm -rf /var/www/osonpos
sudo mv ~/osonpos /var/www/osonpos
sudo chown -R $USER:$USER /var/www/osonpos
```

Projectni tekshirish:

```bash
cd /var/www/osonpos
ls -la
```

Laravel fayllari ko‘rinishi kerak:

```text
artisan
app
bootstrap
composer.json
config
database
public
resources
routes
storage
```

---

## 13. Composer dependencies

```bash
cd /var/www/osonpos

composer install \
  --no-dev \
  --prefer-dist \
  --optimize-autoloader \
  --no-interaction
```

---

## 14. `.env` yaratish

```bash
cp .env.example .env
nano .env
```

Asosiy production config:

```env
APP_NAME=OsonPOS
APP_ENV=production
APP_DEBUG=false
APP_URL=https://osonpos.uz

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=osonpos
DB_USERNAME=osonpos
DB_PASSWORD=STRONG_PASSWORD_HERE

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Application key:

```bash
php artisan key:generate
```

`.env` Git repositoryga commit qilinmasin.

---

## 15. Frontend build

```bash
npm ci
npm run build
```

Agar `package-lock.json` bo‘lmasa:

```bash
npm install
npm run build
```

Diskni tejash uchun builddan keyin:

```bash
rm -rf node_modules
```

---

## 16. Laravel migration

```bash
php artisan migrate --force
```

Seeder kerak bo‘lsa:

```bash
php artisan db:seed --force
```

yoki:

```bash
php artisan migrate --seed --force
```

---

## 17. Storage link

```bash
php artisan storage:link
```

`already exists` chiqsa muammo emas.

---

## 18. Laravel permissions

Deploy userni `www-data` guruhiga qo‘shish:

```bash
sudo usermod -aG www-data gptuchun1129
```

Keyin SSH sessiondan chiqib qayta kirish:

```bash
exit
ssh gptuchun1129@91.213.99.169
```

Tekshirish:

```bash
groups
```

`www-data` ko‘rinishi kerak.

Writable papkalar:

```bash
sudo chown -R gptuchun1129:www-data /var/www/osonpos/storage
sudo chown -R gptuchun1129:www-data /var/www/osonpos/bootstrap/cache

sudo chmod -R 775 /var/www/osonpos/storage
sudo chmod -R 775 /var/www/osonpos/bootstrap/cache
```

Kelajakda yaratiladigan papkalar ham groupni saqlab qolsin:

```bash
sudo find /var/www/osonpos/storage -type d -exec chmod g+s {} \;
sudo find /var/www/osonpos/bootstrap/cache -type d -exec chmod g+s {} \;
```

Agar log permission xatosi chiqsa:

```bash
sudo chown -R gptuchun1129:www-data /var/www/osonpos/storage/logs
sudo chmod -R 775 /var/www/osonpos/storage/logs
```

---

## 19. Laravel optimize

Avval:

```bash
php artisan optimize:clear
```

Keyin:

```bash
php artisan optimize
```

Agar permission sababli ishlamasa:

```bash
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan optimize
```

---

## 20. Nginx config

Config file:

```bash
sudo nano /etc/nginx/sites-available/osonpos
```

HTTP uchun asosiy config:

```nginx
server {
    listen 80;
    listen [::]:80;

    server_name osonpos.uz www.osonpos.uz;

    root /var/www/osonpos/public;
    index index.php index.html;

    charset utf-8;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico {
        access_log off;
        log_not_found off;
    }

    location = /robots.txt {
        access_log off;
        log_not_found off;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;

        fastcgi_pass unix:/run/php/php8.4-fpm.sock;

        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;

        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable:

```bash
sudo ln -s /etc/nginx/sites-available/osonpos /etc/nginx/sites-enabled/osonpos
```

Agar `File exists` chiqsa, symlink allaqachon mavjud.

Default configni olib tashlash:

```bash
sudo rm -f /etc/nginx/sites-enabled/default
```

Tekshirish:

```bash
sudo nginx -t
```

Natija:

```text
syntax is ok
test is successful
```

Reload:

```bash
sudo systemctl reload nginx
```

---

## 21. DNS sozlash

Domain provider panelida:

```text
@      A       91.213.99.169
www    CNAME   osonpos.uz
```

A record yangi VPS IP ga qarashi kerak.

Tekshirish:

```bash
dig +short osonpos.uz
```

yoki:

```bash
dig @8.8.8.8 +short osonpos.uz
dig @1.1.1.1 +short osonpos.uz
```

Natija:

```text
91.213.99.169
```

`www`:

```bash
dig +short www.osonpos.uz
```

HTTP tekshirish:

```bash
curl -I http://osonpos.uz
```

Laravel redirect qilsa, masalan:

```text
HTTP/1.1 302 Found
Location: http://osonpos.uz/admin
```

bu normal.

---

## 22. SSL / HTTPS

Certbot:

```bash
sudo apt update
sudo apt install certbot python3-certbot-nginx -y
```

SSL:

```bash
sudo certbot --nginx -d osonpos.uz -d www.osonpos.uz
```

Agar mavjud sertifikat topilsa:

```text
1: Attempt to reinstall this existing certificate
2: Renew & replace the certificate
```

Sertifikat hali amal qilayotgan bo‘lsa:

```text
1
```

tanlash yetarli.

Tekshirish:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

HTTPS:

```bash
curl -I https://osonpos.uz
```

`200`, `301` yoki `302` chiqishi kerak.

Renew test:

```bash
sudo certbot renew --dry-run
```

---

## 23. Firewall

SSH va Nginx:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

Tekshirish:

```bash
sudo ufw status
```

Public portlar:

```text
22
80
443
```

Quyidagilar public bo‘lmasin:

```text
5432 PostgreSQL
6379 Redis
```

---

## 24. Queue worker - Supervisor

Config:

```bash
sudo nano /etc/supervisor/conf.d/osonpos-worker.conf
```

Ichiga:

```ini
[program:osonpos-worker]
process_name=%(program_name)s
command=/usr/bin/php /var/www/osonpos/artisan queue:work redis --sleep=3 --tries=3 --timeout=120 --max-jobs=1000
directory=/var/www/osonpos
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/osonpos/storage/logs/worker.log
stopwaitsecs=3600
```

Apply:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Kutiladigan holat:

```text
osonpos-worker RUNNING
```

---

## 25. Laravel Scheduler

Root crontab:

```bash
sudo crontab -e
```

Qo‘shish:

```cron
* * * * * cd /var/www/osonpos && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

---

## 26. Servicelarni tekshirish

```bash
sudo systemctl status nginx --no-pager
sudo systemctl status php8.4-fpm --no-pager
sudo systemctl status postgresql --no-pager
sudo systemctl status redis-server --no-pager
sudo supervisorctl status
```

---

## 27. RAM monitoring

```bash
free -h
```

Eng ko‘p RAM ishlatayotgan processlar:

```bash
ps aux --sort=-%mem | head -15
```

2 GB server uchun:

- PHP-FPM workerlar nazoratda bo‘lsin
- queue worker sonini ko‘paytirmaslik kerak
- Redis 128 MB bilan limitlangan
- swap mavjud bo‘lishi yaxshi

---

## 28. Disk monitoring

```bash
df -h
```

Laravel log hajmi:

```bash
du -sh /var/www/osonpos/storage/logs
```

30 GB diskda log va media fayllarni nazorat qilish muhim.

---

# Keyingi deploylar uchun qisqa workflow

Kod GitHubga push qilingandan keyin serverda:

```bash
cd /var/www/osonpos
```

## 1. Maintenance mode

```bash
php artisan down
```

Agar zero-downtime talab bo‘lmasa shu yetarli.

## 2. Kodni olish

```bash
git pull
```

## 3. Composer

```bash
composer install \
  --no-dev \
  --prefer-dist \
  --optimize-autoloader \
  --no-interaction
```

## 4. Frontend

```bash
npm ci
npm run build
rm -rf node_modules
```

## 5. Migration

```bash
php artisan migrate --force
```

## 6. Cache clear va optimize

```bash
php artisan optimize:clear
php artisan optimize
```

## 7. Queue restart

```bash
php artisan queue:restart
```

## 8. PHP-FPM reload

```bash
sudo systemctl reload php8.4-fpm
```

## 9. Nginx reload

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## 10. Maintenance mode off

```bash
php artisan up
```

---

# Tavsiya etilgan deploy script

File:

```bash
nano /var/www/osonpos/deploy.sh
```

Ichiga:

```bash
#!/bin/bash

set -e

cd /var/www/osonpos

echo "Maintenance mode..."
php artisan down || true

echo "Pulling latest code..."
git pull

echo "Installing Composer dependencies..."
composer install \
  --no-dev \
  --prefer-dist \
  --optimize-autoloader \
  --no-interaction

echo "Building frontend..."
npm ci
npm run build
rm -rf node_modules

echo "Running migrations..."
php artisan migrate --force

echo "Optimizing Laravel..."
php artisan optimize:clear
php artisan optimize

echo "Restarting queue..."
php artisan queue:restart

echo "Reloading PHP-FPM..."
sudo systemctl reload php8.4-fpm

echo "Checking Nginx..."
sudo nginx -t

echo "Reloading Nginx..."
sudo systemctl reload nginx

echo "Leaving maintenance mode..."
php artisan up

echo "Deployment completed."
```

Executable qilish:

```bash
chmod +x /var/www/osonpos/deploy.sh
```

Keyingi safar:

```bash
cd /var/www/osonpos
./deploy.sh
```

---

# Muhim troubleshooting commandlari

## Laravel log

```bash
tail -f /var/www/osonpos/storage/logs/laravel.log
```

Agar daily log ishlatilsa:

```bash
ls -lah /var/www/osonpos/storage/logs
```

Keyin kerakli log:

```bash
tail -f /var/www/osonpos/storage/logs/laravel-YYYY-MM-DD.log
```

## Nginx error

```bash
sudo tail -f /var/log/nginx/error.log
```

## Nginx access

```bash
sudo tail -f /var/log/nginx/access.log
```

## PHP-FPM

```bash
sudo journalctl -u php8.4-fpm -f
```

## Queue

```bash
sudo supervisorctl status
```

Worker log:

```bash
tail -f /var/www/osonpos/storage/logs/worker.log
```

## PostgreSQL

```bash
sudo systemctl status postgresql
```

## Redis

```bash
redis-cli ping
```

## DNS

```bash
dig +short osonpos.uz
dig +short www.osonpos.uz
```

## HTTP

```bash
curl -I http://osonpos.uz
```

## HTTPS

```bash
curl -I https://osonpos.uz
```

---

# Final architecture

```text
Internet
   |
   v
osonpos.uz
   |
   v
91.213.99.169
   |
   v
Nginx
   |
   v
PHP 8.4 FPM
   |
   v
Laravel OsonPOS
   |
   +---- PostgreSQL
   |
   +---- Redis
   |
   +---- Supervisor / Queue
   |
   +---- Laravel Scheduler
```

Printer integration uchun QZ Tray serverda emas, kassadagi lokal kompyuterda ishlaydi.

---

# Production checklist

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL=https://osonpos.uz`
- [ ] PostgreSQL local
- [ ] Redis local
- [ ] `5432` public emas
- [ ] `6379` public emas
- [ ] SSL ishlayapti
- [ ] Certbot renew ishlayapti
- [ ] Queue `RUNNING`
- [ ] Scheduler cron mavjud
- [ ] Nginx `syntax is ok`
- [ ] PHP-FPM running
- [ ] Laravel optimize qilingan
- [ ] `storage` permission to‘g‘ri
- [ ] `bootstrap/cache` permission to‘g‘ri
- [ ] Backup strategiyasi bor
- [ ] Disk va RAM monitoring qilinadi
