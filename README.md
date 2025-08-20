# CheckRight Platform - Monorepo

A comprehensive SaaS platform built with Laravel and modern frontend technologies.

## 🏗️ Project Structure

This is a monorepo containing multiple applications and shared packages:

```plain
saas-ai-2/
├── apps/
│   ├── api/                 # Laravel API application (multi-tenant SaaS)
│   └── mobile-app/          # React Native mobile application (Expo)
├── packages/                # Shared utilities and types (future)
├── docs/                    # Documentation (PRD, architecture, stories)
├── scripts/                 # Shared development scripts
├── web-bundles/            # Development agents and configurations
└── .github/                # GitHub workflows and actions
```

## 🚀 Quick Start

### Prerequisites

**For API Development:**

- PHP 8.3+
- Composer
- MySQL 8.0+
- Redis (optional, for queues)

**For Mobile App Development:**

- Node.js 20+
- pnpm 8+
- Expo CLI
- iOS Simulator (macOS) or Android Studio

### Installation

1. **Clone the repository**

   ```bash
   git clone <repository-url>
   cd saas-ai-2
   ```

2. **Install API dependencies**

   ```bash
   cd apps/api
   composer install
   cp .env.example .env
   # Update .env with your database configurations
   php artisan key:generate
   php artisan migrate
   ```

3. **Install Mobile App dependencies**

   ```bash
   cd apps/mobile-app
   pnpm install
   cp .env.example .env
   # Update .env with API URL and mobile API key
   ```

4. **Start development servers**

   ```bash
   # Terminal 1: Start Laravel API
   cd apps/api && php artisan serve
   
   # Terminal 2: Start mobile app
   cd apps/mobile-app && pnpm start
   ```

## 🛠️ Development

### API Development (Laravel)

```bash
cd apps/api

# Development commands
php artisan serve                # Start development server
php artisan test                 # Run Pest tests
./vendor/bin/pint               # Fix code style
php artisan migrate             # Run migrations

# Queue management
php artisan horizon             # Start job queue processor

# Tenancy commands  
php artisan tenants:list        # List all tenants
php artisan tenants:migrate     # Run tenant migrations
```

### Mobile App Development (React Native + Expo)

```bash
cd apps/mobile-app

# Development commands
pnpm start                      # Start Expo development server
pnpm run ios                    # Run on iOS simulator
pnpm run android               # Run on Android emulator
pnpm run web                    # Run on web browser

# Quality commands
pnpm run lint                   # Run ESLint
pnpm run type-check            # Run TypeScript check
pnpm run test                  # Run Jest tests
pnpm run check-all             # Run all quality checks

# Build commands
pnpm run prebuild:development  # Prebuild for development
pnpm run prebuild:production   # Prebuild for production
```

### Testing Strategy

```bash
# API Tests
cd apps/api && php artisan test

# Mobile Tests  
cd apps/mobile-app && pnpm run test:ci

# E2E Tests (Maestro)
cd apps/mobile-app && pnpm run e2e-test
```

## 📦 Applications

### API (`apps/api`)

Laravel application with:

- **Filament Admin Panel** - Modern admin interface
- **Multi-tenancy** - Using stancl/tenancy
- **Job Queues** - Laravel Horizon with Redis
- **Activity Logging** - Spatie ActivityLog
- **Testing** - Pest testing framework

## 🔧 Shared Packages

### `@checkright/shared`

Contains shared utilities, types, and configurations used across applications.

## 🧪 Testing

Run tests across all applications:

```bash
npm run test
```

Or run Laravel-specific tests:

```bash
cd apps/api
php artisan test
```

## 📝 Documentation

Documentation is available in the `docs/` directory:

- Product Requirements Document (PRD)
- Architecture Documentation
- User Stories and Development Plans

## 🤝 Contributing

This project follows a story-driven development approach using BMad agents:

- **Product Owner (po)** - Manages product requirements and story validation
- **Scrum Master (sm)** - Creates and manages user stories
- **Developer (dev)** - Implements features and maintains code quality

## 📄 License

Private Repository - All Rights Reserved
