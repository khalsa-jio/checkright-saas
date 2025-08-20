# 4.4. Backend Architecture (Laravel)
* **Service Architecture:** The application will be architected for a serverless environment (e.g., AWS Lambda via Laravel Vapor), with code organized by domain.
* **Data Access Layer:** The Repository Pattern will be implemented to abstract all database queries, making the application highly testable.
* **Authentication:** Laravel Sanctum will protect API routes, while standard session auth will protect the Filament web panel. `stancl/tenancy` middleware will be applied globally to scope all requests.
