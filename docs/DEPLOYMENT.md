# Deployment guide

Two supported paths: **A. VPS (Ubuntu + Nginx)** and **B. shared hosting (cPanel)**. Read sections 1 to 3 first.

## 1. Before you start

You need: a domain name, hosting, an SMTP service for email, and (for online payment) an SSLCommerz account.
Test everything with SSLCommerz **sandbox** keys first. Live keys come from SSLCommerz after merchant approval.

**Local vs production**

| Setting                                     | Local                     | Production                                 |
| ------------------------------------------- | ------------------------- | ------------------------------------------ |
| `APP_ENV` / `APP_DEBUG`                     | `local` / `true`          | `production` / `false`                     |
| `APP_URL`                                   | `http://127.0.0.1:8000`   | `https://your-domain` (must match exactly) |
| `MAIL_MAILER`                               | `log`                     | `smtp`                                     |
| `QUEUE_CONNECTION`                          | `database` + `queue:work` | `database` + Supervisor or cron            |
| `SESSION_SECURE_COOKIE` / `SESSION_ENCRYPT` | `false`                   | `true`                                     |
| `LOG_STACK` / `LOG_LEVEL`                   | `single` / `debug`        | `daily` / `warning`                        |
| `SSLCOMMERZ_SANDBOX`                        | `true`                    | `false` (only when going live)             |
| Assets                                      | `npm run dev`             | `npm run build`                            |

Keep `APP_TIMEZONE=Asia/Dhaka` from the first day. Changing it later shifts every stored reservation time.

## 2. Server requirements

- PHP: the version in `composer.json` (`php -v`), with mbstring, openssl, pdo_mysql, tokenizer, xml, ctype, fileinfo,
  curl, bcmath, gd (with WebP: `php -r "var_dump(function_exists('imagewebp'));"` must print `true`).
- MySQL 8 or MariaDB, database charset `utf8mb4` (needed for Bangla text).
- `php.ini`: `memory_limit = 256M`, `upload_max_filesize = 4M`, `post_max_size = 8M`, `expose_php = Off`, OPcache on.
  Image resizing needs the memory, and the PHP default upload limit (2M) is too tight for 2 MB photos.
- HTTPS. The site, the payment callbacks and secure cookies all assume it.

## 3. Production `.env`

Start from `.env.example` and change at least:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain
LOG_STACK=daily
LOG_LEVEL=warning
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
DB_DATABASE=...  DB_USERNAME=...  DB_PASSWORD=...
MAIL_MAILER=smtp  MAIL_HOST=...  MAIL_PORT=587  MAIL_USERNAME=...  MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="hello@your-domain"
ADMIN_EMAIL=you@your-domain
```

Rules: never commit `.env`; never use the database `root` user; `APP_URL` must be the real https address (the app builds
emails, the sitemap and payment callback URLs from it).

---

## A. VPS (Ubuntu 24.04 + Nginx)

**1. Server basics**

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server php-fpm php-cli php-mysql php-mbstring php-xml php-curl php-gd php-bcmath php-zip unzip git supervisor
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable
```

Use SSH keys and turn off password login (`PasswordAuthentication no` in `/etc/ssh/sshd_config`). If the packaged PHP is
older than `composer.json` requires, use a PHP repository such as ppa:ondrej/php.
Install Composer from getcomposer.org. Install Node only if you want to build assets on the server (same major version as `node -v` on your PC);
the alternative is to build locally and upload `public/build`.

**2. Database**

```sql
CREATE DATABASE meher CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'meher'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT ALL PRIVILEGES ON meher.* TO 'meher'@'localhost';
```

**3. Code**

```bash
sudo mkdir -p /var/www/meher && sudo chown deploy:www-data /var/www/meher   # "deploy" = your login user
cd /var/www/meher && git clone <your-repo-url> .
cp .env.example .env && nano .env        # production values from section 3
```

**4. Install and build**

```bash
umask 0002
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
npm ci && npm run build                  # or upload public/build from your PC
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder --force   # SAVE the admin password it prints
php artisan storage:link
```

After saving the admin password, remove `ADMIN_PASSWORD` from `.env` if you set one.
Do **not** run the plain `db:seed` on a live site: it adds sample menu items and fake testimonials.

**5. Permissions**

```bash
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod g+s {} \;
```

**6. Nginx.** Copy `deploy/nginx.conf` to `/etc/nginx/sites-available/meher`, fix the domain, path and PHP socket
(`ls /run/php`), then:

```bash
sudo ln -s /etc/nginx/sites-available/meher /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Point the domain's DNS A record (and `www`) to the server first.

**7. HTTPS**

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain -d www.your-domain
sudo certbot renew --dry-run
```

Then make sure `APP_URL=https://...` and `SESSION_SECURE_COOKIE=true` are set. If the server sits behind a proxy or CDN
that terminates HTTPS (Cloudflare, a load balancer), add `$middleware->trustProxies(at: '*');` inside `withMiddleware`
in `bootstrap/app.php` **only if** the origin accepts traffic from that proxy alone.

**8. PHP settings.** Edit `/etc/php/8.3/fpm/php.ini` (version may differ) per section 2, then `sudo systemctl reload php8.3-fpm`.

