<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for tenant creation and management
    |
    */

    /*
     * Domain configuration for tenant invitations
     */
    'domain' => [
        'suffix' => env('TENANT_DOMAIN_SUFFIX', '.checkright.test'),
        'protocol' => env('TENANT_PROTOCOL', 'https'),
    ],

    /*
     * Invitation configuration
     */
    'invitation' => [
        'expiry_days' => env('TENANT_INVITATION_EXPIRY_DAYS', 7),
        // DEPRECATED: URL template no longer used - URLs are generated via Invitation::getAcceptanceUrl()
        // All invitations now use central domain for consistency and security
        'url_template' => env('TENANT_INVITATION_URL_TEMPLATE', '{protocol}://{domain}{suffix}/accept-invitation/{token}'),
    ],

    /*
     * Resource limits for tenant creation
     */
    'limits' => [
        'max_tenants_per_hour' => env('TENANT_CREATION_RATE_LIMIT', 10),
        'max_invitations_per_hour' => env('TENANT_INVITATION_RATE_LIMIT', 50),
    ],

    /*
     * Performance settings for optimized tenant operations
     */
    'performance' => [
        'queue_tenant_creation' => env('QUEUE_TENANT_CREATION', false),
        'queue_email_sending' => env('QUEUE_EMAIL_SENDING', true),
        'cache_tenant_data' => env('CACHE_TENANT_DATA', true),
        'cache_ttl' => env('TENANT_CACHE_TTL', 3600), // 1 hour

        // Session optimization
        'session_lifetime' => env('TENANT_SESSION_LIFETIME', 120), // 2 hours
        'session_gc_probability' => env('TENANT_SESSION_GC_PROBABILITY', 1),
        'session_gc_divisor' => env('TENANT_SESSION_GC_DIVISOR', 100),

        // Route caching
        'route_cache_enabled' => env('TENANT_ROUTE_CACHE', true),
        'route_cache_ttl' => env('TENANT_ROUTE_CACHE_TTL', 3600),

        // Database connection pooling
        'connection_pool_size' => env('TENANT_CONNECTION_POOL_SIZE', 10),
        'connection_max_idle' => env('TENANT_CONNECTION_MAX_IDLE', 300), // 5 minutes

        // Asset optimization
        'asset_cache_enabled' => env('TENANT_ASSET_CACHE', true),
        'asset_cache_ttl' => env('TENANT_ASSET_CACHE_TTL', 86400), // 24 hours
    ],

    /*
     * Security settings for enhanced tenant isolation
     */
    'security' => [
        'session_isolation' => env('TENANT_SESSION_ISOLATION', true),
        'cookie_isolation' => env('TENANT_COOKIE_ISOLATION', true),
        'csrf_protection' => env('TENANT_CSRF_PROTECTION', true),
        'rate_limiting_enabled' => env('TENANT_RATE_LIMITING', true),
        'max_login_attempts' => env('TENANT_MAX_LOGIN_ATTEMPTS', 5),
        'login_throttle_minutes' => env('TENANT_LOGIN_THROTTLE_MINUTES', 1),
    ],

    /*
     * Hybrid tenancy preparation settings
     */
    'hybrid' => [
        'enabled' => env('HYBRID_TENANCY_ENABLED', false),
        'enterprise_threshold' => env('ENTERPRISE_TENANT_THRESHOLD', 1000), // Users
        'separate_db_criteria' => [
            'user_count' => env('SEPARATE_DB_USER_THRESHOLD', 1000),
            'storage_size' => env('SEPARATE_DB_STORAGE_THRESHOLD', 1073741824), // 1GB in bytes
            'monthly_requests' => env('SEPARATE_DB_REQUEST_THRESHOLD', 1000000),
        ],
        'migration_queue' => env('HYBRID_MIGRATION_QUEUE', 'tenant-migrations'),
    ],
];
