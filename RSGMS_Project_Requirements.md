# Project Requirements: Rural Savings Group Management System (RSGMS)

This document specifies the project requirements for the **Rural Savings Group Management System (RSGMS)**, a web-based solution designed to improve financial inclusion, record-keeping accuracy, and operational transparency for community-based savings groups (such as Village Savings and Loan Associations - VSLAs) in rural Zambia, specifically targeting the Ikelenge community.

---

## 1. Project Overview & Scope
* **Target Audience:** Rural savings groups (e.g., Pamodzi Savings Group in Ikelenge) characterized by manual paper-based record-keeping, limited digital literacy, and low-bandwidth connectivity.
* **Platform Type:** Purpose-built, responsive three-tier web application accessible via standard web browsers on any internet-capable device (including basic smartphones) without software installation.
* **Exclusions:** The system explicitly excludes the complex operational, compliance, and accounting workflows of formal microfinance institutions, commercial banks, and large investment cooperatives.

---

## 2. System Actors & Roles
The system implements strict Role-Based Access Control (RBAC) across four primary user categories:

1. **Group Administrator**
   * Responsible for day-to-day savings group management.
   * Registers new members and updates profiles.
   * Records savings contributions and approves transactions.
   * Generates group-level financial reports.
2. **Savings Group Member**
   * Accesses a personalized dashboard.
   * Views personal savings balances, outstanding loan positions, and transaction histories.
   * Submits loan applications.
3. **Loan Officer**
   * Manages the complete loan lifecycle (reviews applications, tracks repayments, assesses penalties).
   * Generates loan portfolio summaries and reports.
4. **System Administrator**
   * Handles platform configuration, global user account control, security policies, and system maintenance.

---

## 3. Functional Requirements

### 3.1 Member Savings Management Module
* **Registration:** Enable Group Administrators to capture, update, and retrieve member profiles (including name, phone number, role, group ID, and account type).
* **Contribution Recording:** Capture savings contribution amounts and dates through a simplified digital form interface.
* **Automated Calculations:** Automatically calculate and update individual savings balances in real time upon form submission.
* **Audit Trail:** Maintain a complete, unalterable transaction history providing members independent access to verify their entire savings record.

* **Self-Service Contributions:** Allow individual members to record their own savings contributions directly via their personal dashboard without requiring an administrator to enter the transaction. Self-service entries must use the same validation rules, update routines, and audit logging as admin-entered contributions.

* **Recurring / Automatic Savings:** Provide an optional feature for members to configure recurring automatic savings (e.g., weekly, monthly) that the system records automatically according to the schedule. The UI must make it clear how to opt-in, change cadence, and opt-out.

* **Member Access to Savings Records:** Explicitly ensure members can view, search, and export their full savings history, contribution receipts, and current balance from their dashboard in a readable format optimized for low-literacy users.

### 3.2 Automated Loan Management Module
* **Loan Application Workflow:** Allow members or officers to submit applications, transiting through standard states: *Applied → Under Review → Approved/Rejected → Disbursed → Active → Settled* (or *Overdue*).
* **Capacity-Driven Credit Limits:** Run an automated risk-profiling query before generating loan eligibility amounts. Instead of flat rejection when outstanding loan flags exist, evaluate total historical savings capacity against requested cash volumes and compute a dynamic eligible ceiling.
* **Risk Score Bounds:** Apply bounded risk-score tiers to cap or minimize selectable disbursement capacity when baseline savings equity is low relative to structural risk bounds, matching safe modern micro-lending credit margin behavior.
* **Interest Calculation Engine:** Compute interest amounts automatically using group-defined mathematical parameters (including compound interest applications).
* **Repayment Tracking:** Record repayments against an active loan, automatically reduce outstanding principal, and update the loan lifecycle status.
* **Penalty Assessment:** Apply automated penalty calculations and flags to overdue accounts when repayment dates pass without recorded payments.
* **Repayment Schedules:** Generate clear repayment schedules and display them on the member's dashboard immediately upon loan approval.

