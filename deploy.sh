#!/bin/bash

# Exit immediately if a command exits with a non-zero status.
set -e

# --- CONFIGURATION ---
PROJECT_PATH="/var/www/social-downloader"
GIT_BRANCH="develop"
# --- END CONFIGURATION ---

# Helper function for logging
print_info() {
    echo -e "\n\e[1;34m[DEPLOY]\e[0m $1"
}

print_info "Starting the deployment process..."

# Navigate to the project directory
cd $PROJECT_PATH

# Enable maintenance mode
print_info "Enabling Maintenance Mode..."
php artisan down

# Pull the latest code
print_info "Pulling the latest code from branch '${GIT_BRANCH}'..."
git stash
git pull origin $GIT_BRANCH
git stash pop

# Install Composer dependencies
print_info "Updating Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# --- NEW STEPS ADDED HERE ---
# Install NPM dependencies
print_info "Installing NPM dependencies..."
npm install

# Build front-end assets
print_info "Building front-end assets..."
npm run build
# --- END OF NEW STEPS ---

# Run database migrations
print_info "Running Database Migrations..."
php artisan migrate --force

# Clear and rebuild caches
print_info "Clearing and caching configuration, routes, and views..."
php artisan optimize:clear

# Restart queue workers
print_info "Restarting Queue Workers (Supervisor)..."
sudo supervisorctl restart all

# Disable maintenance mode
print_info "Disabling maintenance mode. Application is back online!"
php artisan up

print_info "\e[1;32m✅ DEPLOYMENT COMPLETE! ✅\e[0m"
