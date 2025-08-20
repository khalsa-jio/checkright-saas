# Story 1.5: Mobile Security Architecture Implementation

## Overview
Implement comprehensive mobile security architecture for multi-tenant SaaS application with enhanced authentication, authorization, and threat protection.

## Epic Goals
- Secure mobile API authentication and authorization
- Implement advanced token management with rotation
- Deploy request signing and validation system
- Create mobile-specific security measures
- Establish rate limiting and abuse prevention
- Build security monitoring and threat detection

## Technical Requirements

### Authentication & Authorization System
- Enhanced Laravel Sanctum with mobile-specific tokens
- Multi-level token strategy (access, refresh, long-term)
- Device-bound authentication with fingerprinting
- Biometric authentication integration
- Multi-tenant authorization with RBAC
- Resource-level permissions framework

### Token Management
- JWT-based token strategy with rotation
- Secure mobile token storage using Keychain/KeyStore
- Automatic token refresh mechanism
- Token lifecycle management and tracking
- Emergency token revocation capability

### Request Security
- HMAC-based request signing
- Backend signature validation middleware
- SSL certificate pinning
- Timestamp validation and replay attack prevention
- Nonce-based duplicate request prevention

### Mobile Security Measures
- Root/jailbreak detection
- App integrity verification
- Anti-tampering protection
- Sensitive data encryption
- Device security validation

### Rate Limiting & Abuse Prevention
- Multi-tier sliding window rate limiting
- Behavioral analysis and anomaly detection
- DDoS protection with adaptive limiting
- Circuit breaker implementation
- Geographic anomaly detection

### Security Monitoring
- Comprehensive audit logging
- Real-time threat detection
- ML-based anomaly detection
- Automated incident response
- Security metrics dashboard

## Implementation Tasks

### Phase 1: Core Authentication & Authorization (Week 1-2)

#### Task 1.5.1: Enhanced Sanctum Configuration
```php
// File: config/sanctum-mobile.php
return [
    'mobile_tokens' => [
        'access' => [
            'lifetime' => 900,        // 15 minutes
            'abilities' => ['*']
        ],
        'refresh' => [
            'lifetime' => 86400,      // 24 hours  
            'abilities' => ['refresh']
        ],
        'longterm' => [
            'lifetime' => 2592000,    // 30 days
            'abilities' => ['limited']
        ]
    ],
    'device_binding' => true,
    'max_devices_per_user' => 5
];
```

#### Task 1.5.2: Mobile Authentication Controller
```php
// File: app/Http/Controllers/Auth/MobileAuthController.php
class MobileAuthController extends Controller
{
    public function login(MobileLoginRequest $request)
    {
        // Validate credentials
        // Check device security
        // Generate device-bound tokens
        // Log security event
    }
    
    public function refreshToken(RefreshTokenRequest $request)
    {
        // Validate refresh token
        // Rotate tokens
        // Update device tracking
    }
    
    public function registerDevice(DeviceRegistrationRequest $request)
    {
        // Validate device fingerprint
        // Register trusted device
        // Generate device-specific secrets
    }
    
    public function biometricChallenge(BiometricRequest $request)
    {
        // Generate biometric challenge
        // Validate biometric data
        // Issue short-term token
    }
}
```

#### Task 1.5.3: Multi-Tenant Authorization Middleware
```php
// File: app/Http/Middleware/TenantAuthorizationMiddleware.php
class TenantAuthorizationMiddleware
{
    public function handle($request, Closure $next, ...$permissions)
    {
        // Extract tenant context
        // Validate user belongs to tenant
        // Check resource permissions
        // Apply tenant-specific rules
        return $next($request);
    }
}
```

### Phase 2: Token Management System (Week 3)

