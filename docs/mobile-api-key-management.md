# Mobile API Key Management Guide

## Overview

The `MOBILE_API_KEY` is a critical security component that authenticates the mobile application itself (not the user) with the Laravel backend. It serves as the first layer of defense in our multi-layer security architecture.

## Purpose

- **App Authentication**: Validates that requests are coming from the legitimate mobile application
- **API Gateway Control**: Prevents unauthorized applications from accessing mobile-specific endpoints
- **Rate Limiting Base**: Used for app-level rate limiting and abuse prevention
- **Security Monitoring**: Enables tracking of mobile app versions and potential compromise

## Key Generation Process

### 1. Generate Secure API Key

```bash
# Option 1: Using OpenSSL (Recommended)
openssl rand -hex 32

# Option 2: Using Laravel Artisan
php artisan key:generate --show | sed 's/base64://' | base64 -d | hexdump -v -e '/1 "%02x"'

# Option 3: Using Node.js
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

### 2. Key Format Requirements

- **Length**: 64 characters (32 bytes hex-encoded)
- **Character Set**: Hexadecimal (0-9, a-f)
- **Example**: `a1b2c3d4e5f6789012345678901234567890abcdef123456789012345678901234`

### 3. Environment Configuration

#### Backend (.env)
```bash
# Production
MOBILE_API_KEY=your-generated-64-char-hex-key-here
MOBILE_API_KEY_REQUIRED=true
MOBILE_API_KEY_HEADER=X-API-Key
```

#### Mobile App (.env.development, .env.staging, .env.production)
```bash
# Must match the backend key exactly
MOBILE_API_KEY=your-generated-64-char-hex-key-here
```

## Security Best Practices

### 1. Key Rotation Schedule

```bash
# Quarterly rotation (recommended)
0 0 1 */3 * /path/to/rotate-mobile-api-key.sh

# Emergency rotation (compromise detected)
# Immediate rotation with coordinated deployment
```

### 2. Key Storage

- **Backend**: Environment variables only, never in code
- **Mobile**: Build-time injection via environment variables
- **CI/CD**: Encrypted secrets management (GitHub Secrets, etc.)
- **Production**: Secure key management service (AWS Secrets Manager, etc.)

### 3. Key Validation

```php
// Backend validation in MobileSecurityMiddleware
if (!hash_equals(config('sanctum-mobile.api_key.key'), $providedKey)) {
    throw new InvalidApiKeyException('Invalid API key provided');
}
```

## Deployment Process

### 1. Coordinated Deployment

```bash
# 1. Generate new key
NEW_KEY=$(openssl rand -hex 32)

# 2. Update backend environment
echo "MOBILE_API_KEY=$NEW_KEY" >> .env

# 3. Update mobile app environment
echo "MOBILE_API_KEY=$NEW_KEY" >> apps/mobile-app/.env.production

# 4. Deploy backend first (accepts both old and new key temporarily)
# 5. Deploy mobile app with new key
# 6. Remove old key acceptance from backend
```

### 2. Zero-Downtime Rotation

```php
// Temporary dual-key support during rotation
'api_key' => [
    'primary' => env('MOBILE_API_KEY'),
    'secondary' => env('MOBILE_API_KEY_OLD'), // Remove after mobile deployment
    'rotation_window' => env('API_KEY_ROTATION_WINDOW', 3600), // 1 hour
],
```

## Monitoring and Alerting

### 1. Key Usage Monitoring

```php
// Log API key usage for monitoring
SecurityEvent::create([
    'type' => 'api_key_usage',
    'data' => [
        'key_version' => $keyVersion,
        'app_version' => $request->header('User-Agent'),
        'device_id' => $request->header('X-Device-Id'),
    ]
]);
```

### 2. Security Alerts

- **Invalid API Key Attempts**: > 10 failed attempts per minute
- **Old Key Usage**: After rotation grace period expires
- **Key Compromise Indicators**: Usage from unexpected locations/devices

## Emergency Procedures

### 1. Key Compromise Response

```bash
# 1. Immediately rotate key
NEW_KEY=$(openssl rand -hex 32)

# 2. Deploy to backend immediately
echo "MOBILE_API_KEY=$NEW_KEY" > .env && php artisan config:cache

# 3. Force mobile app update
# 4. Revoke all existing mobile tokens
php artisan sanctum:prune-expired --force-all-mobile

# 5. Notify users of required app update
```

### 2. Recovery Process

1. Generate new secure key
2. Update all environments
3. Coordinate deployment across backend and mobile
4. Monitor for successful key adoption
5. Revoke old key acceptance

## Testing

### 1. Development Testing

```bash
# Test with valid key
curl -H "X-API-Key: your-dev-key" http://localhost:8000/api/mobile/health

# Test with invalid key (should fail)
curl -H "X-API-Key: invalid-key" http://localhost:8000/api/mobile/health
```

### 2. Automated Testing

```php
// Test key validation
public function test_rejects_invalid_api_key()
{
    $response = $this->withHeaders([
        'X-API-Key' => 'invalid-key'
    ])->post('/api/mobile/devices/register');
    
    $response->assertStatus(401);
    $response->assertJson(['error' => 'Invalid API key']);
}
```

## Compliance Notes

- **PCI DSS**: Key rotation every 90 days minimum
- **SOC 2**: Documented key management procedures required
- **GDPR**: Key compromise requires breach notification if user data at risk
- **OWASP**: Follow OWASP API Security Top 10 guidelines

## Troubleshooting

### Common Issues

1. **Key Mismatch**: Ensure exact character-for-character match between backend and mobile
2. **Header Case**: API key header name is case-sensitive (`X-API-Key`)
3. **Environment Loading**: Verify environment files are loaded correctly
4. **Caching**: Clear config cache after key changes (`php artisan config:cache`)

### Debug Commands

```bash
# Verify backend key loading
php artisan tinker
>>> config('sanctum-mobile.api_key.key')

# Verify mobile app key loading
# Check mobile app logs for API key first few characters
```

## Implementation Checklist

- [ ] Generate secure 64-character hex API key
- [ ] Configure backend environment with key
- [ ] Configure mobile app environment with same key
- [ ] Test API key validation in development
- [ ] Set up key rotation schedule
- [ ] Implement monitoring and alerting
- [ ] Document emergency rotation procedures
- [ ] Train team on key management processes
- [ ] Set up automated testing for key validation
- [ ] Plan coordinated production deployment