### 3.3 Financial Reporting & Transparency Module
* **Automated Reports:** Generate the following summaries automatically from live transactional data:
  * Group-level aggregate savings reports.
  * Individual member savings statements.
  * Loan portfolio summaries and risk tracking.
  * Meeting preparation documents and end-of-period financial statements.
* **Personalized Dashboards:** Provide real-time financial visibility to members regarding their personal savings balances and active loan balances to foster community trust.

### 3.4 Notification & External Interfaces
* **Reminders:** Schedule and trigger automated loan repayment reminders.
* **Communication:** Integrate with external SMS notification services to send alerts to members.

* **Selectable Notification Recipients:** Provide UI controls for administrators and officers to select specific members (or groups of members) to receive notifications rather than relying on ambiguous "highlighting" interactions. Selection must support multi-select, group selection, and "select all" semantics; chosen recipients should be clearly shown before sending.

* **Member Notification Preferences:** Allow members to set and manage their notification preferences (SMS, in-app, frequency) and opt-out where legally required. Notifications UI must be usable on low-bandwidth devices and not rely on visual highlighting as the sole selection mechanism.

---

## 4. Non-Functional Requirements

### 4.1 Usability & Accessibility
* **Low-Literacy Optimization:** Design a simplified visual user interface with minimal text and intuitive navigation tailored for users with varying formal education levels.
* **Onboarding Metric:** New users with no prior system training must be able to complete primary operational workflows within ten (10) minutes of first access, achieving a minimum 80% task completion rate during usability tests.

### 4.2 Technical Performance
* **Low-Bandwidth Efficiency:** Code and queries must be optimized for lightweight performance under 2G and 3G mobile data connections typical of rural network environments.
* **Response Benchmarks:**
  * Savings contribution recording to confirmation flow must average $\le 2.1$ seconds.
  * Loan application processing and schedule rendering must average $\le 2.3$ seconds.
  * Automated financial report generation must average $\le 2.7$ seconds.

### 4.3 Security & Privacy
* **Access Control:** Enforce backend role-based access control (RBAC) to ensure no user can access unauthorized data or functions (e.g., preventing members from seeing other members' private records).
* **Data Protection:** Encrypt all data in transit using HTTPS protocols. Apply industry-standard encryption algorithms to stored sensitive member financial records.
* **Configuration Security:** Protect background system operational files and credential configurations by storing them strictly outside the web root directory.

### 4.4 Reliability & Quality Attributes
* **Uptime:** The platform must achieve a minimum of 99.0% system availability (uptime) measured across monthly intervals.
* **Database Integrity:** Maintain data persistence and relational integrity across multi-table operations (e.g., contribution updates affecting transaction logs and account tables simultaneously) with zero data loss tolerance.
* **Scalability:** The system architecture must support seamless growth from initial community pilots (e.g., Pamodzi Savings Group) to expanded multi-group membership scales without requiring foundational architectural modifications.

---

## 5. Technology Stack & Environment Blueprint
* **Frontend Layer:** HTML5, CSS3, JavaScript, Bootstrap UI framework.
* **Server-Side Backend Layer:** PHP (handling operational rules, calculations, and RBAC).
* **Database Persistence Layer:** MySQL Relational Database.
* **Local Development Environment:** WAMP Stack (Windows, Apache, MySQL, PHP), MySQL Workbench, Visual Studio Code.
* **Version Control:** Git & GitHub utilizing a structured branching model (separating feature, integration, and production environments).
* **Production Deployment:** Cost-effective Shared Linux Web Hosting environment running Apache, PHP, and MySQL.

---

## 6. Future Enhancement Roadmap
The following requirements are deferred to future development iterations:
1. **Offline Capability:** Implement a local-first offline synchronization framework for core savings and balance records to mitigate rural network dropouts.
2. **Multilingual Interface:** Integrate localization support for native Zambian languages (specifically Kaonde and Luvale).
3. **Mobile Wallet Integration:** Implement API hooks to connect directly with major mobile money providers (e.g., Airtel Money, MTN Mobile Money) to automate ledger entries via direct wallet transfers.
4. **Native Mobile App:** Build a native mobile application featuring push notifications for reminders to remove reliance on traditional SMS gateways.
