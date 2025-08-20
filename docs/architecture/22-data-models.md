# 2.2. Data Models
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
