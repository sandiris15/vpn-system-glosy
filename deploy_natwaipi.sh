#!/bin/bash
# Deployment script for Ruang NOC NATWAIPI on CT 102

set -e

echo "════════════════════════════════════════════════════════════════"
echo "🚀 Deploying Ruang NOC NATWAIPI to CT 102"
echo "════════════════════════════════════════════════════════════════"
echo ""

cd /var/www/vpn-management

echo "📥 Step 1: Pulling latest code from GitHub..."
git pull origin main

echo ""
echo "📦 Step 2: Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

echo ""
echo "📦 Step 3: Installing Node dependencies..."
npm ci

echo ""
echo "🔨 Step 4: Building frontend assets..."
npm run build

echo ""
echo "🔄 Step 5: Running database migrations..."
php artisan migrate --force

echo ""
echo "⚙️  Step 6: Clearing and caching configuration..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "✅ DEPLOYMENT COMPLETE!"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "📍 Verification:"
echo "   APP_NAME: $(php artisan tinker --execute "echo config('app.name')" 2>/dev/null || grep APP_NAME .env | cut -d'=' -f2)"
echo ""
echo "🌐 Application is ready at: https://your-domain"
echo ""

