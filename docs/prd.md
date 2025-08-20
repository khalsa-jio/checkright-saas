# Product Requirements Document: CheckRight

### Section 1: Goals and Background Context
**Goals**
This document outlines the requirements for the Minimum Viable Product (MVP) of "CheckRight." The primary goals of this MVP are to deliver a product that achieves the following outcomes for our initial users:

* **1. Reduce Administrative Overhead**
    * **Technical Outcomes:** The system must support the dynamic creation, updating, and deletion of multi-step inspection checklists with various question types (pass/fail, photo upload, text). All inspection data must be stored in a structured, relational format, linked to a specific user, asset, and tenant.
    * **User Experience Outcomes:** Empower the "Operations/Fleet Manager" persona with an intuitive, drag-and-drop interface for checklist building that requires no technical expertise. The main dashboard should provide an at-a-glance, real-time overview of the fleet's inspection status.

* **2. Improve Compliance & Accountability**
    * **Technical Outcomes:** Every inspection submission must be immutable and logged with a precise timestamp, user ID, and asset ID. The system must enforce a basic Role-Based Access Control (RBAC) model, clearly separating Admin and Inspector privileges.
    * **User Experience Outcomes:** The platform must provide managers with a clear, easily searchable digital audit trail. For the "Field Operator" persona, their submission history should be easily accessible, providing them with a verifiable record of their work.

* **3. Accelerate Maintenance Cycles**
    * **Technical Outcomes:** The system must be able to flag failed inspection items and trigger an internal event. A mechanism must be in place to send a formatted email notification containing key inspection data to a pre-configured supplier address.
    * **User Experience Outcomes:** When a critical issue is logged by an operator, the platform must provide a clear, one-click action for the manager to notify a supplier, bridging the gap between problem identification and resolution.

* **4. Increase Operator Efficiency**
    * **Technical Outcomes:** The mobile application must be a cross-platform solution (iOS/Android) built with React Native. It must have offline capabilities, queueing submissions for synchronization when connectivity is restored. The API response times for submitting an inspection should be under 500ms.
    * **User Experience Outcomes:** The mobile experience for the "Field Operator" must be exceptionally fast and simple, designed for completion in under 2 minutes. The UI should be optimized for use in varied field conditions (e.g., large touch targets, high-contrast design).

**Background Context**
"CheckRight" is being developed to address the significant inefficiencies and risks associated with manual, paper-based vehicle and machinery inspection processes. This PRD focuses on an MVP to validate the core digital workflow.

**For the Design Team:** The primary design challenge is to create a dual-experience product that is both a powerful administrative tool for the "Operations/Fleet Manager" and a lightning-fast, simple utility for the "Field Operator."

**For the Technical Team:** The MVP will be built on a shared-database, multi-tenant architecture using the `stancl/tenancy` package. This is a pragmatic choice to validate the core feature set and user experience with minimal initial complexity. The backend will be built on Laravel 12, exposing a secure API for the React Native mobile client built on a production-ready starter template with modern tooling and UI patterns.

**Change Log**
| Date | Version | Description | Author |
| :--- | :--- | :--- | :--- |
| 2025-08-10 | 1.0 | Complete PRD creation and finalization. | John, PM |

---
### Section 2: Requirements
**Functional Requirements**
* **FR1:** The system shall support a multi-tenant architecture where each tenant's company data is isolated.
* **FR2:** A "Company Admin" shall be able to invite and manage users with "Admin," "Manager," and "Operator" roles.
* **FR3:** Company Admins shall have full CRUD capabilities for their company's assets.
* **FR4:** Company Admins shall be able to create and manage multi-page inspection checklists.
* **FR5:** Checklists shall support pass/fail, text input, and photo upload question types.
* **FR6:** "Inspectors" shall be able to log in to a mobile application.
* **FR7:** The mobile app shall display the correct inspection checklist for a user's assigned asset.
* **FR8:** Inspectors shall be able to complete and submit inspections via the mobile application, including a final review screen.
* **FR9:** Company Admins shall have full CRUD capabilities for a list of company suppliers.
* **FR10:** Company Admins shall be able to manually trigger an email notification to a selected supplier.
* **FR11:** Company Admins shall be able to view a dashboard of all submitted inspections.
* **FR12:** The system shall support a subscription model with trials and different billing cycles.
* **FR13:** The system must provide password reset functionality for users (user-initiated and admin-forced).

**Non-Functional Requirements**
* **NFR1:** The backend system shall be built using Laravel 12.
* **NFR2:** The mobile application shall be built using React Native.
* **NFR3:** The system architecture must be deployable on a serverless infrastructure.
* **NFR4:** The mobile application must provide basic offline support.
* **NFR5:** Core API endpoints must have a response time of less than 500ms.
* **NFR6:** The system must ensure strict data isolation between tenants.

