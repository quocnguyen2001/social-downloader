# API Authentication Testing Guide

This guide provides comprehensive testing procedures for the Laravel Sanctum API authentication system.

## Prerequisites

- Postman, Insomnia, or similar API testing tool
- Access to the application logs
- Admin access to Filament panel

## Authentication Endpoints Testing

### 1. User Registration (`POST /api/auth/register`)

#### Valid Registration Test
```json
{
    "name": "John Doe",
    "email": "john.doe@example.com",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!"
}
```

**Expected Response (201):**
```json
{
    "success": true,
    "message": "Registration successful",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john.doe@example.com",
            "email_verified_at": null,
            "created_at": "2024-01-01T00:00:00.000000Z"
        },
        "token": "1|abc123...",
        "token_type": "Bearer",
        "expires_at": "2024-01-08T00:00:00.000000Z"
    }
}
```

#### Rate Limiting Test
- Make 4+ registration requests from same IP within 60 minutes
- **Expected:** 429 Too Many Requests after 3rd attempt

#### Validation Tests
Test these invalid inputs:
- Empty name: `{"name": "", ...}`
- Invalid email: `{"email": "invalid-email", ...}`
- Weak password: `{"password": "123", ...}`
- Mismatched passwords: `{"password_confirmation": "different", ...}`
- XSS attempt: `{"name": "<script>alert('xss')</script>", ...}`

### 2. User Login (`POST /api/auth/login`)

#### Valid Login Test
```json
{
    "email": "john.doe@example.com",
    "password": "SecurePass123!"
}
```

**Expected Response (200):**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {...},
        "token": "2|def456...",
        "token_type": "Bearer",
        "expires_at": "2024-01-08T00:00:00.000000Z"
    }
}
```

#### Rate Limiting Test
- Make 6+ failed login attempts from same IP
- **Expected:** 429 Too Many Requests after 5th attempt

#### Invalid Credentials Test
```json
{
    "email": "john.doe@example.com",
    "password": "WrongPassword"
}
```
**Expected Response (401):** Unauthorized

### 3. Password Reset Request (`POST /api/auth/forgot-password`)

#### Valid Request Test
```json
{
    "email": "john.doe@example.com"
}
```

**Expected Response (200):**
```json
{
    "success": true,
    "message": "Password reset link sent",
    "data": {}
}
```

#### Rate Limiting Test
- Make 4+ password reset requests from same IP within 60 minutes
- **Expected:** 429 Too Many Requests after 3rd attempt

### 4. Password Reset Confirmation (`POST /api/auth/reset-password`)

#### Valid Reset Test
```json
{
    "token": "reset-token-from-email",
    "email": "john.doe@example.com",
    "password": "NewSecurePass123!",
    "password_confirmation": "NewSecurePass123!"
}
```

### 5. User Profile (`GET /api/auth/user`)

#### Authenticated Request Test
**Headers:**
```
Authorization: Bearer 1|abc123...
Accept: application/json
```

**Expected Response (200):**
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john.doe@example.com",
            "email_verified_at": null,
            "created_at": "2024-01-01T00:00:00.000000Z"
        }
    }
}
```

#### Unauthenticated Request Test
**Headers:** (No Authorization header)
**Expected Response (401):** Unauthorized

### 6. Logout (`POST /api/auth/logout`)

#### Valid Logout Test
**Headers:**
```
Authorization: Bearer 1|abc123...
Accept: application/json
```

**Expected Response (200):**
```json
{
    "success": true,
    "message": "Logged out successfully",
    "data": {}
}
```

## Security Testing

### 1. Input Sanitization Tests

Test these malicious inputs across all endpoints:
- SQL Injection: `'; DROP TABLE users; --`
- XSS: `<script>alert('xss')</script>`
- Path Traversal: `../../../etc/passwd`
- Command Injection: `; cat /etc/passwd`

**Expected:** All should be sanitized or rejected

### 2. Header Validation Tests

#### Missing Accept Header
**Expected Response (400):** Invalid request headers

#### Invalid Content-Type
Send POST request with `Content-Type: text/plain`
**Expected Response (415):** Invalid content type

