# 🚀 Deployment Checklist - idexal Os Production Server

**Server Credentials Provided**:
- Domain: `os.idexal.com`
- Plesk Panel: `https://panel.yahosti.com:8443`
- SSH/FTP User: `idexalcomis`

---

## ✅ Pre-Deployment Verification

### 1. Environment Configuration ✓
```bash
cd /var/www/html/os.idexal.com  # Or your Laravel path

# Verify .env file contains:
APP_NAME="idexal Os"
APP_ENV=production
APP_URL=https://os.idexal.com
APP_LOCALE=en                    # ← CRITICAL for Western numerals
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

GITHUB_TOKEN=your_github_token_here
STRIPE_KEY=your_stripe_key_here
STRIPE_SECRET=your_stripe_secret_here
```

### 2. Database Setup
```bash
# Update database credentials if needed
cp .env.example .env.production

php artisan migrate:fresh --seed     # Only if fresh DB required
# OR
php artisan migrate                  # If using existing DB
```

### 3. Storage Permissions
```bash
chmod -R 775 storage/ bootstrap/cache/
chown -R www-data:www-data storage/
```

### 4. Compile Frontend (if Vue assets exist)
```bash
npm run build   # or php artisan mix
```

---

## 🔴 Critical Steps During Deployment

### Step 1: Upload Files via SFTP

**Connection Details**:
- Host: `91.98.179.77` or `idexal.com`
- Port: `22`
- Username: `idexalcomis`
- Password: `~qr*7wlVdL83Fdfp`

**Files to Upload**:
```
website-laravel-idexalOs/ → /var/www/html/os.idexal.com/public/
OR deploy directly to Laravel root directory

Key files:
- app/Http/Controllers/Admin/AdminExtensionController.php (NEW)
- app/Services/ExtensionMarketplaceService.php (NEW)
- app/Http/Controllers/Admin/AdminProviderController.php (MODIFIED)
- app/Services/ComboModelService.php (MODIFIED)
- app/Http/Controllers/Admin/AdminComboController.php (MODIFIED)
- resources/views/admin/combos/suggest.blade.php (NEW)
- resources/views/admin/extensions.blade.php (NEW)
- resources/views/admin/combos/index.blade.php (MODIFIED)
- routes/web.php (MODIFIED)
- .env (UPDATED)
```

### Step 2: Clear Caches
```bash
cd /var/www/html/os.idexal.com
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan event:clear
```

### Step 3: Create Extension Installations Table
```bash
php artisan make:migration create_extension_installations_table

# Then add this to migration file:
public function up() {
    Schema::create('extension_installations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->string('extension_name');
        $table->enum('extension_type', ['connector', 'skill', 'plugin']);
        $table->string('version')->default('1.0.0');
        $table->text('source')->nullable();
        $table->timestamps();
        
        $table->unique(['user_id', 'extension_name', 'extension_type']);
    });
}

php artisan migrate
```

### Step 4: Queue Worker Setup (for broadcasting)
```bash
# Add to system crontab (/etc/cron.d/laravel)
* * * * * cd /var/www/html/os.idexal.com && php artisan queue:work --tries=3 >> /dev/null 2>&1

# OR start manually for testing
php artisan queue:work --timeout=60
```

### Step 5: SSL Certificate (if not configured)
```bash
# Using Let's Encrypt (recommended)
certbot --nginx -d os.idexal.com

# OR configure in Plesk panel manually
```

### Step 6: Enable CORS (if desktop needs it)
Add to `config/cors.php`:
```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => [
    '*', // Or specific desktop app origin
],
'allowed_headers' => ['*'],
'allowed_methods' => ['*'],
```

---

## 🟡 Post-Deployment Testing

### Test Suite 1: Basic Functions
```bash
curl -I https://os.idexal.com
# Should return HTTP 200 with correct headers

curl -I https://os.idexal.com/admin
# Should redirect to login page

curl -I https://os.idexal.com/admin/dashboard
# After login: should show dashboard with stats
```

