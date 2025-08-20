# CheckRight API

Laravel application providing backend services for the CheckRight SaaS platform with multi-tenancy support.

## 🚀 Quick Start

### Prerequisites

- PHP 8.3+
- Composer
- MySQL 8.0+
- Redis (optional, for queues)
- Node.js 20+ (for asset compilation)

### Installation

1. **Install dependencies**

   ```bash
   composer install
   ```

2. **Environment Setup**

   ```bash
   cp .env.example .env
   # Update .env with your database and API configurations
   ```

3. **Application Setup**

   ```bash
   php artisan key:generate
   php artisan migrate
   php artisan db:seed  # Optional: seed demo data
   ```

4. **Start development**

   ```bash
   php artisan serve
   # Or use the comprehensive dev server
   composer run dev
   ```

### Development Commands

```bash
# Development server
php artisan serve                # Start development server (port 8000)
composer run dev                 # Start with queue, logs, and vite

# Database management
php artisan migrate             # Run migrations
php artisan migrate:fresh --seed # Fresh migration with seeding
php artisan db:seed             # Run database seeders

# Queue management
php artisan horizon             # Start job queue processor
php artisan queue:listen        # Basic queue worker

# Multi-tenancy commands
php artisan tenants:list        # List all tenants
php artisan tenants:migrate     # Run tenant-specific migrations
php artisan tenants:seed        # Seed tenant databases

# Quality assurance
php artisan test                # Run Pest tests
./vendor/bin/pint              # Fix code style
./vendor/bin/pint --test       # Check code style without fixing

# Cache and optimization
php artisan config:cache        # Cache configuration
php artisan route:cache         # Cache routes
php artisan view:cache          # Cache views
php artisan optimize            # Run all optimization commands
php artisan optimize:clear      # Clear all cached data

# Debug and introspection
php artisan pail                # Real-time log monitoring
php artisan tinker              # Interactive PHP shell
```

## 📱 Mobile App Integration

This API is designed to work with the CheckRight mobile app located in `../mobile-app/`.

### Authentication

**Laravel Sanctum** for API token authentication:

- Login endpoint provides access tokens for mobile app
- Multi-tenant support with automatic tenant resolution
- Secure token storage on mobile using Expo SecureStore

### API Endpoints

#### Public Routes

```bash
POST /api/auth/login                    # User authentication
POST /api/invitations/{token}/accept    # Accept user invitation
POST /api/mobile/oauth/{provider}/initialize # OAuth initialization
POST /api/mobile/oauth/{provider}/callback   # OAuth callback
```

#### Authenticated Routes

```bash
GET  /api/user                          # Get authenticated user
POST /api/auth/logout                   # User logout
```

#### Mobile-Specific Routes (with enhanced security)

```bash
# User Management
GET    /api/mobile/users                # List users
GET    /api/mobile/users/{user}         # Get user details
PUT    /api/mobile/users/{user}         # Update user
DELETE /api/mobile/users/{user}         # Soft delete user
POST   /api/mobile/users/{user}/restore # Restore soft-deleted user
POST   /api/mobile/users/{user}/force-password-reset # Force password reset
POST   /api/mobile/users/bulk-deactivate # Bulk deactivate users

# Invitation Management
POST   /api/mobile/invitations          # Send user invitation
GET    /api/mobile/invitations/pending  # List pending invitations
POST   /api/mobile/invitations/{invitation}/resend # Resend invitation
DELETE /api/mobile/invitations/{invitation} # Cancel invitation

# Device Management
POST   /api/mobile/devices/register     # Register mobile device
GET    /api/mobile/devices              # List user devices
POST   /api/mobile/devices/{deviceId}/trust # Trust device
DELETE /api/mobile/devices/{deviceId}/trust # Revoke device trust
DELETE /api/mobile/devices/{deviceId}   # Remove device
GET    /api/mobile/devices/security-status # Get security status

# Token Management
POST   /api/mobile/tokens/generate      # Generate API tokens
POST   /api/mobile/tokens/refresh       # Refresh tokens
GET    /api/mobile/tokens/info          # Get token information
DELETE /api/mobile/tokens/device        # Revoke device tokens
DELETE /api/mobile/tokens/all           # Revoke all tokens
GET    /api/mobile/tokens/should-rotate # Check if rotation needed
GET    /api/mobile/tokens/validate      # Validate current token
```

## 🏗️ Architecture

### Tech Stack

