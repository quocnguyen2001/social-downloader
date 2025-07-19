# API Key Authentication System

This document describes the comprehensive API key authentication system implemented for the social-downloader project's video extraction API.

## Overview

The API key authentication system provides secure access control for the video extraction API endpoints with the following features:

- **API Key Validation**: Secure hash-based API key validation
- **Caching**: High-performance caching to reduce database queries
- **Rate Limiting**: Tier-based rate limiting per API key
- **Usage Tracking**: Daily and monthly usage limits
- **Session Ownership**: Ensures users can only access their own extraction sessions

## Architecture

### Components

1. **ApiKeyAuthentication Middleware**: Main authentication middleware
2. **ApiKeyCacheService**: Caching service for API key data
3. **API Routes**: Protected and public endpoint definitions
4. **Test Suite**: Comprehensive tests for all functionality

### Flow Diagram

```
Request → Extract API Key → Check Cache → Validate → Rate Limit → Usage Limits → Controller
    ↓           ↓              ↓           ↓          ↓            ↓
   401        401           Database    429 (Rate)  429 (Usage)   200 (Success)
```

## Implementation Details

### Middleware: `ApiKeyAuthentication`

**Location**: `app/Http/Middleware/ApiKeyAuthentication.php`

**Features**:
- Extracts API keys from `Authorization: Bearer` header or `X-API-Key` header
- Validates API key status (active/inactive)
- Implements tier-based rate limiting
- Checks daily and monthly usage limits
- Caches API key data for performance
- Provides detailed error responses

**Rate Limits by Tier**:
- **Basic**: 60 requests/minute
- **Pro**: 300 requests/minute  
- **Premium**: 1000 requests/minute

### Cache Service: `ApiKeyCacheService`

**Location**: `app/Services/ApiKeyCacheService.php`

**Features**:
- Caches API key data for 5 minutes (configurable)
- Excludes sensitive data from cache
- Supports cache invalidation
- Handles negative caching for invalid keys
- Provides cache statistics and monitoring

**Cached Data**:
```php
[
    'id', 'name', 'tier', 'status', 'daily_limit', 'monthly_limit',
    'daily_usage', 'monthly_usage', 'total_usage', 'allowed_platforms',
    'allowed_qualities', 'allowed_formats', 'last_reset_daily', 'last_reset_monthly'
]
```

## API Endpoints

### Public Endpoints (No Authentication Required)

```http
GET /api/v1/extract/platforms
```
Returns supported platforms and their capabilities.

### Protected Endpoints (API Key Required)

```http
POST /api/v1/extract
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json

{
    "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
    "quality": "720p",
    "format": "mp4"
}
```

```http
GET /api/v1/extract/status/{sessionId}
Authorization: Bearer YOUR_API_KEY
```

## Usage Examples

### Using Bearer Token

```bash
curl -X POST http://localhost/api/v1/extract \
  -H "Authorization: Bearer your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
    "quality": "720p",
    "format": "mp4"
  }'
```

### Using X-API-Key Header

```bash
curl -X POST http://localhost/api/v1/extract \
  -H "X-API-Key: your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
    "quality": "720p",
    "format": "mp4"
  }'
```

### JavaScript/Fetch Example

```javascript
const response = await fetch('/api/v1/extract', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer your_api_key_here',
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        quality: '720p',
        format: 'mp4'
    })
});

const data = await response.json();
console.log(data);
```

## Error Responses

### 401 Unauthorized

```json
{
    "success": false,
    "message": "API key is required",
    "error_code": "UNAUTHORIZED"
}
```

```json
{
    "success": false,
    "message": "Invalid API key",
    "error_code": "UNAUTHORIZED"
}
```

```json
{
    "success": false,
    "message": "API key is not active",
    "error_code": "UNAUTHORIZED"
}
```

### 403 Forbidden

```json
{
    "success": false,
    "message": "Access denied to this session",
    "error_code": "FORBIDDEN"
}
```

### 429 Rate Limited

```json
{
    "success": false,
    "message": "Rate limit exceeded",
    "retry_after": 45,
    "limit": 60,
    "window": 60
}
```