#### Task 1.5.4: Token Manager Service
```php
// File: app/Services/Security/TokenManager.php
class TokenManager
{
    public function generateMobileTokens($user, $deviceId)
    {
        $accessToken = $this->createAccessToken($user, $deviceId);
        $refreshToken = $this->createRefreshToken($user, $deviceId);
        
        $this->trackTokens($accessToken, $refreshToken, $deviceId);
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => config('sanctum-mobile.mobile_tokens.access.lifetime')
        ];
    }
    
    public function rotateTokens($refreshToken)
    {
        // Validate refresh token
        // Generate new token pair
        // Invalidate old tokens
        // Update tracking
    }
    
    public function revokeDeviceTokens($deviceId)
    {
        // Emergency revocation
        // Clean up tracking
        // Log security event
    }
}
```

#### Task 1.5.5: React Native Secure Storage
```javascript
// File: src/security/SecureStorage.js
import { Keychain, SECURITY_LEVEL } from 'react-native-keychain';

export class SecureTokenStorage {
  async storeTokens(accessToken, refreshToken) {
    await Keychain.setInternetCredentials(
      'app_tokens',
      'tokens',
      JSON.stringify({ accessToken, refreshToken }),
      {
        securityLevel: SECURITY_LEVEL.SECURE_HARDWARE,
        accessControl: ACCESS_CONTROL.BIOMETRY_CURRENT_SET
      }
    );
  }
  
  async getTokens() {
    const credentials = await Keychain.getInternetCredentials('app_tokens');
    return credentials ? JSON.parse(credentials.password) : null;
  }
  
  async clearTokens() {
    await Keychain.resetInternetCredentials('app_tokens');
  }
}
```

### Phase 3: Request Signing & Validation (Week 4)

#### Task 1.5.6: Request Signature Middleware
```php
// File: app/Http/Middleware/ValidateRequestSignature.php
class ValidateRequestSignature
{
    public function handle($request, Closure $next)
    {
        if (!$this->requiresSignature($request)) {
            return $next($request);
        }
        
        $this->validateTimestamp($request);
        $this->validateNonce($request);
        $this->validateSignature($request);
        
        return $next($request);
    }
    
    private function validateSignature($request)
    {
        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');
        $nonce = $request->header('X-Nonce');
        $deviceId = $request->header('X-Device-Id');
        
        $expectedSignature = $this->generateSignature(
            $request->method(),
            $request->url(),
            $request->getContent(),
            $timestamp,
            $nonce,
            $this->getDeviceSecret($deviceId)
        );
        
        if (!hash_equals($expectedSignature, $signature)) {
            throw new InvalidSignatureException();
        }
    }
}
```

#### Task 1.5.7: Mobile Request Signer
```javascript
// File: src/security/RequestSigner.js
import CryptoJS from 'crypto-js';

export class RequestSigner {
  constructor(deviceSecret) {
    this.deviceSecret = deviceSecret;
  }
  
  signRequest(method, url, body, timestamp, nonce) {
    const payload = `${method}\n${url}\n${body}\n${timestamp}\n${nonce}`;
    const signature = CryptoJS.HmacSHA256(payload, this.deviceSecret);
    return signature.toString(CryptoJS.enc.Base64);
  }
  
  async makeSecureRequest(method, url, data = {}) {
    const timestamp = Date.now();
    const nonce = this.generateNonce();
    const body = JSON.stringify(data);
    const signature = this.signRequest(method, url, body, timestamp, nonce);
    
    const headers = {
      'Authorization': `Bearer ${await this.getAccessToken()}`,
      'X-Signature': signature,
      'X-Timestamp': timestamp,
      'X-Nonce': nonce,
      'X-Device-Id': await this.getDeviceId(),
      'Content-Type': 'application/json'
    };
    
    return fetch(url, { method, headers, body });
  }
}
```

### Phase 4: Mobile Security Measures (Week 5)

