# Fullstack Architecture Document: CheckRight

### Section 1: Introduction & High Level Architecture
**1.1. Introduction**
This document outlines the complete fullstack architecture for "CheckRight," including the Laravel backend, Filament admin panel, and the React Native mobile application. It serves as the single source of truth for all technical implementation, ensuring consistency and guiding AI-driven development.

* **Starter Template:** The project will be built upon the `Log1x/filament-starter` template. Key packages such as `stancl/tenancy` and `spatie/laravel-activitylog` will be integrated into this base. The React Native mobile application will be developed as a separate project within a monorepo.

**1.2. High Level Architecture**
"CheckRight" will be a full-stack, multi-tenant SaaS application built with a Laravel/Filament backend and a React Native mobile client, designed for a serverless architecture. A core component is the subscription management system, which will handle trials and billing via Stripe. For the MVP, it will use a shared-database tenancy model.

* **Repository Structure:** A monorepo managed with a tool like Nx or Turborepo is recommended to house both the Laravel/Filament application and the React Native mobile app.

* **High Level Project Diagram:**
  ```mermaid
  graph TD
      subgraph Users
          U_Admin[Company Admin]
          U_Inspector[Inspector]
      end

      subgraph "Client Applications"
          WebApp[Filament Web App / Billing Portal];
          MobileApp[React Native Mobile App];
      end

      subgraph "CheckRight SaaS Platform (Serverless)"
          API[Laravel REST API];
          Queue[Job Queue / Horizon];
          DB[(Managed Database)];
          Storage[(File Storage)];
      end
      
      subgraph "External Services"
          Payment[Payment Provider e.g., Stripe];
          Email[Email Service e.g., AWS SES];
      end

      U_Admin -- "Uses" --> WebApp;
      U_Inspector -- "Uses" --> MobileApp;
      WebApp -- "Interacts with" --> API;
      MobileApp -- "Makes calls to" --> API;
      API -- "Integrates with" --> Payment;
      API -- "Reads/Writes to" --> DB;
      API -- "Uploads/Reads from" --> Storage;
      API -- "Dispatches jobs to" --> Queue;
      Queue -- "Sends notifications via" --> Email;

# 2. Tech Stack & Data Models

## 2.1. Tech Stack
(As previously defined, using MySQL 8.x as the database)

## 2.2. Data Models
This section defines the core entities of the application.

* **Company (Tenant):** The root entity for a customer's organization.
* **User:** Represents an individual user with a specific role (`admin`, `manager`, `operator`) and contains fields for monitoring and lifecycle management (`last_login_at`, `deleted_at`, `must_change_password`).
* **Plan:** Represents a subscription plan with defined limits (users, assets, data retention) and pricing.
* **Subscription:** The link between a Company and a Plan, managed by Laravel Cashier.
* **Coupon:** Represents a discount code that can be applied to a subscription.
* **Asset:** A flexible entity representing a physical item (`vehicle`, `building`, `tool`, etc.) with a wide range of nullable, type-specific fields.
* **Supplier:** A third-party supplier or mechanic.
* **ChecklistTemplate:** The reusable master copy of a multi-page inspection checklist.
* **ChecklistQuestion:** A single question within a template.
* **Inspection:** A record of a single, completed inspection event, including automatically captured metadata (duration, GPS location).
* **InspectionAnswer:** The specific answer given for a question during an inspection.

# 3. API, Components, & External Services

## 3.1. API Specification
The system exposes a REST API based on the OpenAPI 3.0 specification. It provides endpoints for authentication, password reset, user profile management (`/me`), and core business logic for assets and inspections. All protected routes use bearer token authentication.

## 3.2. Components
* **React Native Mobile App:** The client interface for Inspectors.
* **Laravel Backend:** The core system containing all business logic.
* **Filament Admin Panel:** The web UI for Admins, built with Livewire.
* **Database:** The persistent MySQL data store.
* **Job Queue:** Redis and Laravel Horizon for asynchronous tasks.
* **Payment Provider (External):** Stripe for subscription billing.

## 3.3. External APIs
* **Stripe:** Used for all payment and subscription processing. Integration is handled server-side via Laravel Cashier, including listening for critical webhooks.
* **Amazon SES:** Used for all transactional emails (invitations, notifications, etc.). Integration is handled server-side via Laravel's native mail driver.

# 4. System Design & Workflows

## 4.1. Core Workflows
Sequence diagrams have been defined for critical processes, including **New Company Onboarding & Subscription** and **Inspector Submits an Inspection**. These diagrams illustrate the interaction between clients, the backend, the job queue, and external services, detailing both synchronous and asynchronous operations.

## 4.2. Database Schema
A concrete database schema has been defined using SQL DDL for MySQL. It includes tables for all data models, with appropriate primary keys, foreign keys (with cascading deletes where appropriate), and indexes on frequently queried columns to ensure performance.

## 4.3. Frontend Architecture (React Native)
* **Organization:** A feature-based directory structure will be used (`src/features/auth`, `src/features/inspections`).
* **State Management:** Zustand will be used for simple, performant global state.
* **Routing:** React Navigation will be used to manage authentication and main application navigators.
* **Services:** A centralized API client using Axios with an interceptor for auth tokens will handle all communication with the backend.

## 4.4. Backend Architecture (Laravel)
* **Service Architecture:** The application will be architected for a serverless environment (e.g., AWS Lambda via Laravel Vapor), with code organized by domain.
* **Data Access Layer:** The Repository Pattern will be implemented to abstract all database queries, making the application highly testable.
* **Authentication:** Laravel Sanctum will protect API routes, while standard session auth will protect the Filament web panel. `stancl/tenancy` middleware will be applied globally to scope all requests.

# 5. Development Workflow & Deployment

## 5.1. Unified Project Structure (Monorepo)
A monorepo will be used to house both the Laravel and React Native applications.
```plaintext
/
├── apps/
│   ├── api/          # Laravel + Filament Application
│   └── mobile/       # React Native Application
├── packages/
│   └── shared/       # Shared TypeScript types and validation rules
└── package.json      # Root package manager

# 6. Quality, Standards, & Operations

## 6.1. Testing Strategy
A testing pyramid will be followed:
* **Unit Tests:** Pest for the backend, Jest/RNTL for the mobile app.
* **Integration Tests:** Pest will be used to test service layer interactions.
* **E2E Tests:** Maestro will be used for automated testing of critical user flows on the mobile app.

## 6.2. Coding Standards
* A strict set of coding standards will be enforced automatically via **Pint** for PHP and **ESLint/Prettier** for TypeScript/React Native. All code must pass linting checks before being merged.

## 6.3. Error Handling
* A standardized JSON error response format will be used for the API. The mobile app will have a global error handler to gracefully manage API errors. All critical backend errors will be reported to **Sentry**.

## 6.4. Monitoring and Observability
* **Queues:** Laravel Horizon will provide a real-time dashboard for monitoring background jobs.
* **Error Tracking:** Sentry will be used for real-time error tracking and performance monitoring for both the backend and mobile app.
* **Logging:** The `spatie/laravel-activitylog` package will provide a detailed audit trail for all critical user and system actions.

# 7. Architect Solution Validation Checklist Results

## Executive Summary
* **Overall Architecture Readiness:** High
* **Critical Risks Identified:** None. The architecture is robust, and potential risks (e.g., vendor lock-in, missed webhooks) have standard mitigation patterns that will be implemented.
* **Key Strengths:** The architecture is modern, scalable (serverless), and highly testable. The use of a monorepo and shared types will improve developer velocity and reduce bugs.
