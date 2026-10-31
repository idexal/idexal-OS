#!/bin/bash

# Script to build desktop installer for idexal Os
# Run this on YOUR computer (Windows/Mac/Linux)

set -e

echo "=========================================="
echo "  Building idexal Os Desktop Installer"
echo "=========================================="
echo ""

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "❌ Node.js not found. Please install Node.js 18+ first."
    exit 1
fi

# Check if pnpm is installed  
if ! command -v pnpm &> /dev/null; then
    echo "⚠️  pnpm not found, installing..."
    npm install -g pnpm
fi

cd apps/desktop

echo "📦 Installing dependencies..."
pnpm install

echo "🔧 Building application..."
pnpm run build

echo "📦 Packaging installer..."
pnpm run package:electron

echo ""
echo "✅ Build Complete!"
echo ""
echo "Installer location:"
find dist-electron-* -name "*.exe" -o -name "*.dmg" -o -name "*.AppImage" | head -5

echo ""
echo "Next steps:"
echo "1. Find the generated .exe/.dmg file above"
echo "2. Install it on your computer"
echo "3. Launch the app and test login to https://os.idexal.com"
echo ""
echo "For testing on localhost:"
echo "1. Start web platform: cd ../website-laravel-idexalOs && php artisan serve --host=0.0.0.0 --port=8000"
echo "2. Update .env in desktop app with APP_URL=http://localhost:8000"
echo "3. Login and test sync functionality"
