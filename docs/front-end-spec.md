# UI/UX Specification: CheckRight

### Section 1: Overall UX Goals & Principles
**Target User Personas**
* **Primary (Web):** The Operations/Fleet Manager, who needs power and efficiency.
* **Primary (Mobile):** The Field Operator, who needs speed and simplicity.

**Usability Goals**
* **Efficiency of Use:** The mobile inspection workflow must be completable in under two minutes. The web admin dashboard should allow managers to find a specific report in under three clicks.
* **Ease of Learning:** A new Company Admin should be able to create their first asset and build a basic checklist without needing a tutorial.
* **Error Prevention:** The design will proactively prevent errors, with clear confirmation steps for destructive actions (like deleting an asset) and robust handling of mobile offline synchronization.

**Design Principles**
1.  **Clarity Above All:** The interface will be clean, uncluttered, and use clear language.
2.  **Efficiency is Key:** Every workflow will be optimized to require the minimum number of taps and cognitive load.
3.  **Feedback is Instant:** Every user action will provide immediate and clear visual feedback.
4.  **Accessible by Default:** We will adhere to WCAG 2.1 Level AA standards.

---
### Section 2: Information Architecture (IA)
**Web Admin Dashboard Site Map**
```mermaid
graph TD
    A[Login] --> B(Dashboard);
    B --> B1[Upcoming Expiries Widget]
    B --> B2[Recent Inspections List]
    B --> C[Assets];
    C --> C1{List / Create};
    C1 --> C2[Edit Asset];
    B --> D[Checklists];
    D --> D1{List / Create};
    D1 --> D2[Checklist Builder];
    B --> E[Suppliers];
    E --> E1{List / Create};
    E1 --> E2[Edit Supplier];
    B --> F[Users];
    F --> F1{List / Invite};
    F1 --> F2[Edit User];
    B --> G[Settings];
    G --> G1[Branding Config];
