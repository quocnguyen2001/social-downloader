# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 12 application that provides social media video downloading capabilities via API. It supports YouTube, TikTok, Instagram, and Facebook video extraction using yt-dlp as the underlying tool, with a Filament admin panel for management.

## Development Commands

### Setup & Installation
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### Development Server
```bash
# Start all development services (server, queue, logs, vite)
composer dev

# Or start individual services:
php artisan serve          # Start web server
php artisan queue:listen   # Process background jobs
php artisan pail           # View logs
npm run dev               # Start Vite dev server
```

### Testing
```bash
# Run all tests
composer test
# Or: php artisan test

# Run specific test suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Run specific test file
php artisan test tests/Unit/ApiKeyCacheServiceTest.php
```

### Code Quality
```bash
# Format code with Laravel Pint
./vendor/bin/pint

# Format specific files
./vendor/bin/pint app/Services
```

### Queue Management
```bash
# Process queue with retries
php artisan queue:listen --tries=1

# Monitor queue with Horizon (if running)
php artisan horizon
```

### Build Assets
```bash
npm run build              # Production build
npm run build:filament     # Build Filament assets
```

## Core Architecture

### Video Extraction System

The application uses a driver-based architecture for multi-platform video extraction:

**Driver Factory Pattern** (`app/Services/VideoExtraction/Factory/DriverFactory.php`)
- Automatically detects platform from URL
- Returns appropriate driver instance (YouTubeDriver, TikTokDriver, InstagramDriver, FacebookDriver)
- All drivers extend `AbstractDriver` and implement `DriverInterface`

**Extraction Flow:**
1. API request → `VideoExtractionController`
2. Creates `DownloadSession` record
3. Dispatches `ProcessVideoExtraction` job to queue
4. Job uses `DriverFactory` to create platform-specific driver
5. Driver calls `YtDlpService` to extract metadata
6. Results stored in `DownloadSession`, fires `ExtractionCompleted` event
7. Optional: `TriggerVideoDownload` job downloads video file to R2 storage

**Key Services:**
- `YtDlpService`: Wraps yt-dlp binary execution
- `ThumbnailService`: Downloads and stores video thumbnails
- `FileUploadService`: Handles video file uploads to cloud storage (R2)
- `ScheduledFileDeletionService`: Manages temporary file cleanup

### API Authentication & Rate Limiting

**Dual Authentication:**
- **Guest users**: Rate limited via IP (`GuestApiRateLimit` middleware)
- **Authenticated users**: API key authentication via `api_keys` table
  - API key passed in `X-API-Key` header
  - Authenticated via `AuthenticatedApiKey` singleton service
  - Rate limits based on user's membership plan

**API Key Flow:**
1. `ValidateApiRequest` middleware validates headers and content
2. Custom `api.auth` middleware authenticates API key
3. `ApiUsageTracker` service logs usage to `api_requests` table
4. Rate limits enforced via `RateLimitService`

**Key Middleware:**
- `ValidateApiRequest`: Validates headers, content-type, request size
- `SecurityHeaders`: Adds security headers to responses
- `SanitizeInput`: Sanitizes user input to prevent XSS
- `ClearAuthenticatedApiKey`: Clears API key singleton after request

### Filament Admin Panel

Located in `app/Filament/`, provides admin interface for:
- User management (`UserResource`)
- API key management (`ApiKeyResource`)
- Download session tracking (`DownloadSessionResource`)
- API request logs (`ApiRequestResource`)
- Membership plans (`MembershipPlanResource`)
- Subscriptions and transactions

Admin panel accessible at `/admin` route.

### Queue Jobs

All video processing happens asynchronously:
- `ProcessVideoExtraction`: Main job for extracting video metadata
- `ExtractVideoMetadataJob`: Alternative metadata extraction
- `CleanupExtractionData`: Cleanup temporary extraction data
- `RefreshVideoMetadata`: Refresh cached metadata
- `ProcessScheduledFileDeletions`: Delete expired files

Configure queue connection in `config/queue.php`. Default: `database` driver.

### Database Models

**Core Models:**
- `User`: Extends Laravel's base with API key relationship
- `ApiKey`: API keys for authentication (many-to-one with User)
- `DownloadSession`: Tracks each video extraction request
- `DownloadOption`: Available download formats/qualities per session
- `ApiRequest`: Logs all API calls for billing
- `MembershipPlan`, `Subscription`, `Transaction`: Subscription billing
- `ScheduledFileDeletion`: Tracks files pending deletion

### Configuration Files

**`config/video-extraction.php`** - Main video extraction config:
- yt-dlp binary path and options
- FFmpeg settings for video conversion
- Platform-specific driver configuration
- Rate limiting per platform
- Cache, temp files, and security settings

**`config/api_security.php`** - API security settings
**`config/api_rate_limits.php`** - Rate limit configurations
**`config/filesystems.php`** - Cloud storage (R2) configuration

## Common Development Patterns

### Adding a New Platform Driver

1. Create driver class in `app/Services/VideoExtraction/Drivers/`
2. Extend `AbstractDriver`, implement `extractVideoId()` and `getUrlPatterns()`
3. Add platform to `Platform` enum (`app/Enums/Platform.php`)
4. Register in `DriverFactory::registerDefaultDrivers()`
5. Add configuration to `config/video-extraction.php` under `drivers`

### Working with Video Extraction

To test extraction manually:
```bash
php artisan test:video-extraction {url}
php artisan test:instagram-extraction
php artisan test:yt-dlp-service
```

### API Routes Structure

Routes defined in `routes/api.php` under `/api/v1` prefix:
- `/auth/*` - Authentication endpoints (register, login, logout)
- `/extract/*` - Video extraction endpoints
- `/download/*` - File download endpoints
- `/subscriptions/*` - Subscription management

All API routes use `api.auth` middleware group for authentication.

### Events & Listeners

**Key Events:**
- `VideoExtractionRequested`: Fired when extraction is requested
- `ExtractionCompleted`: Fired when extraction succeeds
- `ExtractionFailed`: Fired when extraction fails

**Listeners:**
- `HandleExtractionRequest`: Processes extraction requests

## Environment Variables

Key variables to configure:

```env
# yt-dlp configuration
YT_DLP_BINARY_PATH=/usr/local/bin/yt-dlp
YT_DLP_TIMEOUT=300

# FFmpeg configuration
FFMPEG_BINARY_PATH=ffmpeg
FFMPEG_CONVERSION_ENABLED=true

# Storage (R2)
FILESYSTEM_DISK=r2
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
AWS_URL=
AWS_ENDPOINT=

# Queue
QUEUE_CONNECTION=database

# API Security
API_KEY_HASH_ALGO=sha256
```

## Testing Notes

- Tests use SQLite in-memory database (`:memory:`)
- Queue connection set to `sync` in tests
- Mail driver set to `array` for testing
- Key test coverage:
  - API key authentication flow
  - Video extraction platform detection
  - Rate limiting and usage aggregation
  - Hash-based filename generation

## Laravel Horizon

If using Redis queue driver, monitor queues via Horizon:
```bash
php artisan horizon
```

Access dashboard at `/horizon` route.

## Debugging

View real-time logs:
```bash
php artisan pail --timeout=0
```

Or tail log file:
```bash
tail -f storage/logs/laravel.log
```
