#!/bin/bash

echo "=========================================="
echo "   Testing idexal Os Platform"
echo "=========================================="
echo ""

BASE_URL="${1:-http://localhost:8000}"

# Test 1: Main homepage
echo "Test 1: Homepage..."
curl -sI "$BASE_URL" | head -1

# Test 2: Admin login page
echo "Test 2: Admin login..."
curl -sI "$BASE_URL/admin/login" | head -1

# Test 3: Provider management
echo "Test 3: Provider keys page..."
curl -sI "$BASE_URL/admin/providers/1/keys" | head -1

# Test 4: Health check endpoint
echo "Test 4: Health check..."
curl -s "$BASE_URL/api/health" | python3 -m json.tool 2>/dev/null || curl -s "$BASE_URL/api/health"

# Test 5: Check if multi-key service exists
echo ""
echo "Checking services..."
if [ -f "website-laravel-idexalOs/app/Services/ProviderKeyRotationService.php" ]; then
    echo "✅ ProviderKeyRotationService EXISTS"
else
    echo "❌ ProviderKeyRotationService MISSING"
fi

if [ -f "website-laravel-idexalOs/app/Services/SmartRouterService.php" ]; then
    echo "✅ SmartRouterService EXISTS"
else
    echo "❌ SmartRouterService MISSING"
fi

if [ -f "website-laravel-idexalOs/app/Services/PaymentGatewayService.php" ]; then
    echo "✅ PaymentGatewayService EXISTS"
else
    echo "❌ PaymentGatewayService MISSING"
fi

if [ -f "website-laravel-idexalOs/app/Services/AffiliateSystemService.php" ]; then
    echo "✅ AffiliateSystemService EXISTS"
else
    echo "❌ AffiliateSystemService MISSING"
fi

if [ -f "website-laravel-idexalOs/app/Services/InviteSystemService.php" ]; then
    echo "✅ InviteSystemService EXISTS"
else
    echo "❌ InviteSystemService MISSING"
fi

echo ""
echo "=========================================="
echo "Testing Complete!"
echo "=========================================="