#### Task 1.5.8: Device Security Validator
```javascript
// File: src/security/DeviceValidator.js
import JailMonkey from 'jail-monkey';
import DeviceInfo from 'react-native-device-info';

export class DeviceSecurityValidator {
  async validateDevice() {
    const checks = {
      isJailBroken: JailMonkey.isJailBroken(),
      isOnExternalStorage: JailMonkey.isOnExternalStorage(),
      isDebuggingEnabled: JailMonkey.isDebuggingEnabled(),
      hookDetected: JailMonkey.hookDetected(),
      canMockLocation: JailMonkey.canMockLocation(),
      isEmulator: await DeviceInfo.isEmulator()
    };
    
    const riskScore = this.calculateRiskScore(checks);
    
    if (riskScore > 0.7) {
      throw new UnsafeDeviceError('Device security risk too high');
    }
    
    return { checks, riskScore };
  }
  
  calculateRiskScore(checks) {
    let score = 0;
    if (checks.isJailBroken) score += 0.4;
    if (checks.isDebuggingEnabled) score += 0.3;
    if (checks.hookDetected) score += 0.3;
    if (checks.canMockLocation) score += 0.2;
    if (checks.isOnExternalStorage) score += 0.1;
    if (checks.isEmulator) score += 0.2;
    return Math.min(score, 1.0);
  }
}
```

#### Task 1.5.9: App Integrity Checker
```javascript
// File: src/security/IntegrityChecker.js
export class AppIntegrityChecker {
  async verifyIntegrity() {
    const results = await Promise.all([
      this.verifyAppSignature(),
      this.verifyCodeIntegrity(),
      this.verifyFileIntegrity()
    ]);
    
    return results.every(result => result === true);
  }
  
  async verifyAppSignature() {
    // Platform-specific signature verification
    if (Platform.OS === 'ios') {
      return this.verifyIOSSignature();
    } else {
      return this.verifyAndroidSignature();
    }
  }
  
  async verifyCodeIntegrity() {
    // Check for runtime code modifications
    const checksums = await this.calculateCodeChecksums();
    return this.compareWithExpectedChecksums(checksums);
  }
}
```

### Phase 5: Rate Limiting & Abuse Prevention (Week 6)

#### Task 1.5.10: Mobile Rate Limiter
```php
// File: app/Http/Middleware/MobileRateLimit.php
class MobileRateLimit
{
    protected $limits = [
        'auth' => ['attempts' => 5, 'window' => 900, 'penalty' => 3600],
        'api' => ['requests' => 1000, 'window' => 3600, 'burst' => 50],
        'sensitive' => ['requests' => 10, 'window' => 3600, 'penalty' => 7200]
    ];
    
    public function handle($request, Closure $next, $operation = 'api')
    {
        $identifier = $this->getIdentifier($request);
        $limit = $this->limits[$operation];
        
        if (!$this->checkLimit($operation, $identifier, $limit)) {
            throw new TooManyRequestsHttpException($limit['penalty'] ?? 3600);
        }
        
        return $next($request);
    }
    
    private function checkLimit($operation, $identifier, $limit)
    {
        $key = "mobile_rate:{$operation}:{$identifier}";
        $current = Redis::get($key) ?? 0;
        
        if ($current >= $limit['requests']) {
            return false;
        }
        
        Redis::incr($key);
        Redis::expire($key, $limit['window']);
        
        return true;
    }
}
```

#### Task 1.5.11: Behavioral Analysis Service
```php
// File: app/Services/Security/BehaviorAnalyzer.php
class BehaviorAnalyzer
{
    public function analyzeRequest($request)
    {
        $userId = auth()->id();
        $patterns = [
            'frequency' => $this->analyzeRequestFrequency($userId),
            'geographic' => $this->detectGeographicAnomaly($userId, $request->ip()),
            'device' => $this->detectDeviceSwitching($userId, $request->header('X-Device-Id')),
            'usage' => $this->analyzeUsagePatterns($userId, $request)
        ];
        
        $riskScore = $this->calculateRiskScore($patterns);
        
        if ($riskScore > 0.8) {
            $this->triggerSecurityAlert($userId, $patterns);
            return 'block';
        } elseif ($riskScore > 0.6) {
            return 'challenge';
        }
        
        return 'allow';
    }
    
    private function calculateRiskScore($patterns)
    {
        return min(
            ($patterns['frequency'] * 0.3) +
            ($patterns['geographic'] * 0.25) +
            ($patterns['device'] * 0.25) +
            ($patterns['usage'] * 0.2),
            1.0
        );
    }
}
```

### Phase 6: Security Monitoring & Threat Detection (Week 7)

