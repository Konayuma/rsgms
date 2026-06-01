# Additional Requirements Specification (Supervisor Amendments)

This document serves as an official amendment to the **Rural Savings Group Management System (RSGMS)** project requirements, capturing the direct modifications, constraints, and operational adjustments mandated by the project supervisor (Mr. Mwiya) during the review session on May 29, 2026.

---

## 1. Scope Adjustments & Technical De-scoping
* **Single Mobile Money Gateway Focus:** Technical development efforts regarding external payment channels must be strictly confined to **one** specific gateway (e.g., Airtel Money or MTN Mobile Money API sandbox). 
* **Elimination of Multi-Channel Overhead:** Multi-channel integrations such as bank transfers or multi-wallet systems are explicitly removed from the immediate project scope to ensure deep, high-quality execution of a single secure channel instead of surface-level implementations of multiple features.
* **Sandbox Limit Constraints:** For demonstration and defense purposes, the integration can leverage a sandbox/trial version restricted to a baseline allocation (e.g., 10 testing transactions), which is entirely sufficient to demonstrate end-to-end payment workflows to the examination panel.

---

## 2. Functional Amendments & Architectural Rules

### 2.1 Member Direct-Savings Workflow (Automation Over Admin Overhead)
* **Direct Panel Ledger Entry:** Members must be given the functional capability to initiate and log savings contributions directly from their own member dashboard. 
* **Automated Timeline Restraints:** If a savings group institutes a rule restricting savings collections between a specific start and end date, the system backend must autonomously enforce this window. If a member attempts to save outside this period, the system must block the transaction and render an explicit validation error message.
* **Administrative De-escalation:** The Group Administrator must be decoupled from the manual transaction recording bottleneck. The system must process transactions automatically, reducing the administrator's operational role to viewing automatically generated transaction audits and cycle reports.

### 2.2 Capacity-Based Risk Profiling Engine
* **Dynamic Credit Scoring Layer:** Before approving or rendering loan options, the platform must dynamically evaluate the member's financial risk profile based on real-world asset metrics rather than enforcing rigid, binary rules (such as blocking a user entirely just because they have an active loan flag).
* **Proportional Capacity Borrowing:** The calculation engine must evaluate a member's net accumulated equity against their total outstanding debt obligations. For example, if a member has a substantial savings balance (e.g., K5,000) and an outstanding minor loan (e.g., K500), the system must permit concurrent additional borrowing up to their safe risk threshold.
* **Credit Capping (Micro-Lending Standard):** Mirroring established regional micro-lending systems (such as FairMoney or Zamcash), the system must calculate a safe maximum borrowable ceiling for each profile. If a user's savings-to-debt ratio indicates high structural default risk, the system must reduce or cap their accessible loan limits rather than executing a total flat-out rejection.

---

## 3. Prototype Integrity, Quality Metrics, & Sandbox Densities

### 3.1 UX Cleansing (Removal of AI Artefacts)
* **Application Authenticity Metric:** The entire system interface must be rigorously cleansed of any structural design artefacts, explicit source code badges, icons, or visual indicators that convey generic or automated AI-generation traits. Every user interface component must present a highly customized, professionally designed appearance tailored strictly to the RSGMS brand system.

### 3.2 Sandbox Seeding & Data Density
* **Interactive Profile Array:** The development database must be seeded with a robust, multi-row matrix of distinct member records far exceeding basic 3-profile placeholders. 
* **Logic Validation:** The developer must populate and simulate continuous transaction histories across a dense layout of mock users to realistically demonstrate the accuracy of the capacity-based credit-scoring algorithms during system evaluations and live panel defenses.

---

## 4. Implementation Matrix Reference
| Targeted Amendment | Affected System Component | Core Technology Layer | Operational Priority |
| :--- | :--- | :--- | :--- |
| Member Direct Savings | Member Dashboard Panel | PHP Session Processing / Frontend | High |
| Automated Save Closures | Validation Calculation Engine | Backend Date/Time Logic | High |
| Asset Risk Profiling | Loan Eligibility Engine | SQL Relational Matrix / PHP Logic | Critical |
| Mobile Money Gateway | External Sandbox API | PHP cURL / JSON Endpoint Hook | Medium |
| Visual Framework Audit | User Interface (UI) | CSS Custom Assets / Bootstrap | Critical |
| Mock Data Expansion | Database Layer | MySQL Seed Scripts / Tables | High |
