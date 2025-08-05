#!/bin/bash

# Laravel Horizon Deployment Script
# This script handles the deployment of Horizon in production

echo "🚀 Starting Horizon deployment..."

# Terminate existing Horizon processes gracefully
echo "📋 Terminating existing Horizon processes..."
php artisan horizon:terminate

# Wait for processes to terminate
echo "⏳ Waiting for processes to terminate..."
sleep 10

# Clear and cache configuration
echo "🔧 Clearing and caching configuration..."
php artisan config:clear
php artisan config:cache

# Clear and cache routes
echo "🛣️ Clearing and caching routes..."
php artisan route:clear
php artisan route:cache

# Clear and cache views
echo "👁️ Clearing and caching views..."
php artisan view:clear
php artisan view:cache

# Optimize autoloader
echo "⚡ Optimizing autoloader..."
composer dump-autoload --optimize

# Restart Horizon
echo "🔄 Starting Horizon..."
php artisan horizon &

# Check if Horizon is running
sleep 5
if pgrep -f "artisan horizon" > /dev/null; then
    echo "✅ Horizon is running successfully!"
    php artisan horizon:status
else
    echo "❌ Failed to start Horizon!"
    exit 1
fi

echo "🎉 Horizon deployment completed successfully!"
