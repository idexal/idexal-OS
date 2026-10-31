# idexal Os — Deployment Guide

This guide covers local testing with XAMPP and a production deployment at
`https://os.idexal.com`.

The Laravel application lives in `website-laravel-idexalOs`. Its public web
root is `website-laravel-idexalOs/public`, not the repository root.

---

## 1. Requirements

| Component | Minimum |
| --- | --- |
| PHP | 8.4 |
| MySQL / MariaDB | MySQL 8 / MariaDB 10.4 |
| Composer | 2.x |
| Node.js | 20 (only needed to build assets) |
| Web server | Apache 2.4 or Nginx |

Required PHP extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`,
`json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`.

The invoice PDF renderer uses mPDF and needs `mbstring`, `gd`, and `xml`.

---

## 2. Local setup with XAMPP

1. Copy the application somewhere under `C:\xampp\htdocs`, for example
   `C:\xampp\htdocs\idexal-os`.
2. Create the database:

   ```sql
   CREATE DATABASE idexal_os CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Create the environment file and application key:

   ```powershell
   cd "C:\xampp\htdocs\idexal-os\website-laravel-idexalOs"
   Copy-Item .env.example .env
   php artisan key:generate
   ```

   Set these values in `.env`:

   ```dotenv
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost:8000
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=idexal_os
   DB_USERNAME=root
   DB_PASSWORD=
   SESSION_SECURE_COOKIE=false
   MAIL_MAILER=log
   ```

4. Install dependencies and build assets:

   ```powershell
   composer install
   npm ci
   npm run build
   php artisan migrate --seed
   php artisan storage:link
   ```

5. Start the local server:

   ```powershell
   php artisan serve --host=127.0.0.1 --port=8000
   ```

   Open `http://127.0.0.1:8000`.

   If port 8000 is already used by another application, use another port:

   ```powershell
   php artisan serve --host=127.0.0.1 --port=8010
   ```

6. The seeded administrator is:

   - Email: `admin@idexal.com`
   - Password: the value of `ADMIN_PASSWORD` in `.env`.
   - If `ADMIN_PASSWORD` is empty, the seeder generates a random password and
     prints it once. Save it immediately.

   Change the password after the first login.

### Using Apache instead of `artisan serve`

Point an Apache virtual host at the `public` directory:

```apache
<VirtualHost *:80>
    ServerName idexal-os.local
    DocumentRoot "C:/xampp/htdocs/idexal-os/website-laravel-idexalOs/public"

    <Directory "C:/xampp/htdocs/idexal-os/website-laravel-idexalOs/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Enable `mod_rewrite`, add `127.0.0.1 idexal-os.local` to
`C:\Windows\System32\drivers\etc\hosts`, restart Apache, then visit
`http://idexal-os.local`.

---

## 3. Production deployment

### 3.1 Upload the files

Upload the entire `website-laravel-idexalOs` directory. Configure the domain's
document root to:

```text
/path/to/website-laravel-idexalOs/public
```

Never serve the repository root directly. A root document root would expose
`.env`, `storage`, and application source files.

If the host does not allow changing the document root, create a subdomain whose
web root points to `public`, or move the contents of `public` to the domain root
and update `public/index.php` paths carefully. Changing the document root is the
supported approach.

### 3.2 Install PHP dependencies

With SSH:

```bash
composer install --no-dev --optimize-autoloader
```

Without SSH, run `composer install --no-dev --optimize-autoloader` locally and
upload the `vendor` directory. Also upload the already-built `public/build`
directory, or run `npm ci && npm run build` on the server.

### 3.3 Configure `.env`

Copy `.env.example` to `.env` and set at least:

```dotenv
APP_NAME="idexal Os"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://os.idexal.com

APP_LOCALE=ar
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cpanel_user_idexal_os
DB_USERNAME=cpanel_user_idexal
DB_PASSWORD=replace-with-a-strong-password

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_DOMAIN=null

CACHE_STORE=database
QUEUE_CONNECTION=database
LOG_LEVEL=warning

MAIL_MAILER=smtp
MAIL_HOST=mail.idexal.com
MAIL_PORT=587
MAIL_USERNAME=os@idexal.com
MAIL_PASSWORD=replace-with-mail-password
MAIL_FROM_ADDRESS=os@idexal.com
MAIL_FROM_NAME="${APP_NAME}"

IDEXAL_DESKTOP_SCHEME=idexalos
IDEXAL_HANDOFF_TTL=10
IDEXAL_DESKTOP_TOKEN_DAYS=365
IDEXAL_SUPPORT_EMAIL=os@idexal.com
IDEXAL_INFO_EMAIL=info@idexal.com
IDEXAL_ALLOW_REGISTRATION=true
IDEXAL_REQUIRE_EMAIL_VERIFICATION=false

STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=

ADMIN_NAME="Platform Admin"
ADMIN_EMAIL=admin@idexal.com
ADMIN_PASSWORD=replace-with-a-long-random-password
```