```json
{
    "success": false,
    "message": "Daily usage limit exceeded",
    "daily_usage": 100,
    "daily_limit": 100,
    "resets_at": "2024-01-02T00:00:00Z"
}
```

## Testing

### Running Tests

```bash
# Run all authentication tests
php artisan test tests/Feature/ApiKeyAuthenticationTest.php

# Run cache service tests
php artisan test tests/Unit/ApiKeyCacheServiceTest.php

# Test the system manually
php artisan api-auth:test

# Test specific components
php artisan api-auth:test --create-key
php artisan api-auth:test --test-endpoints
php artisan api-auth:test --test-cache
php artisan api-auth:test --cleanup
```

### Test Coverage

The test suite covers:
- ✅ Valid API key authentication (Bearer and X-API-Key)
- ✅ Invalid API key rejection
- ✅ Missing API key rejection
- ✅ Inactive API key rejection
- ✅ Rate limiting enforcement
- ✅ Daily usage limits
- ✅ Monthly usage limits
- ✅ Session ownership validation
- ✅ Cache functionality
- ✅ Cache invalidation
- ✅ Tier-based permissions
- ✅ Public endpoint access

## Performance Considerations

### Caching Strategy

1. **API Key Data**: Cached for 5 minutes to reduce database queries
2. **Negative Results**: Invalid keys cached for 1 minute to prevent repeated lookups
3. **Cache Invalidation**: Manual invalidation when API key data changes
4. **Memory Usage**: Only essential data cached, sensitive information excluded

### Database Optimization

1. **Indexed Lookups**: API key hash field is indexed for fast lookups
2. **Minimal Queries**: Cache reduces database load significantly
3. **Connection Pooling**: Uses Laravel's database connection pooling

### Rate Limiting

1. **In-Memory Storage**: Uses Laravel's rate limiter with cache backend
2. **Sliding Window**: 1-minute sliding window for rate limits
3. **Tier-Based Limits**: Different limits based on API key tier

## Security Features

### API Key Security

1. **Hash Storage**: Only SHA-256 hashes stored in database
2. **Secure Transmission**: HTTPS required for production
3. **No Query Parameters**: API keys not accepted in URL parameters (except testing)
4. **Cache Security**: Sensitive data excluded from cache

### Access Control

1. **Session Ownership**: Users can only access their own extraction sessions
2. **Status Validation**: Only active API keys accepted
3. **Usage Limits**: Prevents abuse through usage limits
4. **Rate Limiting**: Prevents DoS attacks

## Monitoring and Logging

### Logged Events

- Successful authentications
- Failed authentication attempts
- Rate limit violations
- Usage limit violations
- Cache operations (debug level)

### Metrics Tracked

- Authentication success/failure rates
- Cache hit/miss ratios
- Rate limit violations per tier
- Usage patterns by API key

## Configuration

### Environment Variables

```env
# Cache configuration
CACHE_DRIVER=redis
CACHE_PREFIX=social_downloader

# Rate limiting
API_RATE_LIMIT_BASIC=60
API_RATE_LIMIT_PRO=300
API_RATE_LIMIT_PREMIUM=1000

# Cache TTL (seconds)
API_KEY_CACHE_TTL=300
```

### Middleware Registration

The middleware is registered in `bootstrap/app.php`:

```php
$middleware->alias([
    'api.auth' => \App\Http\Middleware\ApiKeyAuthentication::class,
]);
```

## Troubleshooting

### Common Issues

1. **"API key is required"**: Ensure Authorization header is set correctly
2. **"Invalid API key"**: Check that the API key exists and is active
3. **Rate limit exceeded**: Wait for the rate limit window to reset
4. **Cache issues**: Clear cache with `php artisan cache:clear`

### Debug Commands

```bash
# Test the authentication system
php artisan api-auth:test

# Clear all caches
php artisan cache:clear

# Check API key status
php artisan tinker
>>> App\Models\ApiKey::where('key_hash', hash('sha256', 'your_key'))->first()
```

## Future Enhancements

1. **Scope-Based Permissions**: More granular permission system
2. **API Key Rotation**: Automatic key rotation functionality
3. **Advanced Analytics**: Detailed usage analytics and reporting
4. **Webhook Support**: Notifications for usage limits and violations
5. **Multi-Factor Authentication**: Additional security layers