### 3. Rate Limiting Verification

Check these rate limits:
- Registration: 3 attempts per 60 minutes per IP
- Login: 5 attempts per 15 minutes per IP
- Password Reset: 3 attempts per 60 minutes per IP

### 4. Security Headers Verification

Check response headers include:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`
- `Content-Security-Policy: default-src 'self'`
- `Strict-Transport-Security` (HTTPS only)

## Filament Integration Testing

### 1. Token Management in UserResource

#### Access Filament Admin Panel
1. Navigate to `/admin/users`
2. Verify token-related columns are visible:
   - API Tokens count
   - Last Login timestamp

#### Generate Token Action
1. Click on a user row action menu
2. Select "Generate API Token"
3. Fill in token details:
   - Name: "Test Token"
   - Abilities: ["*"]
   - Expires: 30 days from now
4. Click "Generate Token"
5. Verify token is displayed securely
6. Copy token and test API access

#### Manage Tokens Action
1. Click "Manage Tokens" for a user with existing tokens
2. Verify token list displays correctly
3. Test individual token revocation
4. Verify token is deleted from database

#### Bulk Token Actions
1. Select multiple users
2. Test "Generate API Tokens" bulk action
3. Test "Revoke All Tokens" bulk action
4. Verify actions complete successfully

### 2. Token Statistics Widgets

#### Dashboard Verification
1. Navigate to `/admin`
2. Verify these widgets display:
   - Token Stats Widget (total, active, expired tokens)
   - Token Usage Chart (creation trend)
   - Recent Tokens Widget (latest tokens table)

#### Widget Data Accuracy
1. Create/revoke tokens via API
2. Refresh dashboard
3. Verify widget data updates correctly

### 3. User Filters and Search

#### Token-based Filters
1. Use "Has Tokens" filter
2. Use "Login Activity" filter
3. Verify results are accurate

#### Search Functionality
1. Search by user name/email
2. Verify token-related data appears in results

## Performance Testing

### 1. Load Testing

Test with multiple concurrent requests:
- 100 registration requests
- 100 login requests
- 100 token generation requests

Monitor:
- Response times
- Memory usage
- Database connections
- Rate limiting effectiveness

### 2. Token Performance

Test with users having many tokens:
- Create user with 50+ tokens
- Test token listing performance
- Test bulk token operations

## Error Handling Verification

### 1. Database Connection Errors
- Temporarily disable database
- Test API endpoints
- Verify graceful error responses

### 2. Email Service Errors
- Configure invalid SMTP settings
- Test registration/password reset
- Verify errors are handled gracefully

### 3. Rate Limiting Storage Errors
- Test with Redis/cache unavailable
- Verify fallback behavior

## Logging Verification

### 1. Security Event Logging
Check logs for these events:
- Suspicious input patterns
- Rate limit violations
- Failed authentication attempts
- Token generation/revocation

### 2. Log Format Verification
Ensure logs contain:
- Timestamp
- IP address
- User agent
- Request details (sanitized)
- Security event type

## Checklist Summary

- [ ] All authentication endpoints respond correctly
- [ ] Rate limiting works for all endpoints
- [ ] Input validation rejects malicious input
- [ ] Security headers are present
- [ ] CORS configuration works
- [ ] Token generation/revocation works in Filament
- [ ] Widgets display accurate data
- [ ] Bulk actions work correctly
- [ ] Email notifications are sent
- [ ] Logging captures security events
- [ ] Performance is acceptable under load
- [ ] Error handling is graceful
- [ ] Documentation is complete

## Common Issues and Solutions

### Issue: CORS Errors
**Solution:** Check `CORS_ALLOWED_ORIGINS` in `.env`

### Issue: Rate Limiting Not Working
**Solution:** Verify cache/Redis configuration

### Issue: Tokens Not Generating
**Solution:** Check Sanctum configuration and database

### Issue: Email Not Sending
**Solution:** Verify mail configuration and queue processing

### Issue: Filament Actions Not Working
**Solution:** Check user permissions and middleware configuration
