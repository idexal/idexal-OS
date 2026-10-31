=======================================
idexal Os Production Server - Setup Guide
=======================================

Server: os.idexal.com (91.98.179.77)
Plesk Panel: https://panel.yahosti.com:8443
FTP User: idexalcomis
Database: idexal_os

---------------------------------------
STEP 1: Create .env file on server
---------------------------------------

Path: /var/www/os.idexal.com/public_html/.env

Copy paste this EXACTLY (no modifications):

```
APP_NAME="idexal Os"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://os.idexal.com
APP_TIMEZONE=UTC
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=idexal_os
DB_USERNAME=idexal
# NOTE: Set DB_PASSWORD via Plesk panel or your MySQL credentials

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=os.idexal.com
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="os@idexal.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"

IDEXAL_DESKTOP_SCHEME=idexalos
IDEXAL_HANDOFF_TTL=10
IDEXAL_DESKTOP_TOKEN_DAYS=365
IDEXAL_SUPPORT_EMAIL=os@idexal.com
IDEXAL_INFO_EMAIL=info@idexal.com
IDEXAL_ALLOW_REGISTRATION=true
IDEXAL_REQUIRE_EMAIL_VERIFICATION=false
IDEXAL_CURRENCY=USD
IDEXAL_INVOICE_PREFIX=IXO
IDEXAL_TAX_RATE=0
IDEXAL_GRACE_DAYS=3
IDEXAL_TRIAL_DAYS=14
IDEXAL_GATEWAY_ENABLED=true
IDEXAL_GATEWAY_TIMEOUT=180
IDEXAL_GATEWAY_STREAM_TIMEOUT=600
IDEXAL_GATEWAY_LOG_RETENTION_DAYS=90
IDEXAL_GATEWAY_RATE_LIMIT=120
IDEXAL_GATEWAY_MAX_BODY_KB=1024

STRIPE_KEY=your_stripe_key_here
STRIPE_SECRET=your_stripe_secret_here
STRIPE_WEBHOOK_SECRET=your_webhook_secret_here

ADMIN_NAME="Platform Admin"
ADMIN_EMAIL=admin@idexal.com

GITHUB_TOKEN=your_github_token_here

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAXmind_LICENSE_KEY=

CLOUD_SIGN_IN_ENABLED=false
CLOUD_ENABLED=false
CLOUD_API_URL=https://api.idexal.com/api/v1/idexal-os-cloud-provider-sign-in
```

---------------------------------------
STEP 2: Generate Laravel App Key
---------------------------------------

SSH into server and run:
cd /var/www/os.idexal.com/public_html
php artisan key:generate --force

Or skip if you don't want to SSH yet - will prompt on first visit

---------------------------------------
STEP 3: Create Required Database Tables
---------------------------------------

Go to Plesk → Databases → idexal_os → phpMyAdmin

Run these SQL queries sequentially in the "SQL" tab:

**Query 1: Extension Installations Table**
```sql
CREATE TABLE IF NOT EXISTS extension_installations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    extension_name VARCHAR(255) NOT NULL,
    extension_type ENUM('connector', 'skill', 'plugin') NOT NULL,
    version VARCHAR(50) DEFAULT '1.0.0',
    source TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_extension (user_id, extension_name, extension_type),
    CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Query 2: Queue Jobs Table**
```sql
CREATE TABLE jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue VARCHAR(191) NOT NULL,
    payload LONGTEXT NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    available_at INT UNSIGNED NOT NULL,
    reserved_at INT UNSIGNED DEFAULT NULL,
    INDEX(available_at),
    INDEX(reserved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(191) NOT NULL,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY failed_jobs_uuid_unique (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Query 3: Cache Table**
```sql
CREATE TABLE cache (
    key VARCHAR(255) NOT NULL PRIMARY KEY,
    value MEDIUMTEXT NOT NULL,
    expiration INTEGER NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Query 4: Sessions Table**
```sql
CREATE TABLE sessions (
    id VARCHAR(255) NOT NULL PRIMARY KEY,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    payload LONGTEXT NOT NULL,
    last_activity INTEGER NOT NULL,
    INDEX sessions_user_id_idx(user_id),
    INDEX sessions_last_activity_idx(last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---------------------------------------
STEP 4: Set Permissions
---------------------------------------

Via SSH or Plesk File Manager:
chown -R www-data:www-data /var/www/os.idexal.com/public_html/storage/
chown -R www-data:www-data /var/www/os.idexal.com/public_html/bootstrap/cache/
chmod -R 775 /var/www/os.idexal.com/public_html/storage/
chmod -R 775 /var/www/os.idexal.com/public_html/bootstrap/cache/

---------------------------------------
STEP 5: Optimize & Clear Cache
---------------------------------------

Via SSH:
cd /var/www/os.idexal.com/public_html

php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan optimize --classmap

---------------------------------------
STEP 6: Start Queue Worker (for broadcasting)
---------------------------------------

Via SSH or create cron job:
php artisan queue:work --timeout=60 --tries=3

OR add to crontab (/etc/cron.d/laravel):
* * * * * cd /var/www/os.idexal.com/public_html && php artisan queue:work >> /dev/null 2>&1

---------------------------------------
STEP 7: Verify Installation
---------------------------------------

Visit these URLs:

1. Main site: https://os.idexal.com
   ✅ Should show homepage

2. Admin login: https://os.idexal.com/admin/login
   ✅ Should show admin login form

3. Dashboard: https://os.idexal.com/admin/dashboard
   ✅ Should show dashboard with stats

4. Providers: https://os.idexal.com/admin/providers
   ✅ Should list providers

5. **Combos (NEW):** https://os.idexal.com/admin/combos
   ✅ Should show combo page with NEW buttons:
      - "AI Suggestions" button
      - "Extensions Marketplace" button
   
6. **Combo Suggestions (NEW):** https://os.idexal.com/admin/combos/suggest
   ✅ Should show intelligent combo suggestions UI

7. **Extensions Marketplace (NEW):** https://os.idexal.com/admin/extensions
   ✅ Should show tabbed interface (All/Connectors/Skills/Plugins)

---------------------------------------
STEP 8: Test New Features
---------------------------------------

**Test Sync:**
1. Go to https://os.idexal.com/admin/providers/create
2. Add a test provider
3. Check logs: tail -f storage/logs/laravel.log
4. You should see broadcast() calls in log

**Test Combo Creation:**
1. Visit https://os.idexal.com/admin/combos/suggest
2. Select any suggested combo
3. Click "Use This Suggestion"
4. Verify combo appears in main combos list

**Test Extensions Marketplace:**
1. Visit https://os.idexal.com/admin/extensions
2. Switch between tabs
3. Click "Install" on any extension
4. Verify success message
5. Check database: SELECT * FROM extension_installations;

**Test Number Display:**
1. Visit any admin page
2. Verify ALL numbers display as: 123456789 (NOT Arabic numerals ١٢٣٤٥٦٧٨٩)
3. Money amounts: $1,234.50 (not $١٬٢٣٤٫٥٠)

---------------------------------------
TROUBLESHOOTING
---------------------------------------

If errors appear:

1. "Class not found"
   - Run: composer dump-autoload --optimize
   - Or upload composer.phar and run locally

2. Permission denied on storage/
   - chmod -R 775 storage/ bootstrap/cache/

3. "Broadcast events not working"
   - Ensure queue worker running
   - Start manually: php artisan queue:work

4. Marketplace shows empty
   - Check GITHUB_TOKEN is correct in .env
   - Review logs for GitHub API errors
   - Manual refresh: ?tab=all&q=

5. Still seeing Arabic numbers
   - Hard refresh browser (Ctrl+Shift+R)
   - Check .env has APP_LOCALE=en
   - Clear view cache

---------------------------------------
FINAL CHECKLIST
---------------------------------------

Before going live:
☐ HTTPS enabled (SSL certificate configured)
☐ All tables created in database
☐ All numbers display correctly (Western numerals only)
☐ Combo suggestions page loads
☐ Extensions marketplace functional
☐ No errors in logs
☐ Queue worker running
☐ Backup completed
☐ Team notified of new features

=======================================
Congratulations! Platform ready! 🎉
=======================================

New features accessible at:
- /admin/combos (with AI suggestions)
- /admin/combos/suggest (advanced creation)
- /admin/extensions (full marketplace)

Synchronization active! ✅