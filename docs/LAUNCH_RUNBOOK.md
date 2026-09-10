# OsonPOS launch runbook

## Server preparation

1. Install PHP 8.4 FPM with PostgreSQL, Redis, mbstring, intl, curl, XML,
   zip, and bcmath extensions; PostgreSQL 17; Redis; Nginx; Supervisor;
   Composer; and Node 20.19 or newer with pnpm 11.
2. Deploy to `/var/www/osonpos`, copy `.env.production.example` to `.env`,
   replace every domain and `CHANGE_ME` value, and run `php artisan key:generate`.
3. Install `deploy/nginx/osonpos.conf`, issue the TLS certificate, install
   `deploy/supervisor/osonpos-worker.conf`, and apply the local-only Redis
   settings from `deploy/redis/osonpos.conf` with a strong password or ACL.
4. Run `bin/deploy-production /var/www/osonpos`, then verify `/up` returns 200.

The deploy command installs locked dependencies, builds Vue assets, enters
maintenance mode, runs migrations, optimizes Laravel and Filament, restarts
workers, and always brings the application back up if a step fails.

## Backups

Run `bin/backup-postgres /srv/backups/osonpos 14` daily from a protected systemd
timer or cron job with `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, and
`PGPASSWORD` supplied by a root-readable environment file. Regularly test:

```bash
createdb osonpos_restore_test
pg_restore --clean --if-exists --no-owner --dbname=osonpos_restore_test /srv/backups/osonpos/osonpos-TIMESTAMP.dump
```

Keep an encrypted off-host copy. A backup is not considered valid until a
restore test succeeds.

## First customer

1. Create the first platform operator with
   `php artisan app:platform-admin operator@example.uz`.
2. Sign in at `/platform`, create or verify the MVP plan/features, then use
   **Onboard organization**. This creates the subscription, first store, Owner,
   default roles, permissions, and logical print routes.
3. The owner signs in at `/admin`, creates the cashier under **Users**, assigns
   allowed stores and the Cashier role, and verifies Products, Tables, Printers,
   Print Routes, and Settings.
4. The cashier opens `/pos/device-setup`, registers the terminal, discovers OS
   printers through QZ Tray, binds logical printers, and runs the test print.

## QZ Tray and printers

Install the current QZ Tray desktop application on each POS terminal. Import the
matching QZ certificate, keep the private key only at the server path configured
by `QZ_PRIVATE_KEY_PATH`, and enable both `QZ_SIGNING_ENABLED` and
`VITE_QZ_SIGNED_PRINTING` before the production build. Never copy the private key
to `public/`, `resources/js`, or a terminal.

In `/admin`, create physical printer records and map `CUSTOMER_RECEIPT` and
`KITCHEN_TICKET` under Print Routes. They may point to the same printer. Bind the
OS printer name from `/pos/device-setup`; printer names are never hardcoded.

The installation-site hardware checklist is:

1. QZ connects and lists installed printers.
2. Test print, kitchen ticket, and customer receipt print and cut correctly.
3. Both logical types work on one printer, then kitchen is rerouted to another.
4. Stopping QZ shows an error; restarting reconnects.
5. Printer failure leaves orders/payments saved; retry shows `REPRINT`.
6. Adding an item after a kitchen print prints only the new item.

## Final acceptance

Run `php artisan test`, `vendor/bin/pint --test`, `pnpm test:unit`,
`pnpm typecheck`, and `pnpm build`. Then exercise login, shift open/close,
dine-in, takeaway, delivery, split/partial payment, kitchen print, receipt,
reports, user/role management, subscription expiry, and a second-tenant IDOR
attempt. Record the hardware printer results during the on-site check.