**9. Queue worker and scheduler**

```bash
sudo cp deploy/supervisor-queue.conf /etc/supervisor/conf.d/meher-queue.conf
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start meher-queue:*
sudo crontab -u www-data -e
# add:
* * * * * cd /var/www/meher && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs `orders:expire-unpaid` every 10 minutes.

**10. Cache and check**

```bash
php artisan optimize
php artisan app:preflight
```

Fix every `FAIL` before announcing the site. After any `.env` change run `php artisan config:cache`.

---

## B. Shared hosting (cPanel)

1. **PHP:** pick the PHP version from `composer.json` (MultiPHP Manager), enable the extensions in section 2, and set the
   `php.ini` values in MultiPHP INI Editor.
2. **Database:** MySQL Databases > create a database and user, add the user to the database with all privileges.
   Names get a prefix (for example `cpuser_meher`); use the full names in `.env`.
3. **Build on your PC:** `composer install --no-dev --optimize-autoloader` and `npm run build`.
   Upload the project (including `vendor` and `public/build`; excluding `.git`, `node_modules`, `.env`, `tests`) with SFTP
   to a folder **outside** `public_html`, for example `/home/USER/meher`.
4. **Document root, preferred:** create the domain or subdomain with its document root set to `/home/USER/meher/public`.
5. **If you must use `public_html`:** copy the contents of `meher/public` into `public_html`, then change
   `public_html/index.php` to:

    ```php
    <?php

    use Illuminate\Http\Request;

    define('LARAVEL_START', microtime(true));

    if (file_exists($maintenance = __DIR__.'/../meher/storage/framework/maintenance.php')) {
        require $maintenance;
    }

    require __DIR__.'/../meher/vendor/autoload.php';

    $app = require_once __DIR__.'/../meher/bootstrap/app.php';
    $app->usePublicPath(__DIR__);
    $app->handleRequest(Request::capture());
    ```

6. **`.env`:** create it in `/home/USER/meher` (File Manager). Generate the key on your PC with
   `php artisan key:generate --show` and paste it as `APP_KEY`.
7. **Commands** (cPanel Terminal or SSH; no SSH means a one-time cron entry per command):
    ```bash
    cd ~/meher
    php artisan migrate --force
    php artisan db:seed --class=ProductionSeeder --force   # save the admin password
    php artisan storage:link
    php artisan optimize
    php artisan app:preflight
    ```
    If symlinks are blocked, in `config/filesystems.php` set the `public` disk to `'root' => public_path('storage')` and
    `'url' => env('APP_URL').'/storage'`, and skip `storage:link`.
8. **HTTPS:** enable AutoSSL (or Let's Encrypt) and force HTTPS, then set `APP_URL=https://...` and run `php artisan config:cache`.
9. **Cron (Cron Jobs; use the PHP path cPanel shows, for example `/usr/local/bin/php`):**
    ```
    * * * * * /usr/local/bin/php /home/USER/meher/artisan schedule:run >> /dev/null 2>&1
    * * * * * /usr/local/bin/php /home/USER/meher/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
    ```
    The second line replaces Supervisor; emails go out within about a minute. Or set `QUEUE_CONNECTION=sync` (emails send
    during the request, which is slower but needs no cron).

---

## 4. Real content before launch

1. Admin > **Settings:** name, cuisine, tagline, hours, **real phone, email and address**, social links, Google Maps embed URL.
2. Admin > **Categories / Menu items:** real dishes, prices, photos, sizes and extras.
3. Delete or replace the sample **Testimonials**, **Team**, **Offers** and **FAQ** (the seeders add examples).
4. Delete the sample `@meher.test` admin if it exists; create real manager and staff users.
5. Replace the Privacy Policy and Terms text in `lang/*/pages.php` with wording reviewed by a lawyer.

## 5. Email

Use your host's SMTP or a transactional provider. Set SPF and DKIM (and ideally DMARC) DNS records for the sending domain,
and use a `MAIL_FROM_ADDRESS` on that domain, or reservation and order emails will land in spam. Many VPS providers block
port 25; use 587 or 465. Test:

```bash
php artisan tinker
>>> Mail::raw('SMTP test', fn ($m) => $m->to('you@example.com')->subject('SMTP test'));
```

With `QUEUE_CONNECTION=database`, mail sent by the app itself is queued, so the worker must be running.

## 6. Going live with SSLCommerz

1. Get live Store ID and Password from SSLCommerz (different from sandbox).
2. In the SSLCommerz merchant panel set the IPN URL to `https://your-domain/payments/sslcommerz/ipn`.
3. `.env`: `ONLINE_PAYMENT=true`, `SSLCOMMERZ_SANDBOX=false`, live credentials, then `php artisan config:cache`.
4. Make sure the payment callback paths (`/payments/sslcommerz/*`) are not blocked by a firewall, basic auth or a WAF rule.
5. Place a small real order, pay it, and check: the order becomes **Paid**, the kitchen alert email arrives, and the order's
   _Payment attempts_ table shows a `val_id`. Then refund it from the SSLCommerz panel.