#### Task 1.5.12: Security Event Logger
```php
// File: app/Services/Security/SecurityLogger.php
class SecurityLogger
{
    protected $events = [
        'auth_success', 'auth_failure', 'token_refresh',
        'permission_denied', 'suspicious_activity', 'rate_limit_exceeded',
        'device_change', 'geographic_anomaly'
    ];
    
    public function logSecurityEvent($event, $context = [])
    {
        $logEntry = [
            'event' => $event,
            'timestamp' => now(),
            'user_id' => auth()->id(),
            'tenant_id' => auth()->user()->tenant_id ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'device_id' => request()->header('X-Device-Id'),
            'context' => $context,
            'risk_score' => $this->calculateRiskScore($event, $context),
            'session_id' => session()->getId()
        ];
        
        // Multi-destination logging
        Log::channel('security')->info($event, $logEntry);
        
        // High-risk events to SIEM
        if ($logEntry['risk_score'] > 0.7) {
            $this->sendToSIEM($logEntry);
        }
        
        // Real-time alerting
        if ($logEntry['risk_score'] > 0.8) {
            $this->triggerRealTimeAlert($logEntry);
        }
    }
}
```

#### Task 1.5.13: Threat Detection Engine
```php
// File: app/Services/Security/ThreatDetectionEngine.php
class ThreatDetectionEngine
{
    public function analyzeRequest($request)
    {
        $features = $this->extractFeatures($request);
        
        $results = [
            'ml_model' => $this->mlModel->predict($features),
            'rule_engine' => $this->ruleEngine->evaluate($features),
            'signature_match' => $this->signatureEngine->match($features)
        ];
        
        $threatScore = $this->aggregateScores($results);
        
        if ($threatScore > 0.8) {
            $this->triggerImmediateResponse($request, $threatScore);
        } elseif ($threatScore > 0.6) {
            $this->scheduleInvestigation($request, $threatScore);
        }
        
        return $threatScore;
    }
    
    private function extractFeatures($request)
    {
        return [
            'request_size' => strlen($request->getContent()),
            'header_count' => count($request->headers->all()),
            'unusual_headers' => $this->detectUnusualHeaders($request),
            'payload_entropy' => $this->calculateEntropy($request->getContent()),
            'geographic_distance' => $this->calculateGeographicDistance($request),
            'time_since_last_request' => $this->getTimeSinceLastRequest($request)
        ];
    }
}
```

### Phase 7: Integration & Testing (Week 8)

#### Task 1.5.14: Security Test Suite
```php
// File: tests/Feature/MobileSecurityTest.php
class MobileSecurityTest extends TestCase
{
    public function test_device_registration_and_validation()
    {
        // Test device fingerprinting
        // Test device trust establishment
        // Test device-bound tokens
    }
    
    public function test_request_signing_validation()
    {
        // Test valid signature acceptance
        // Test invalid signature rejection
        // Test timestamp validation
        // Test nonce replay prevention
    }
    
    public function test_rate_limiting_enforcement()
    {
        // Test various rate limit scenarios
        // Test penalty enforcement
        // Test adaptive rate limiting
    }
    
    public function test_threat_detection_accuracy()
    {
        // Test ML model predictions
        // Test rule engine evaluation
        // Test signature matching
    }
    
    public function test_incident_response_automation()
    {
        // Test automated blocking
        // Test token revocation
        // Test alert generation
    }
}
```

#### Task 1.5.15: Mobile Security Integration
```javascript
// File: src/services/SecurityService.js
export class SecurityService {
  constructor() {
    this.deviceValidator = new DeviceSecurityValidator();
    this.integrityChecker = new AppIntegrityChecker();
    this.tokenStorage = new SecureTokenStorage();
    this.requestSigner = new RequestSigner();
  }
  
  async initializeSecurity() {
    // Validate device security
    await this.deviceValidator.validateDevice();
    
    // Verify app integrity
    await this.integrityChecker.verifyIntegrity();
    
    // Setup secure networking
    this.setupCertificatePinning();
    
    // Initialize token management
    await this.initializeTokens();
  }
  
  async makeSecureApiCall(endpoint, data, options = {}) {
    // Pre-request security checks
    await this.performSecurityChecks();
    
    // Sign and execute request
    return this.requestSigner.makeSecureRequest(
      options.method || 'POST',
      endpoint,
      data
    );
  }
}
```

