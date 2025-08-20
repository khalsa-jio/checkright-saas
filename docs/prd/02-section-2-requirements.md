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

***