- **Framework**: Laravel 12
- **Database**: MySQL with single-database multi-tenancy
- **Queue System**: Laravel Horizon with Redis
- **Admin Panel**: Filament v4
- **Authentication**: Laravel Sanctum
- **Multi-tenancy**: stancl/tenancy package
- **Testing**: Pest PHP testing framework
- **Code Style**: Laravel Pint

### Key Features

- **Multi-Tenant SaaS**: Single database multi-tenancy with automatic tenant resolution
- **Mobile API**: Dedicated mobile endpoints with enhanced security middleware
- **Activity Logging**: Comprehensive audit trail using Spatie ActivityLog
- **Queue Processing**: Background job processing with Laravel Horizon
- **OAuth Integration**: Social authentication for mobile apps
- **Device Management**: Mobile device registration and trust management
- **Token Management**: Secure token rotation and validation

### Project Structure

```plain
apps/api/
├── app/
│   ├── Http/
│   │   ├── Controllers/      # API controllers
│   │   ├── Middleware/       # Custom middleware
│   │   └── Requests/         # Form request validation
│   ├── Models/               # Eloquent models
│   ├── Providers/           # Service providers
│   └── Services/            # Business logic services
├── config/                  # Configuration files
├── database/
│   ├── migrations/          # Database migrations
│   ├── seeders/             # Database seeders
│   └── factories/           # Model factories
├── routes/
│   ├── api.php             # API routes
│   ├── web.php             # Web routes
│   └── tenant.php          # Tenant-specific routes
├── tests/
│   ├── Feature/            # Feature tests
│   └── Unit/               # Unit tests
└── resources/
    └── views/              # Blade templates
```

## 🔒 Security

### Multi-Tenancy Security

- **Tenant Isolation**: Complete data isolation between tenants
- **Automatic Context**: Tenant context automatically resolved from subdomain/domain
- **Migration Safety**: Tenant migrations run in isolated environments

### API Security

- **Laravel Sanctum**: Token-based authentication
- **Mobile Security Middleware**: Enhanced security for mobile endpoints
- **Device Trust Management**: Track and manage trusted mobile devices
- **Token Rotation**: Automatic token refresh and validation
- **Rate Limiting**: Built-in Laravel rate limiting on sensitive endpoints

### Best Practices

- Environment variables for all sensitive configuration
- CORS properly configured for mobile app domains
- Input validation using Form Request classes
- SQL injection protection through Eloquent ORM
- XSS protection via Laravel's built-in features

## 🧪 Testing

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/AuthTest.php

# Run tests with filter
php artisan test --filter=login

# Coverage report
php artisan test --coverage
```

### Test Structure

- **Feature Tests**: Test complete user workflows and API endpoints
- **Unit Tests**: Test individual classes and methods in isolation
- **Database Tests**: Use RefreshDatabase trait for clean test state
- **Multi-tenancy Tests**: Test tenant isolation and context switching

### Key Test Files

- `tests/Feature/AuthTest.php` - Authentication flow tests
- `tests/Feature/TenantTest.php` - Multi-tenancy functionality
- `tests/Feature/MobileApiTest.php` - Mobile-specific API tests
- `tests/Unit/` - Unit tests for individual components

## 🚀 Deployment

### Production Setup

1. **Environment Configuration**
   ```bash
   # Production optimizations
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan optimize
   ```

2. **Queue Processing**
   ```bash
   # Start Horizon for production
   php artisan horizon
   ```

3. **Tenant Setup**
   ```bash
   # Create new tenant
   php artisan tenants:create example.com
   ```

### Environment Variables

Key environment variables for production:

```env
APP_NAME="CheckRight"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=checkright
DB_USERNAME=root
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Multi-tenancy
TENANCY_DATABASE_AUTO_DELETE=false
TENANCY_MIGRATE_AFTER_DOMAIN_CREATION=true

# Mobile App CORS
MOBILE_APP_URL=https://your-mobile-app-domain.com
```

## 📚 Documentation

- **API Documentation**: Generated from route definitions and controller docblocks
- **Multi-tenancy Guide**: See `docs/` directory for tenant management guides
- **Mobile Integration**: Authentication and API usage patterns for mobile apps

## 🤝 Contributing

1. Follow PSR-12 coding standards (enforced by Pint)
2. Write tests for all new features
3. Update API documentation for new endpoints
4. Run `composer run test` before submitting PRs
5. Use conventional commit messages

## 📄 License

Private Repository - All Rights Reserved
