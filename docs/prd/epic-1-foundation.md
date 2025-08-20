# Epic 1: Foundation & Core Tenancy
**Expanded Goal:** To establish the project's technical groundwork and implement a hierarchical, invitation-based multi-tenancy system.

* **Story 1.1: Project Scaffolding:** A Developer wants a new Laravel project initialized with all core dependencies (Filament, Pest, stancl/tenancy, Horizon, ActivityLog).
* **Story 1.2: Super Admin Tenant Creation:** A Super Admin wants to create a new tenant organization and invite the first Company Admin.
* **Story 1.3: Invited User Registration & Login:** A new user who has received an invitation wants to securely set up their account.
* **Story 1.4: Hierarchical User Management:** A Company Admin or Manager wants to invite and manage users with specific roles (Manager, Operator).
* **Story 1.5: Role-Based Access Control (RBAC):** A user wants their access to be restricted based on their assigned role.
* **Story 1.6: User-Initiated Password Reset:** A User who has forgotten their password wants to request a reset link via email.
* **Story 1.7: Admin-Forced Password Reset:** A Company Admin wants to require a user to reset their password on their next login.