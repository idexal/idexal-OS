#!/bin/bash

#############################################
# idexal Os Production Deployment Script
# Automated setup for multi-key provider system
#############################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
LARAVEL_PATH="/var/www/os.idexal.com/public_html"
LOG_FILE="/var/log/idexal-os-deploy.log"

# Logging function
log() {
    echo -e "${GREEN}[INFO]${NC} $1" | tee -a "$LOG_FILE"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    error "Please run as root or with sudo"
    exit 1
fi

echo ""
echo "=============================================="
echo "   idexal Os Platform Deployment"
echo "=============================================="
echo ""

# Step 1: Backup existing database
log "Creating database backup..."
BACKUP_NAME="pre_deploy_$(date +%Y%m%d_%H%M%S).sql"
mysqldump -u root -p$idexal_mysql_root_password idexal_os > "/tmp/$BACKUP_NAME" 2>/dev/null || \
mysqldump -u idexal idexal_os > "/tmp/$BACKUP_NAME" 2>/dev/null

if [ -f "/tmp/$BACKUP_NAME" ]; then
    log "Backup created: /tmp/$BACKUP_NAME"
else
    warn "Could not create backup, continuing anyway..."
fi

# Step 2: Update files
log "Updating application files..."
cd "$LARAVEL_PATH" || { error "Cannot access Laravel path"; exit 1; }

# Run migrations
log "Running database migrations..."
php artisan migrate --force

if [ $? -eq 0 ]; then
    log "Database migrations completed successfully!"
else
    error "Migration failed!"
    exit 1
fi

# Clear cache
log "Clearing application cache..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Optimize for production
log "Optimizing for production..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache

# Step 3: Set permissions
log "Setting file permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Step 4: Create supervisor service for health monitoring
log "Configuring health monitor service..."

SUPERVISOR_CONF="/etc/supervisor/conf.d/provider-health-monitor.conf"

cat > "$SUPERVISOR_CONF" << EOF
[program:provider-health-monitor]
command=/usr/bin/php /var/www/os.idexal.com/public_html/artisan providers:health-checks --interval=60
directory=$LARAVEL_PATH
user=www-data
autostart=true
autorestart=true
stderr_logfile=/var/log/provider-health-err.log
stdout_logfile=/var/log/provider-health-out.log
environment=LARAVEL_ENV="production"
EOF

log "Starting health monitor service..."
supervisorctl reread
supervisorctl update
supervisorctl start provider-health-monitor

# Step 5: Verify setup
log "Verifying deployment..."

# Check if tables exist
TABLE_CHECK=$(mysql -u idexal -pidexal_database_password idexal_os -e "SHOW TABLES LIKE 'provider_api_keys';" 2>/dev/null)
if [ ! -z "$TABLE_CHECK" ]; then
    log "✓ Database tables created successfully"
else
    error "✗ Database migration may have failed"
    exit 1
fi

# Check if health monitor is running
MONITOR_STATUS=$(supervisorctl status provider-health-monitor | grep RUNNING)
if [ -n "$MONITOR_STATUS" ]; then
    log "✓ Health monitor service is running"
else
    warn "✗ Health monitor not running, check logs"
fi

# Final summary
echo ""
echo "=============================================="
echo "   ✅ Deployment Complete!"
echo "=============================================="
echo ""
echo "Deployment Summary:"
echo "  - Database updated with new tables"
echo "  - Files optimized for production"
echo "  - Health monitor started (runs every 60s)"
echo "  - Logs available at: $LOG_FILE"
echo ""
echo "Next Steps:"
echo "  1. Visit: https://os.idexal.com/admin/providers/{id}/keys"
echo "  2. Add backup API keys for each provider"
echo "  3. Monitor health at: tail -f /var/log/provider-health-out.log"
echo "  4. Test failover by disabling primary key temporarily"
echo ""
echo "For support or issues: os@idexal.com"
echo ""
