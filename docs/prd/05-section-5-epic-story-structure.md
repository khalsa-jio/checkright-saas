### Section 5: Epic & Story Structure

#### **Epic 1: Foundation & Core Tenancy**

**Expanded Goal:** To establish the project's technical groundwork and implement a hierarchical, invitation-based multi-tenancy system.

* **Story 1.1: Project Scaffolding:** A Developer wants a new Laravel project initialized with all core dependencies (Filament, Pest, stancl/tenancy, Horizon, ActivityLog).
* **Story 1.2: Super Admin Tenant Creation:** A Super Admin wants to create a new tenant organization and invite the first Company Admin.
* **Story 1.3: Invited User Registration & Login:** A new user who has received an invitation wants to securely set up their account.
* **Story 1.4: Hierarchical User Management:** A Company Admin or Manager wants to invite and manage users with specific roles (Manager, Operator).
* **Story 1.5: Role-Based Access Control (RBAC):** A user wants their access to be restricted based on their assigned role.
* **Story 1.6: User-Initiated Password Reset:** A User who has forgotten their password wants to request a reset link via email.
* **Story 1.7: Admin-Forced Password Reset:** A Company Admin wants to require a user to reset their password on their next login.

***

#### **Epic 2: Subscription Management & Billing**

**Expanded Goal:** To integrate a complete and flexible billing system that allows the Super Admin to manage plans with custom limits and discounts, and enables Company Admins to subscribe.

* **Story 2.1: Super Admin Plan Management:** A Super Admin wants to create plans with a name, price, billing interval, data retention period, and limits (max users, max assets).
* **Story 2.2: Company Admin Subscription Flow:** A Company Admin wants to choose a plan and apply a discount code during signup.
* **Story 2.3: Subscription State Management & Billing Portal:** A Company Admin wants to view and manage their company's subscription and payment details.
* **Story 2.4: Subscription Expiry Notifications:** A Company Admin wants to receive email notifications before their trial or subscription expires.
* **Story 2.5: Discount Code Management:** A Super Admin wants to create and manage discount coupon codes.

***

#### **Epic 3: Asset and Supplier Management**

**Expanded Goal:** To enable Company Admins to populate their accounts with the essential information needed for inspections—the assets they own and the suppliers who service them.

* **Story 3.1: Asset Management (CRUD):** A Company Admin wants to create, view, update, and delete assets with type-specific, conditional fields.
* **Story 3.2: Supplier Management (CRUD):** A Company Admin wants to create, view, update, and delete supplier information.

***

#### **Epic 4: Dynamic Checklist Creation**

**Expanded Goal:** To empower Company Admins with a powerful tool to create and manage multi-page inspection templates.

* **Story 4.1: Checklist Template Management (CRUD):** A Company Admin wants to create, view, update, and delete checklist templates.
* **Story 4.2: Add and Configure Questions:** A Company Admin wants to add and configure different types of questions to a specific page within a checklist template.
* **Story 4.3: Reorder Checklist Questions:** A Company Admin wants to change the order of questions, including moving them between pages.
* **Story 4.4: Manage Checklist Pages:** A Company Admin wants to organize their checklist questions into multiple pages.
* **Story 4.5: Default Inspection Metadata:** A Company Admin wants every inspection to automatically capture key metadata (Start Time, End Time, GPS Location).

***

#### **Epic 5: End-to-End Inspection Workflow**

**Expanded Goal:** To deliver the core value proposition by enabling an inspector to complete a multi-page inspection on the mobile app, with results appearing on the admin dashboard.

* **Story 5.1: Mobile App Navigation & Asset Selection:** An Inspector wants to log in and use a clear navigation system to access their assigned assets.
* **Story 5.2: Perform Multi-Page Inspection:** An Inspector wants to fill out the multi-page checklist for their selected asset while metadata is captured automatically.
* **Story 5.3: Review and Submit Inspection:** An Inspector wants to review all their answers on a summary screen before submitting.
* **Story 5.4: Admin Dashboard - View and Action Submissions:** A Company Admin wants to see completed inspection reports on their dashboard and take action.
* **Story 5.5: View Inspection History:** An Inspector wants to view a list of their previously submitted inspections in the mobile app.

***
