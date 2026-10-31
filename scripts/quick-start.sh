#!/bin/bash

#############################################
# Quick Start Guide - idexal Os Platform
# Fast setup instructions for immediate use
#############################################

# Prerequisites:
# 1. SSH access to server (idexalcomis@91.98.179.77)
# 2. Plesk panel access (https://panel.yahosti.com:8443)
# 3. Database credentials from Plesk

echo "=============================================="
echo "   idexal Os - Quick Start Setup"
echo "=============================================="
echo ""
echo "Choose your deployment method:"
echo "  1) Automated Script (Recommended)"
echo "  2) Manual Setup"
echo "  3) Show me the documentation"
echo ""

read -p "Enter choice [1-3]: " choice

case $choice in
    1)
        echo "Starting automated deployment..."
        
        # Run deploy script if it exists
        if [ -f "scripts/deploy-production.sh" ]; then
            sudo bash scripts/deploy-production.sh
        else
            echo "Deploy script not found! See manual setup below."
        fi
        ;;
        
    2)
        echo ""
        echo "===== MANUAL SETUP STEPS ====="
        echo ""
        echo "STEP 1: Access your server via SSH"
        echo "  ssh idexalcomis@91.98.179.77"
        echo ""
        echo "STEP 2: Navigate to Laravel directory"
        echo "  cd /var/www/os.idexal.com/public_html"
        echo ""
        echo "STEP 3: Run database migrations"
        echo "  php artisan migrate"
        echo ""
        echo "STEP 4: Clear caches"
        echo "  php artisan config:clear"
        echo "  php artisan cache:clear"
        echo "  php artisan view:clear"
        echo ""
        echo "STEP 5: Set permissions"
        echo "  chmod -R 775 storage bootstrap/cache"
        echo "  chown -R www-data:www-data storage bootstrap/cache"
        echo ""
        echo "STEP 6: Start health monitor (background)"
        echo "  nohup php artisan providers:health-checks --interval=60 > /var/log/provider-health.log 2>&1 &"
        echo ""
        echo "STEP 7: Verify everything is working"
        echo "  mysql -u idexal idexal_os -e \"SHOW TABLES LIKE '%provider%'\""
        echo ""
        echo "STEP 8: Add backup API keys"
        echo "  Visit: https://os.idexal.com/admin/providers/{id}/keys"
        echo "  Click 'Add New API Key' for each provider"
        echo ""
        echo "===== SETUP COMPLETE ====="
        ;;
        
    3)
        echo ""
        echo "===== DOCUMENTATION FILES ====="
        echo ""
        echo "Main Documentation:"
        echo "  - FINAL_SUMMARY_COMPLETE.md      : Complete feature summary"
        echo "  - PLATFORM_OVERHAUL_SUMMARY.md   : Technical implementation details"
        echo "  - CRITICAL_FIXES_PLAN.md         : Original fix plan"
        echo ""
        echo "Setup Guides:"
        echo "  - DEPLOYMENT_CHECKLIST.md        : Step-by-step deployment checklist"
        echo "  - TESTING_GUIDE.md              : Comprehensive testing guide"
        echo "  - REBRANDING_IMPLEMENTATION.md   : Desktop rebranding guide"
        echo ""
        echo "Arabic Documentation:"
        echo "  - NARATIVE_SUMMARY_ARABIC.md     : الملخص الشامل بالعربية"
        echo ""
        ;;
        
    *)
        echo "Invalid choice!"
        exit 1
        ;;
esac

echo ""
echo "For additional help, contact: os@idexal.com"
echo ""
