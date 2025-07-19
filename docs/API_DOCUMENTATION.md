# Social Downloader API Documentation

## Overview

The Social Downloader API provides secure authentication and user management functionality using Laravel Sanctum. This API supports user registration, authentication, password management, and token-based access control.

## Base URL

```
Production: https://your-domain.com/api
Development: http://localhost:8000/api
```

## Authentication

This API uses Bearer token authentication via Laravel Sanctum. Include the token in the Authorization header:

```
Authorization: Bearer {your-token}
```

## Rate Limiting

The API implements rate limiting to prevent abuse:

- **Registration**: 3 attempts per hour per IP
- **Login**: 5 attempts per 15 minutes per IP  
- **Password Reset**: 3 attempts per hour per IP
- **General API**: 1000 requests per hour per user/IP

Rate limit headers are included in responses:
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Remaining requests
- `X-RateLimit-Reset`: Reset timestamp

## Response Format

All API responses follow this consistent format:

### Success Response
```json
{
    "success": true,
    "message": "Operation completed successfully",
    "data": {
        // Response data here
    }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error description",
    "errors": {
        "field": ["Validation error message"]
    }
}
```

## Authentication Endpoints

### Register User

Create a new user account.

**Endpoint:** `POST /auth/register`

**Request Body:**
```json
{
    "name": "John Doe",
    "email": "john.doe@example.com",
    "password": "SecurePassword123!",
    "password_confirmation": "SecurePassword123!"
}
```

**Validation Rules:**
- `name`: Required, string, 2-255 characters, letters/spaces/hyphens only
- `email`: Required, valid email with DNS check, unique, max 255 characters
- `password`: Required, min 8 characters, mixed case, numbers, symbols
- `password_confirmation`: Required, must match password

**Success Response (201):**
```json
{
    "success": true,
    "message": "Registration successful. Welcome email sent.",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john.doe@example.com",
            "email_verified_at": null,
            "created_at": "2024-01-01T00:00:00.000000Z"
        },
        "token": "1|abc123def456...",
        "token_type": "Bearer",
        "expires_at": "2024-01-08T00:00:00.000000Z"
    }
}
```

**Error Responses:**
- `422`: Validation errors
- `429`: Rate limit exceeded

---

### Login User

Authenticate user and receive access token.

**Endpoint:** `POST /auth/login`

**Request Body:**
```json
{
    "email": "john.doe@example.com",
    "password": "SecurePassword123!"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john.doe@example.com",
            "email_verified_at": "2024-01-01T12:00:00.000000Z",
            "last_login_at": "2024-01-01T12:00:00.000000Z"
        },
        "token": "2|def456ghi789...",
        "token_type": "Bearer",
        "expires_at": "2024-01-08T12:00:00.000000Z"
    }
}
```

**Error Responses:**
- `401`: Invalid credentials
- `422`: Validation errors
- `429`: Rate limit exceeded

---

### Get User Profile

Retrieve authenticated user's profile information.

**Endpoint:** `GET /auth/user`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john.doe@example.com",
            "email_verified_at": "2024-01-01T12:00:00.000000Z",
            "last_login_at": "2024-01-01T12:00:00.000000Z",
            "created_at": "2024-01-01T00:00:00.000000Z",
            "membership_plan": {
                "id": 1,
                "name": "Premium",
                "slug": "premium"
            }
        }
    }
}
```

**Error Responses:**
- `401`: Unauthenticated

---

### Logout User

Revoke the current access token.

**Endpoint:** `POST /auth/logout`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Logged out successfully",
    "data": {}
}
```

**Error Responses:**
- `401`: Unauthenticated

---

### Request Password Reset

Send password reset link to user's email.

**Endpoint:** `POST /auth/forgot-password`

**Request Body:**
```json
{
    "email": "john.doe@example.com"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Password reset link sent to your email",
    "data": {}
}
```

**Error Responses:**
- `422`: Validation errors
- `429`: Rate limit exceeded

---

### Reset Password

Reset user password using token from email.

**Endpoint:** `POST /auth/reset-password`

**Request Body:**
```json
{
    "token": "reset-token-from-email",
    "email": "john.doe@example.com",
    "password": "NewSecurePassword123!",
    "password_confirmation": "NewSecurePassword123!"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Password reset successfully",
    "data": {}
}
```

**Error Responses:**
- `400`: Invalid or expired token
- `422`: Validation errors

## Security Features

### Input Sanitization
- All input is automatically sanitized to prevent XSS attacks
- Null bytes, control characters, and suspicious patterns are removed
- HTML tags are stripped from non-content fields

### Request Validation
- Content-Type validation for POST/PUT/PATCH requests
- Request size limits (1MB default)
- Required headers validation
- Suspicious pattern detection and logging

### Security Headers
All API responses include security headers:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`
- `Content-Security-Policy: default-src 'self'`
- `Strict-Transport-Security` (HTTPS only)

### CORS Configuration
- Configurable allowed origins
- Specific allowed methods and headers
- Credentials support for authenticated requests

## Error Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 413 | Payload Too Large |
| 415 | Unsupported Media Type |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Internal Server Error |

## SDKs and Examples

### cURL Examples

#### Register User
```bash
curl -X POST https://your-domain.com/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john.doe@example.com",
    "password": "SecurePassword123!",
    "password_confirmation": "SecurePassword123!"
  }'
```

#### Login User
```bash
curl -X POST https://your-domain.com/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "john.doe@example.com",
    "password": "SecurePassword123!"
  }'
```

#### Get User Profile
```bash
curl -X GET https://your-domain.com/api/auth/user \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### JavaScript Example
```javascript
// Login and get token
const loginResponse = await fetch('/api/auth/login', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  body: JSON.stringify({
    email: 'john.doe@example.com',
    password: 'SecurePassword123!'
  })
});

const loginData = await loginResponse.json();
const token = loginData.data.token;

// Use token for authenticated requests
const userResponse = await fetch('/api/auth/user', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json',
  }
});

const userData = await userResponse.json();
```

## Changelog

### Version 1.0.0 (2024-01-01)
- Initial API release
- User registration and authentication
- Password reset functionality
- Token-based authentication
- Rate limiting implementation
- Security headers and CORS
- Input validation and sanitization

## Admin Panel Integration

### Filament UserResource Features

The admin panel provides comprehensive user and token management:

#### Token Management
- **Generate Token**: Create API tokens for users with custom abilities and expiration
- **Manage Tokens**: View, revoke individual tokens
- **Bulk Actions**: Generate or revoke tokens for multiple users
- **Token Statistics**: Dashboard widgets showing token usage analytics

#### User Management
- **Token Count Column**: Shows active tokens per user
- **Last Login Tracking**: Displays when users last accessed the system
- **Filters**: Filter users by token status and login activity
- **Security Actions**: Email verification, membership management

#### Dashboard Widgets
- **Token Stats**: Total, active, expired token counts
- **Usage Charts**: Token creation trends over time
- **Recent Tokens**: Latest token activity table

### Verification Command

Run the setup verification command:
```bash
php artisan api:verify-setup
```

This command checks:
- Database connectivity and required tables
- Sanctum configuration
- Middleware setup
- Rate limiting functionality
- Security configurations
- Email setup
- Filament integration

Use `--fix` flag to attempt automatic fixes:
```bash
php artisan api:verify-setup --fix
```

## Support

For API support and questions:
- Documentation: [Link to full documentation]
- Issues: [Link to issue tracker]
- Email: support@your-domain.com
