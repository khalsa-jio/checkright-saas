<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile Token Configuration
    |--------------------------------------------------------------------------
    |
    | This array contains the configuration for mobile-specific tokens with
    | different lifetime values and abilities for enhanced security.
    |
    */

    'mobile_tokens' => [
        'access' => [
            'lifetime' => env('MOBILE_ACCESS_TOKEN_LIFETIME', 900), // 15 minutes
            'abilities' => ['*'],
        ],
        'refresh' => [
            'lifetime' => env('MOBILE_REFRESH_TOKEN_LIFETIME', 86400), // 24 hours
            'abilities' => ['refresh'],
        ],
        'longterm' => [
            'lifetime' => env('MOBILE_LONGTERM_TOKEN_LIFETIME', 2592000), // 30 days
            'abilities' => ['limited'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Device Management
    |--------------------------------------------------------------------------
    |
    | Configuration for device binding and management functionality.
    |
    */

    'device_binding' => [
        'enabled' => env('MOBILE_DEVICE_BINDING_ENABLED', true),
        'max_devices_per_user' => env('MAX_DEVICES_PER_USER', 5),
        'device_trust_duration' => env('DEVICE_TRUST_DURATION', 2592000), // 30 days
        'require_device_registration' => env('REQUIRE_DEVICE_REGISTRATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Key Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for API key authentication alongside bearer tokens.
    |
    */

    'api_key' => [
        'header_name' => env('MOBILE_API_KEY_HEADER', 'X-API-Key'),
        'required' => env('MOBILE_API_KEY_REQUIRED', true),
        'key' => env('MOBILE_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Signing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for HMAC request signing and validation.
    |
    */

    'request_signing' => [
        'enabled' => env('MOBILE_REQUEST_SIGNING_ENABLED', true),
        'algorithm' => env('MOBILE_SIGNATURE_ALGORITHM', 'sha256'),
        'timestamp_tolerance' => env('MOBILE_TIMESTAMP_TOLERANCE', 300), // 5 minutes
        'require_nonce' => env('MOBILE_REQUIRE_NONCE', true),
        'signature_header' => env('MOBILE_SIGNATURE_HEADER', 'X-Signature'),
        'timestamp_header' => env('MOBILE_TIMESTAMP_HEADER', 'X-Timestamp'),
        'nonce_header' => env('MOBILE_NONCE_HEADER', 'X-Nonce'),
        'device_id_header' => env('MOBILE_DEVICE_ID_HEADER', 'X-Device-Id'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Rotation Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic token rotation.
    |
    */

    'token_rotation' => [
        'enabled' => env('MOBILE_TOKEN_ROTATION_ENABLED', true),
        'threshold' => env('MOBILE_TOKEN_ROTATION_THRESHOLD', 0.8), // Rotate when 80% of lifetime used
        'auto_rotate' => env('MOBILE_AUTO_ROTATE_TOKENS', false), // Auto-rotate on threshold
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Features
    |--------------------------------------------------------------------------
    |
    | Additional security features for mobile authentication.
    |
    */

    'security' => [
        'enable_certificate_pinning' => env('MOBILE_ENABLE_CERT_PINNING', true),
        'require_biometric_for_sensitive' => env('MOBILE_REQUIRE_BIOMETRIC', false),
        'max_failed_attempts' => env('MOBILE_MAX_FAILED_ATTEMPTS', 5),
        'lockout_duration' => env('MOBILE_LOCKOUT_DURATION', 900), // 15 minutes
    ],

];
