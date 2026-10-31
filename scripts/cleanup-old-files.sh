#!/bin/bash

# idexal Os - Cleanup Script
# Removes old build artifacts and keeps source code clean

set -e

echo "🧹 Cleaning up old files..."

# Find and list old dist-electron folders
OLD_DISTS=$(find apps/desktop -type d -name "dist-electron-*" 2>/dev/null | wc -l)
echo "Found $OLD_DIST dist-electron folders to remove"

# Remove old electron builds
rm -rf apps/desktop/dist-electron-*

# Remove any other old build folders
rm -rf apps/app/dist/
rm -rf apps/server/dist/
rm -rf packages/*/dist/

# Clean node modules cache (keep actual modules)
rm -rf .pnpm-store/* 2>/dev/null || true

# Clear npm cache quietly
npm cache clean --force 2>/dev/null || true

echo "✅ Cleanup complete!"
echo ""
echo "Files removed:"
echo "  - Old electron build folders ($OLD_DIST folders)"
echo "  - Old app dist folders"
echo "  - Pnpm store cache"
echo "  - NPM cache"
echo ""
echo "Source code structure preserved:"
echo "  - All TypeScript/JavaScript source files"
echo "  - Configuration files (.json, .yml, .env.example)"
echo "  - Documentation files (README.md, *.md)"
echo "  - Scripts and build configs"
echo ""
echo "Ready to rebuild fresh on next deployment!"