6. Run `php artisan app:preflight`: it fails while sandbox mode is on.

The app never trusts the browser or the callback body: every payment is confirmed with SSLCommerz's validation API
(transaction id, amount and currency must match) before an order is marked paid.

## 7. Backups and restore

“The website can also back itself up: Admin > Backups, and a daily backup:run through the scheduler. These files stay on the server, so still copy them off-site.”
One-time setup on the server:

```sql
CREATE USER 'meher_backup'@'localhost' IDENTIFIED BY 'another-long-password';
GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON meher.* TO 'meher_backup'@'localhost';
```

```bash
sudo tee /etc/meher-backup.cnf >/dev/null <<'EOF'
[client]
user=meher_backup
password=another-long-password
EOF
sudo chmod 600 /etc/meher-backup.cnf
sudo mkdir -p /var/backups/meher && sudo chmod 700 /var/backups/meher
chmod +x deploy/backup.sh deploy/deploy.sh
sudo crontab -e      # add:   30 3 * * * /var/www/meher/deploy/backup.sh >> /var/log/meher-backup.log 2>&1
```

Backups on the same server do not protect against losing the server: copy them off-site (rsync, rclone, or your host's
snapshots). Keep a copy of `.env` in a password manager. On shared hosting use cPanel's backup tools plus a database
export from phpMyAdmin.

**Restore (practise this once on a scratch database):**

```bash
mysql -u root -p -e "CREATE DATABASE meher_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gunzip -c /var/backups/meher/db-YYYYMMDD-HHMMSS.sql.gz | mysql -u root -p meher_restore
tar -xzf /var/backups/meher/uploads-YYYYMMDD-HHMMSS.tar.gz -C /var/www/meher/storage/app
cd /var/www/meher && php artisan storage:link && php artisan optimize:clear && php artisan optimize
```

For a real restore import into `meher` instead, with the site in maintenance mode (`php artisan down`).
**Never run `php artisan test` on the live server's database.** The test suite refuses to, but do not rely on that.

## 8. After deploying: smoke test

- [ ] `php artisan app:preflight` shows no `FAIL`.
- [ ] `http://` redirects to `https://`; `curl -I https://your-domain` shows `Strict-Transport-Security` and `Content-Security-Policy`.
- [ ] Home, menu, a dish page, about, gallery, contact, reservation, privacy and terms load; Bangla switch works; `/no-such-page` shows the custom 404 without a stack trace.
- [ ] `https://your-domain/.env` and `https://your-domain/.git/config` return 404 or 403 (never the file).
- [ ] `/robots.txt` hides `/admin`, `/cart`, `/checkout`; `/sitemap.xml` lists pages. Submit the sitemap in Google Search Console.
- [ ] Admin login works; edit a setting and see it on the site; upload a photo (a `.webp` and a `-480.webp` appear in `storage/app/public`).
- [ ] Contact form message appears in Admin > Messages; reservation email arrives (check spam); confirming it sends the status email.
- [ ] Cash order end to end: the kitchen alert arrives, admin moves it through every status, the tracking page updates.
- [ ] Online payment (section 6) with a small real order.
- [ ] `sudo supervisorctl status` shows the worker running (VPS); `php artisan schedule:list` shows `orders:expire-unpaid`.
- [ ] A backup ran, and you restored it on a scratch database.

## 9. Updating and rollback

```bash
./deploy/backup.sh          # before anything with migrations
./deploy/deploy.sh
sudo systemctl reload php8.3-fpm
```

Rollback: put the site in maintenance mode, `git checkout <previous-tag-or-commit>`, `composer install --no-dev`,
rebuild assets, `php artisan optimize`, `php artisan queue:restart`, then `php artisan up`. If the release ran migrations,
restore the database backup instead of trying to reverse them by hand.

## 10. Troubleshooting

| Symptom                             | Likely cause and fix                                                                                                                                       |
| ----------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Blank page or 500                   | Read `storage/logs/laravel-*.log`. Usually permissions on `storage` / `bootstrap/cache`, a missing `.env`, or a missing `vendor` folder.                   |
| Page has no styles                  | `public/build` missing (run `npm run build`) or a stale `public/hot` file (delete it).                                                                     |
| "Permission denied" writing the log | A command ran as another user. Re-run the permission commands in step A5; run artisan with `umask 0002`.                                                   |
| 419 Page Expired everywhere         | `APP_URL` or the domain does not match, or `SESSION_SECURE_COOKIE=true` while the site is on http.                                                         |
| Photos missing                      | `php artisan storage:link` not run, or the symlink points to an old path.                                                                                  |
| Emails never arrive                 | Queue worker not running; SMTP details wrong (check `storage/logs`); SPF/DKIM missing (goes to spam).                                                      |
| Order stays "Unpaid" after paying   | Check the `payments` table and the log for "SSLCommerz validation" or "amount or currency mismatch"; confirm the live keys and `SSLCOMMERZ_SANDBOX=false`. |
| Config changes ignored              | `php artisan config:cache` (or `optimize:clear` to turn caching off).                                                                                      |
| Search results show only English    | Known limitation: the language is not in the URL (see README).                                                                                             |
