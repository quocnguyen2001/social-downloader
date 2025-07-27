# Codebase Structure

## Directory Organization

### Core Application (`app/`)
- **Models**: User, ApiKey, DownloadSession, DownloadOption, etc.
- **Controllers**: API controllers for video extraction, downloads, auth
- **Services**: Business logic (YtDlpService, FileUploadService, etc.)
- **Jobs**: Background processing (ProcessVideoDownload, ProcessVideoExtraction)
- **Middleware**: API authentication, rate limiting, security headers
- **Enums**: Platform, VideoFormat, DownloadSessionStatus, etc.

### Admin Panel (`app/Filament/`)
- **Resources**: CRUD interfaces for all models
- **Pages**: Custom admin pages and settings
- **Widgets**: Dashboard statistics and charts

### Video Extraction System (`app/Services/VideoExtraction/`)
- **Drivers**: Platform-specific extractors (YouTube, TikTok, Instagram, Facebook)
- **DTOs**: Data transfer objects for extraction results
- **Contracts**: Interfaces for extractors and drivers
- **Exceptions**: Custom exception handling

### Database (`database/`)
- **Migrations**: Schema definitions with UUID primary keys
- **Factories**: Model factories for testing
- **Seeders**: Initial data seeding

### Configuration (`config/`)
- **video-extraction.php**: Video processing settings
- **api_rate_limits.php**: Rate limiting configuration
- **filesystems.php**: Storage disk configuration (local, S3, R2)

## Key Architectural Patterns
- Service layer pattern for business logic
- Repository pattern for data access
- Job queues for background processing
- Enum-based status management
- UUID-based primary keys
- Multi-tenant API key system