# Social Downloader Project Overview

## Purpose
Social Downloader is a Laravel-based API service for extracting and downloading videos from social media platforms including YouTube, TikTok, Instagram, and Facebook. The system provides:

- Video metadata extraction using yt-dlp
- Background video download processing
- Cloud storage integration (S3/R2)
- API key authentication and rate limiting
- Admin panel using Filament v3
- Multi-language support (English/Vietnamese)

## Tech Stack
- **Backend**: Laravel 12+ (PHP 8.4+)
- **Admin Panel**: Filament v3.3
- **Database**: MySQL/PostgreSQL with UUID primary keys
- **Queue System**: Database-based job queues
- **Storage**: Local + S3/Cloudflare R2
- **Video Processing**: yt-dlp binary
- **Frontend**: Vite + TailwindCSS v4
- **Authentication**: Laravel Sanctum + API Keys
- **Permissions**: Spatie Laravel Permission
- **Settings**: Spatie Laravel Settings

## Key Features
- Multi-platform video extraction (YouTube, TikTok, Instagram, Facebook)
- Background job processing for video downloads
- API rate limiting for authenticated and guest users
- File upload service with streaming support for large files
- Scheduled file deletion system
- Comprehensive admin dashboard
- Multi-language support with translation files