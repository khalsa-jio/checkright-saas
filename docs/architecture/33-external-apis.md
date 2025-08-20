# 3.3. External APIs
* **Stripe:** Used for all payment and subscription processing. Integration is handled server-side via Laravel Cashier, including listening for critical webhooks.
* **Amazon SES:** Used for all transactional emails (invitations, notifications, etc.). Integration is handled server-side via Laravel's native mail driver.