---
### Section 3: User Interface Design Goals
**Overall UX Vision**
The platform will have a dual-UI vision. For the **Company Admin (Web Dashboard)**, the experience should feel like a powerful "mission control"—data-rich and efficient. For the **Inspector (Mobile App)**, the experience must be a lightning-fast, simple, and foolproof "field tool."

**Core Screens and Views**
* **Web Admin:** Login, Inspection Dashboard, Asset Management, Checklist Builder, Supplier Management, User Management, Branding Configuration.
* **Mobile App:** Login, Asset Selection, Inspection View, Inspection Summary, Submission Confirmation.

**Accessibility**
* **Standard:** **WCAG 2.1 Level AA**.

**Branding**
* The platform will support tenant-specific branding. Company Admins will have the ability to upload their own company logo and select a primary brand color.

**Animations and Transitions**
* The user interface will incorporate subtle and professional animations to enhance the user experience, including smooth page transitions and loading indicators.

**Target Device and Platforms**
* The platform will target **Web Responsive** for the Admin Dashboard (desktop-first) and **Cross-Platform** (iOS & Android via React Native) for the Inspector's Mobile App (mobile-first).

---
### Section 4: Technical Assumptions
* **Repository Structure:** Monorepo.
* **Service Architecture:** Serverless with a shared-database multi-tenancy model for the MVP.
* **Testing Requirements:** Unit + Integration testing using Pest.
* **Additional Technical Assumptions:** Laravel 12, Filament, React Native, MySQL/PostgreSQL, Terraform, `stancl/tenancy`.

---
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

---
#### **Epic 2: Subscription Management & Billing**
**Expanded Goal:** To integrate a complete and flexible billing system that allows the Super Admin to manage plans with custom limits and discounts, and enables Company Admins to subscribe.

* **Story 2.1: Super Admin Plan Management:** A Super Admin wants to create plans with a name, price, billing interval, data retention period, and limits (max users, max assets).
* **Story 2.2: Company Admin Subscription Flow:** A Company Admin wants to choose a plan and apply a discount code during signup.
* **Story 2.3: Subscription State Management & Billing Portal:** A Company Admin wants to view and manage their company's subscription and payment details.
* **Story 2.4: Subscription Expiry Notifications:** A Company Admin wants to receive email notifications before their trial or subscription expires.
* **Story 2.5: Discount Code Management:** A Super Admin wants to create and manage discount coupon codes.

---
#### **Epic 3: Asset and Supplier Management**
**Expanded Goal:** To enable Company Admins to populate their accounts with the essential information needed for inspections—the assets they own and the suppliers who service them.

* **Story 3.1: Asset Management (CRUD):** A Company Admin wants to create, view, update, and delete assets with type-specific, conditional fields.
* **Story 3.2: Supplier Management (CRUD):** A Company Admin wants to create, view, update, and delete supplier information.

---
#### **Epic 4: Dynamic Checklist Creation**
**Expanded Goal:** To empower Company Admins with a powerful tool to create and manage multi-page inspection templates.

* **Story 4.1: Checklist Template Management (CRUD):** A Company Admin wants to create, view, update, and delete checklist templates.
* **Story 4.2: Add and Configure Questions:** A Company Admin wants to add and configure different types of questions to a specific page within a checklist template.
* **Story 4.3: Reorder Checklist Questions:** A Company Admin wants to change the order of questions, including moving them between pages.
* **Story 4.4: Manage Checklist Pages:** A Company Admin wants to organize their checklist questions into multiple pages.
* **Story 4.5: Default Inspection Metadata:** A Company Admin wants every inspection to automatically capture key metadata (Start Time, End Time, GPS Location).

---
#### **Epic 5: End-to-End Inspection Workflow**
**Expanded Goal:** To deliver the core value proposition by enabling an inspector to complete a multi-page inspection on the mobile app, with results appearing on the admin dashboard.

* **Story 5.1: Mobile App Navigation & Asset Selection:** An Inspector wants to log in and use a clear navigation system to access their assigned assets.
* **Story 5.2: Perform Multi-Page Inspection:** An Inspector wants to fill out the multi-page checklist for their selected asset while metadata is captured automatically.
* **Story 5.3: Review and Submit Inspection:** An Inspector wants to review all their answers on a summary screen before submitting.
* **Story 5.4: Admin Dashboard - View and Action Submissions:** A Company Admin wants to see completed inspection reports on their dashboard and take action.
* **Story 5.5: View Inspection History:** An Inspector wants to view a list of their previously submitted inspections in the mobile app.

---
### Section 6: Checklist Results Report
* **Final Decision:** ✅ READY FOR ARCHITECT & UX DESIGN. The PRD is comprehensive, consistent, and provides a solid foundation for the next phases. No blocking issues were identified.

### Section 7: Next Steps
* **UX Expert Prompt:** Handoff to create the detailed UI/UX Specification.
* **Architect Prompt:** Handoff to create the comprehensive Fullstack Architecture Document.