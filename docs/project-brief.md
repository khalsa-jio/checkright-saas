# Project Brief: CheckRight

### Section 1: Executive Summary
The project is to create "CheckRight," a comprehensive, multi-tenant SaaS platform designed to replace inefficient, paper-based vehicle and machinery pre-start inspection processes. The primary problem this solves is the lack of real-time visibility, poor data management, and compliance tracking challenges that companies face. The target market includes industries such as logistics, construction, manufacturing, and agriculture, where regular equipment checks are mandatory. The key value proposition is to significantly increase operational efficiency, improve safety compliance, and provide actionable, real-time insights into asset condition, while also streamlining the maintenance workflow by directly connecting asset issues to pre-approved suppliers and mechanics.

### Section 2: Problem Statement
Companies managing vehicle fleets or heavy machinery are fundamentally constrained by outdated, manual inspection processes that create significant operational friction and risk. This manifests as distinct, costly pain points across the organization:
* **For Field Operators/Inspectors:** The process is tedious and prone to error, with no direct line of communication to initiate a maintenance request.
* **For Fleet & Operations Managers:** They operate in a reactive state, often learning about critical asset failures hours or days late, leading to prolonged asset downtime.
* **For Compliance & Safety Officers:** They face a significant administrative burden, manually collecting and filing paperwork to prepare for safety audits, risking substantial fines and legal liability.
* **For Business Owners:** The inability to analyze inspection data means they are missing out on crucial business intelligence for predictive maintenance and trend analysis.

Existing solutions fall short as they are mere digital forms, lacking the intelligence for predictive maintenance, the automation for one-click compliance reporting, or the integrated workflows to manage maintenance with suppliers. This leaves a clear and urgent market gap for a specialized, intelligent, and accessible platform.

### Section 3: Proposed Solution
The proposed solution is "CheckRight," a full-stack, multi-tenant SaaS platform that provides an end-to-end, intelligent system for managing vehicle and machinery inspections. The approach is to create a unified ecosystem consisting of a central web dashboard for administrators and a cross-platform mobile app for field operators. The platform will be built on a modern, serverless architecture to ensure scalability and cost-efficiency.

This solution will succeed by moving beyond simple data collection to become a comprehensive operational tool. It addresses the entire inspection lifecycle—from dynamic form creation to issue identification, resolution via supplier integration, and deep data analysis. By offering advanced, high-value features like predictive maintenance and automated compliance reporting, "CheckRight" will provide a more compelling and focused solution than either generic form-builders or overly complex enterprise systems.

### Section 4: Target Users
**Primary User Segments**
1.  **The Operations/Fleet Manager (Company Admin):** Manages a fleet for a small to medium-sized business. Needs real-time visibility, simple compliance tracking, and efficient maintenance scheduling to reduce downtime and costs.
2.  **The Field Operator/Inspector:** The hands-on driver or machinery operator. Needs a fast, simple, and unambiguous mobile tool to complete checks quickly and have a clear digital record.

**Secondary User Segment**
3.  **The Owner-Operator / Self-Employed Driver:** An individual who owns and operates their own commercial vehicle. Needs an all-in-one solution to track inspections, maintenance, and expenses for compliance and business records.

### Section 5: Goals & Success Metrics
**Business Objectives**
* **Customer Acquisition:** Onboard 50 paying companies and 100 self-employed drivers within the first 6 months post-launch.
* **Establish Market Fit:** Achieve a Net Promoter Score (NPS) of 40+ from our active user base within the first year.

**User Success Metrics**
* **Reduced Administrative Time:** A measured 50% reduction in the time Company Admins spend managing inspection paperwork and preparing for audits.
* **Increased Compliance Rate:** Achieve and maintain a 98% on-time inspection completion rate for all active assets on the platform.

### Section 6: MVP Scope (Finalized)
**Core Features (Must Have for MVP)**
* Core Tenancy & User Management (Shared-Database Model)
* Asset Management (CRUD)
* Dynamic Checklist Builder
* Mobile Inspection Workflow
* Supplier Management (CRUD)
* Manual Maintenance Notification via Email
* Basic Inspection Dashboard

**Out of Scope for MVP**
* Dedicated Database for Enterprise Plan
* AI-Powered Features
* Predictive Maintenance & Advanced Analytics
* Automated Compliance/Audit Reports
* Real-time GPS Tracking
* Marketing Website CMS
* Gamification and Operator Scores

### Section 7: Post-MVP Vision
* **Phase 2:** Automated Compliance Reports, GPS Tracking, Gamification.
* **Long-term Vision (1-2 Years):** Launch Enterprise Plan (Dedicated DBs), Predictive Maintenance Engine, AI-Powered Onboarding.
* **Expansion Opportunities:** Deeper Supplier Integration, Integration Marketplace, New Verticals.

### Section 8: Technical Considerations
* **Platforms:** Responsive Web App (Admins), Cross-platform Mobile App (iOS/Android for Inspectors).
* **Technology Preferences:** Laravel 12 backend (with specified packages), React Native mobile app, Filament/Livewire for web dashboards.
* **Infrastructure:** Serverless architecture, starting on a cost-effective cloud provider (e.g., DigitalOcean) with Terraform for IaC.
* **Architecture:** Shared-database multi-tenancy for MVP using `stancl/tenancy`. A monorepo structure should be considered.

### Section 9: Constraints & Assumptions
* **Constraints:** Cost-effectiveness is a primary driver. The development team is assumed to be small, and the tech stack is constrained to the Laravel/React Native ecosystem.
* **Key Assumptions:** There is a market willingness to pay for this solution; the mobile app will be preferred over paper; the chosen tech stack is feasible; the data collected will be valuable for future features.

### Section 10: Risks & Open Questions
* **Key Risks:** Competition from established players like EROAD and global platforms like SafetyCulture; technical complexity of the multi-tenancy architecture; potential for scope creep.
* **Open Questions:** Optimal pricing strategy to compete effectively; specific NZ compliance workflows (e.g., WOF-style checks) most valuable for the MVP.

### Section 11: Next Steps
* **Immediate Actions:** Finalize this brief, hand off to PM to create the PRD, and hand off to UX Expert for UI/UX specification.
* **PM Handoff:** This brief provides the full context to begin creating the Product Requirements Document (PRD), paying special attention to the MVP scope and the defined pricing model.

### Appendix A: Market Research Summary
The provided market research indicates a fragmented local NZ market with direct competitors like EROAD Inspect (fleet-focused, regulation-compliant) and adjacent global players like SafetyCulture (iAuditor) (highly customizable but not specific to NZ vehicle compliance). A strategic gap exists for a SaaS product that is both highly customizable and tailored to NZ regulatory standards, such as those set by VTNZ. Key differentiators will be the flexible tenancy model, deep inspection workflow customization, and asset-specific notifications and analytics.