Generate the key once:

```bash
php artisan key:generate
```

Important:

- `APP_URL` must be exactly `https://os.idexal.com` (no trailing slash). The
  desktop handoff deep link uses it as `denBaseUrl`.
- Keep `APP_DEBUG=false` in production.
- Set `IDEXAL_REQUIRE_EMAIL_VERIFICATION=true` only after outgoing mail is
  working. Otherwise new users cannot reach the dashboard.
- Set `SESSION_SECURE_COOKIE=true` only when HTTPS is active.

### 3.4 Database options

#### Option A — Fresh install (recommended)

```bash
php artisan migrate --force
php artisan db:seed --force
```

This creates the schema, the Free/Starter/Pro/Business plans, the inactive AI
provider catalog, and the administrator account.

#### Option B — Import an existing database

Export the local database:

```bash
mysqldump -u root idexal_os > database/idexal_os.sql
```

Import it on the server with phpMyAdmin or:

```bash
mysql -u cpanel_user_idexal -p cpanel_user_idexal_os < database/idexal_os.sql
```

After importing:

```bash
php artisan migrate --force
php artisan db:seed --class=PlanSeeder --force
php artisan db:seed --class=AiCatalogSeeder --force
```

Do not run `migrate:fresh` on production; it deletes all data.

If the imported administrator password is the development default, change it
immediately, or create a new administrator and remove the old one.

### 3.5 Finish installation

```bash
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Set writable permissions on:

```text
storage/
bootstrap/cache/
```

On shared hosting, `755` for directories and `644` for files is usually enough.
If the host runs PHP as a different user, use its documented permission method.

### 3.6 Scheduler and queues

The application uses scheduled commands and database queues for notifications,
webhook retries, and log retention.

Add this cron entry:

```cron
* * * * * cd /home/USER/domains/os.idexal.com/website-laravel-idexalOs && php artisan schedule:run >> /dev/null 2>&1
```

Run a queue worker under Supervisor:

```ini
[program:idexal-os-worker]
command=php /home/USER/domains/os.idexal.com/website-laravel-idexalOs/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/home/USER/domains/os.idexal.com/website-laravel-idexalOs
autostart=true
autorestart=true
user=USER
numprocs=1
redirect_stderr=true
stdout_logfile=/home/USER/domains/os.idexal.com/website-laravel-idexalOs/storage/logs/worker.log
```

If Supervisor is unavailable, use cPanel's cron to run the worker every five
minutes, or run `QUEUE_CONNECTION=sync` only for a very small installation.
Database queues are recommended.

### 3.7 Domain and HTTPS

1. Point `os.idexal.com` to the server:
   - `A` record to the server IPv4 address, and/or
   - `AAAA` record to the IPv6 address.
2. Issue a TLS certificate (Let's Encrypt or the host's AutoSSL).
3. Force HTTPS at the web server or CDN.
4. Confirm `https://os.idexal.com` loads without certificate warnings.
5. Set `APP_URL=https://os.idexal.com` and clear the config cache after any
   domain change.

### 3.8 Stripe

Stripe is optional. Without Stripe, invoices can still be created and marked as
paid manually by an administrator.

1. Create products and recurring prices in Stripe for each paid plan.
2. Add the Price IDs to the plans in `/admin/plans`.
3. Set `STRIPE_KEY` and `STRIPE_SECRET` in `.env` or in `/admin/settings`.
4. Add the webhook endpoint in Stripe:

   ```text
   https://os.idexal.com/webhooks/stripe
   ```

5. Subscribe to these events:

   - `checkout.session.completed`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.paid`
   - `invoice.payment_failed`

6. Copy the signing secret into `STRIPE_WEBHOOK_SECRET` or `/admin/settings`.
7. Run a Stripe test-mode checkout before enabling live keys.

Stripe webhook events are idempotent. A repeated event returns
`{"received":true,"duplicate":true}` without processing twice.

### 3.9 AI gateway

The seeded providers are inactive until you add credentials.

1. Sign in as an administrator.
2. Open `/admin/providers`.
3. Edit OpenAI, Anthropic, Google, or create an OpenAI-compatible provider.
4. Enter the base URL and API key, add the model IDs, and activate the provider
   and models.
5. Use the provider test button to verify the credentials.
6. Open `/admin/gateway` to monitor requests, tokens, and errors.

The gateway exposes:

```text
POST /v1/chat/completions   OpenAI-compatible
POST /v1/messages           Anthropic-compatible
GET  /v1/models
GET  /v1/gateway/usage-limits/me
```

Prompts are not stored. Usage rows store token counts, model, status, latency,
and cost only.

### 3.10 Desktop sign-in verification

1. Install the idexal Os desktop app so the `idexalos://` protocol is
   registered.