## Acceptance Criteria

### Security Requirements
- [x] Multi-level token authentication implemented *(Task 1.3 - JWT TokenManager)*
- [x] Device binding and fingerprinting active *(Task 1.4 - Biometric auth component)*
- [ ] Request signing and validation functional
- [ ] Rate limiting enforced across all endpoints
- [x] Biometric authentication integrated *(Task 1.4 - React Native biometric-auth.tsx)*
- [ ] Certificate pinning implemented
- [ ] Root/jailbreak detection active

### Monitoring Requirements
- [ ] Security events logged comprehensively
- [ ] Threat detection engine operational
- [ ] Real-time alerting functional
- [ ] Security metrics dashboard deployed
- [ ] Incident response automation active

### Performance Requirements
- [ ] Authentication response time < 500ms
- [ ] Token refresh time < 200ms
- [ ] Request signature validation < 50ms
- [ ] Device validation < 100ms
- [ ] 99.9% API availability maintained

### Testing Requirements
- [ ] 95%+ test coverage for security components
- [ ] Penetration testing completed
- [ ] Load testing under security constraints
- [ ] Mobile security testing across devices
- [ ] OWASP Mobile Top 10 compliance verified

## Rollout Plan

### Phase 1: Core Security (Week 1-2)
- Deploy enhanced authentication
- Implement token management
- Enable multi-tenant authorization

### Phase 2: Request Security (Week 3-4)
- Activate request signing
- Deploy signature validation
- Implement certificate pinning

### Phase 3: Mobile Hardening (Week 5-6)
- Enable device validation
- Deploy integrity checking
- Activate rate limiting

### Phase 4: Monitoring & Detection (Week 7-8)
- Launch threat detection
- Enable security logging
- Deploy monitoring dashboard

## Risk Mitigation

### High Risk Items
- **Token compromise**: Multi-layer token strategy with rotation
- **Device spoofing**: Hardware-based fingerprinting
- **API abuse**: Behavioral analysis and rate limiting
- **Man-in-the-middle**: Certificate pinning and request signing

### Monitoring
- Real-time threat score monitoring
- Automated incident response
- Security metrics tracking
- Compliance audit trails

## Success Metrics

- 99.9% reduction in unauthorized access attempts
- 95% improvement in threat detection accuracy
- <50ms security validation overhead
- 100% compliance with mobile security standards
- Zero security incidents post-deployment

---

## Phase 1 Completion Notes

### Implemented Features (Tasks 1.3-1.5)
✅ **Task 1.3: JWT Token Service**
- `app/Services/Security/TokenManager.php` - Complete JWT token lifecycle management
- Automatic token rotation and validation
- Multi-tenant token isolation

✅ **Task 1.4: Biometric Authentication**
- `mobile/src/components/biometric-auth.tsx` - Face ID/Touch ID/Fingerprint integration
- Secure biometric challenge/response flow
- Fallback authentication methods

✅ **Task 1.5: Secure Storage**
- `mobile/src/utils/secure-storage.tsx` - iOS Keychain & Android KeyStore
- Encrypted token storage with hardware-backed security
- Auto-cleanup on logout/uninstall

### Implementation Files
#### Backend Components
- `app/Services/Security/TokenManager.php` - JWT token lifecycle management
- `app/Http/Controllers/TokenManagementController.php` - Token API endpoints
- `config/sanctum-mobile.php` - Mobile-specific Sanctum configuration

#### Mobile Components  
- `mobile/src/components/biometric-auth.tsx` - Biometric authentication UI/logic
- `mobile/src/utils/secure-storage.tsx` - Secure token storage wrapper
- `mobile/src/services/token-service.ts` - Token management service

### Testing Coverage
- Comprehensive unit tests for TokenManager service
- Integration tests for biometric authentication flow
- End-to-end tests for secure storage operations

**Phase 1 Status: ✅ COMPLETED**