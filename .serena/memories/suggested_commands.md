# Suggested Commands

## Development Commands

### Laravel Artisan
```bash
# Start development server with all services
composer dev

# Individual services
php artisan serve                    # Web server
php artisan queue:listen --tries=1  # Queue worker
php artisan pail --timeout=0        # Log viewer

# Database operations
php artisan migrate                  # Run migrations
php artisan migrate:fresh --seed    # Fresh database with seeders
php artisan db:seed                  # Run seeders only

# Cache operations
php artisan config:clear             # Clear config cache
php artisan cache:clear              # Clear application cache
php artisan route:clear              # Clear route cache
php artisan view:clear               # Clear view cache
```

### Testing
```bash
# Run all tests
composer test
php artisan test

# Run specific test suites
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage
```

### Code Quality
```bash
# Format code with Laravel Pint
./vendor/bin/pint

# Check code style
./vendor/bin/pint --test
```

### Frontend Development
```bash
# Install dependencies
npm install

# Development build
npm run dev

# Production build
npm run build

# Filament-specific builds
npm run dev:filament
npm run build:filament
```

### Filament Commands
```bash
# Create resources
php artisan make:filament-resource ModelName
php artisan make:filament-resource ModelName --simple

# Upgrade Filament
php artisan filament:upgrade
```

### Custom Commands
```bash
# Test video extraction
php artisan test:video-extraction
php artisan test:instagram-extraction
php artisan test:yt-dlp-service

# Test API setup
php artisan verify:api-setup
php artisan test:api-key-authentication

# File management
php artisan process:scheduled-file-deletions
```

## System Commands (macOS)
```bash
# File operations
ls -la                    # List files with details
find . -name "*.php"      # Find PHP files
grep -r "pattern" .       # Search in files

# Process management
ps aux | grep php         # Find PHP processes
kill -9 <pid>            # Kill process by ID

# Git operations
git status               # Check repository status
git add .                # Stage all changes
git commit -m "message"  # Commit changes
git push origin branch   # Push to remote
```