2. Open this URL in a browser:

   ```text
   https://os.idexal.com/?mode=sign-in&desktopAuth=1&desktopScheme=idexalos
   ```

3. Sign in if prompted.
4. Choose a workspace and authorize the device.
5. Click **Open idexal Os**.
6. The browser opens a link shaped like:

   ```text
   idexalos://den-auth?grant=dh_...&denBaseUrl=https%3A%2F%2Fos.idexal.com
   ```

7. The desktop app exchanges the single-use grant at
   `POST /v1/auth/desktop-handoff/exchange`.

Grants expire after `IDEXAL_HANDOFF_TTL` minutes (default 10) and can be used
once. If the app does not open, copy the full link and paste it into the app's
manual sign-in field.

The desktop app works without this account. Signing in only enables optional
cloud surfaces; local files, local models, and local providers remain available.

---

## 4. Post-deployment checklist

- [ ] `https://os.idexal.com` loads over HTTPS.
- [ ] `/login`, `/register`, `/pricing`, `/docs` return 200.
- [ ] `admin@idexal.com` can sign in and the default password has been changed.
- [ ] `/admin` loads for the administrator and returns 403 for a normal user.
- [ ] New registration creates a personal workspace and API key page.
- [ ] `/v1/app-version` returns JSON with `latestAppVersion`.
- [ ] `/v1/features` returns JSON.
- [ ] Desktop handoff issues a link and the link opens the app.
- [ ] A second exchange of the same grant returns `410 Gone`.
- [ ] A test invoice PDF downloads with Arabic text.
- [ ] Stripe webhook returns 400 for an invalid signature, not 419.
- [ ] Scheduler and queue worker are running.
- [ ] `storage/logs/laravel.log` has no recurring errors.

---

## 5. Troubleshooting

### 500 error after upload

- Confirm the document root is `public`.
- Confirm `storage/` and `bootstrap/cache/` are writable.
- Check `storage/logs/laravel.log`.
- Run `php artisan optimize:clear` and reload.

### 419 Page Expired

- Confirm `SESSION_DRIVER=database` and the sessions table exists.
- Confirm the domain in the browser exactly matches `APP_URL`.
- Clear cookies and `php artisan optimize:clear`.
- If HTTPS is active, set `SESSION_SECURE_COOKIE=true`.

### API returns 404

This application uses no `/api` prefix for `/v1` routes. Call:

```text
https://os.idexal.com/v1/me
```

not `/api/v1/me`.

### Desktop app says the link is expired

- Grants expire after 10 minutes.
- Generate a new link from the dashboard: **Desktop app → Connect**.
- Check that the server clock is correct.

### Desktop app never opens

- Confirm the app is installed and registers `idexalos://`.
- On Windows, test the protocol from PowerShell:

  ```powershell
  Start-Process "idexalos://den-auth?grant=test&denBaseUrl=https%3A%2F%2Fos.idexal.com"
  ```

- In the browser, copy the link and paste it into the app's manual sign-in field.

### Arabic invoice PDF is blank or shows squares

- Ensure `mbstring`, `gd`, and `xml` are enabled.
- Ensure `storage/app/mpdf` exists and is writable.
- Clear the view cache: `php artisan view:clear`.

### Assets do not load

- Confirm `public/build/manifest.json` exists.
- Run `npm ci && npm run build`.
- Run `php artisan optimize:clear`.

### Emails do not arrive

- Set `MAIL_MAILER=smtp` and valid SMTP credentials.
- If email verification is enabled, users are blocked until mail works.
- Use `/admin/settings` to inspect the support email, but SMTP credentials live
  in `.env`.

---

## 6. Security checklist

- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` is set and backed up
- [ ] Database password is strong and not the XAMPP default
- [ ] Administrator password changed from the seeded value
- [ ] `.env` is outside the public web root
- [ ] HTTPS is forced
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] Stripe webhook signature verification is enabled
- [ ] Database and `.env` backups are scheduled
- [ ] `storage/logs` are rotated
- [ ] Composer dependencies are updated with `composer update --no-dev` during a
      maintenance window

---

## 7. Useful commands

```bash
php artisan about
php artisan route:list --except-vendor
php artisan migrate:status
php artisan optimize:clear
php artisan optimize
php artisan queue:restart
php artisan schedule:list
php artisan test
```

To deploy an update:

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```