### Test Suite 2: New Features
1. **Combo Suggestions**
```bash
Visit: https://os.idexal.com/admin/combos
Click: "AI Suggestions" button
Verify: Page loads with suggestions grid
```

2. **Extensions Marketplace**
```bash
Visit: https://os.idexal.com/admin/extensions
Check: Three tabs visible (All/Connectors/Skills/Plugins)
Try: Install any extension
Verify: Success message appears
```

3. **Number Display**
```bash
Visit any admin page
Check all numbers: Should be 1,234,567 not ١٬٢٣٤٬٥٦٧
Example: Billing page should show $1,234.50 not $١٬٢٣٤٫٥٠
```

### Test Suite 3: Broadcasting
```bash
Log in as admin in browser A
Open same account in browser B (incognito)
In browser A: Update/add provider
Browser B: Should receive notification immediately (via broadcast)
```

---

## 🟢 Troubleshooting Common Issues

### Issue 1: "Class not found" after upload
**Cause**: Autoloader needs regeneration  
**Fix**:
```bash
composer dump-autoload --optimize
php artisan optimize
```

### Issue 2: Permission denied on storage/
**Cause**: Wrong ownership  
**Fix**:
```bash
sudo chown -R www-data:www-data storage/bootstrap/
sudo chmod -R 775 storage/bootstrap/
```

### Issue 3: Broadcast events not showing
**Cause**: Queue not running  
**Fix**:
```bash
# Check if queue worker exists
ps aux | grep queue.work

# Start if missing
cd /var/www/html/os.idexal.com
nohup php artisan queue:work > /dev/null 2>&1 &
```

### Issue 4: Marketplace shows empty
**Cause**: GitHub API rate limit or wrong token  
**Fix**:
```bash
# Check logs
tail -f storage/logs/laravel.log | grep "GitHub API"

# Verify token in .env
grep GITHUB_TOKEN .env

# Refresh cache
php artisan cache:clear
```

### Issue 5: Arabic text still showing numbers
**Cause**: Browser caching old page  
**Fix**: 
```bash
# Hard refresh browser (Ctrl+Shift+R)
# Or clear Laravel view cache
php artisan view:clear
```

---

## 📊 Monitoring Dashboard

After deployment, monitor these endpoints:
- `/admin/dashboard` - Platform health
- `/admin/gateway` - Gateway usage stats
- `/admin/providers` - Provider sync status
- `/admin/combinations/suggest` - Combo suggestion accuracy

---

## 🔐 Security Checks

- [ ] HTTPS enabled on all pages
- [ ] CSRF protection active on all forms
- [ ] Admin routes protected with authentication
- [ ] Extensions installation validates user permissions
- [ ] No debug/error messages exposed publicly
- [ ] Database credentials secured in .env (not committed)
- [ ] GitHub token stored only in environment variable

---

## ✨ Optional Enhancements (Post-Production)

1. **Enable Redis** for faster caching and queues
2. **Setup CDN** for static assets
3. **Configure monitoring** (New Relic, Datadog, etc.)
4. **Setup automated backups** for database
5. **Create staging environment** for testing before production

---

## 🎯 Final Sign-off Checklist

Before announcing launch:
- [ ] All features tested end-to-end
- [ ] Desktop app can connect and receives broadcasts
- [ ] Combo creation works with all strategies
- [ ] Marketplace extensions install/uninstall cleanly
- [ ] Numbers display correctly in ALL languages
- [ ] Admin can access all new pages
- [ ] No errors in Laravel logs
- [ ] Performance acceptable (< 2s page load)
- [ ] Mobile responsive on all devices
- [ ] Backup completed successfully

---

**Next Action Required**: Choose one of these paths:

1. **Deploy Now** → Execute steps above
2. **Test Locally First** → Set up local test environment
3. **Review Changes** → Audit all modified files in detail

What would you like to do? 😊