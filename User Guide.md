# RSGMS Test Users & Sequence Guide

## Users Overview

| # | Username | Password | Role | Grade | Group | Profile |
|---|----------|----------|------|-------|-------|---------|
| 1 | `admin` | `admin123` | **admin** | — | — | System Administrator (created automatically by `config/database.php`) |
| 2 | `gmwila` | `password123` | **group_admin** | — | Pamodzi Savings (PAMODZI001) | Grace Mwila — manages the group |
| 3 | `alice` | `password123` | **member** | **A** | Pamodzi Savings | Heavy saver (K4,800 in 12 contributions), no debt |
| 4 | `bob` | `password123` | **member** | **B** | Pamodzi Savings | Good saver (K2,500), repaid old loan + active loan with 1 late payment |
| 5 | `carol` | `password123` | **member** | **C** | Pamodzi Savings | Moderate saver (K1,200), active loan with late payments |
| 6 | `david` | `password123` | **member** | **E** | Pamodzi Savings | Low savings (K300), overdue loan with no repayments |
| 7 | `eve` | `password123` | **member** | **A-low** | Pamodzi Savings | New member, tiny savings (K100), no loans — Grade A but lowest credit ceiling |
| 8 | `frank` | `password123` | **member** | **B** | Pamodzi Savings | High saver (K4,200), two active loans, mostly on-time |
| 9 | `loanofficer` | `pass123` | **loan_officer** | — | Pamodzi Savings | Created via `scripts/create_loan_officer.php` |

---

## Detailed Member Profiles & Risk Grades

### Alice Banda (`alice`) — Grade A (Highest ceiling)
- **12 monthly savings** of K350–K450 = ~K4,800 total
- **No loans** ever taken
- Ideal credit profile: high savings capacity, zero debt

### Bob Chanda (`bob`) — Grade B
- **10 monthly savings** of K250 = K2,500 total
- **1 repaid loan** (K600, 3 payments, all on-time)
- **1 active loan** (K1,200, 3/6 payments made, 1 late)
- Good saver with manageable existing debt

### Carol Daka (`carol`) — Grade C
- **6 monthly savings** of K200 = K1,200 total
- **1 active loan** (K800, 2/4 payments made, 1 late)
- Moderate savings with late payment history

### David Mwale (`david`) — Grade E (Lowest)
- **3 savings** of K100 each = K300 total
- **1 loan** (K500, fully overdue, 0 repayments made)
- Highest risk: minimal savings, defaulted loan

### Eve Banda (`eve`) — Grade A (Low ceiling)
- **1 savings** of K100
- **No loans**
- New/young member: Grade A behaviourally but negligible savings => very low credit ceiling

### Frank Zulu (`frank`) — Grade B
- **12 monthly savings** of K350 = K4,200 total
- **Loan 1**: K1,000 (3/6 payments, all on-time)
- **Loan 2**: K800 (2/4 payments, 1 late)
- High saver with managed multi-loan portfolio

---

## Seeding Data

### Standard Seed (6 members + group admin)
```bash
# PHP seeder (recommended — handles password hashing dynamically)
php seed_test_data.php

# SQL seeder (requires direct MySQL access)
mysql -u root rsgms_db < seed_test_data.sql
```

### Dense Data Seed (~180 members, 3 groups)
```bash
php scripts/seed_dense_data.php
```

### Create Loan Officer Manually
```bash
php scripts/create_loan_officer.php loanofficer pass123 "John Doe" 1
php scripts/create_loan_officer.php --list-groups
```

---

## E2E Test Sequence

Tests must run **in order** (each builds on the prior state). The runner `node e2e/run.js` executes them sequentially and stops on first failure.

| Order | File | Users Logged In | What It Verifies |
|-------|------|-----------------|------------------|
| 1 | `auth.test.js` | `admin`, `alice` | Login/logout, sidebar nav, RBAC (member blocked from admin pages), invalid login error, unauthenticated redirect |
| 2 | `members.test.js` | `admin` | Members data-table renders, view member modal, page loads for savings/loans/reports/meetings |
| 3 | `savings.test.js` | `admin`, `alice` | Admin savings table, member record-savings page, member my-savings with stat cards & history |
| 4 | `loans.test.js` | `alice`, `loanofficer`, `admin` | Member loan application form, loan officer view, admin loan overview |
| 5 | `dashboard.test.js` | `admin` | Dashboard stat cards (≥3), recent transactions, quick actions, reports page (≥4 stat cards, tables, ≥2 export buttons), notifications, profile |

### Running Tests
```bash
# Run full suite (auto-starts PHP server on port 8080)
npm test
# or
node e2e/run.js

# Run individual test (requires running PHP server separately)
node e2e/auth.test.js
node e2e/members.test.js
node e2e/savings.test.js
node e2e/loans.test.js
node e2e/dashboard.test.js

# With custom port
PORT=9090 node e2e/run.js
```

---

## Manual Test Guide (Click-by-Click)

> **Prerequisites:** PHP dev server running (`php -S localhost:8080`), database seeded (`php seed_test_data.php` and `php scripts/create_loan_officer.php loanofficer pass123 "John Doe" 1`).

---

### Scenario 1: Admin Full System Tour

**Step 1 — Login**
1. Open `http://localhost:8080/login.php` in your browser
2. You see the login page with heading "RSGMS" and subheading "Sign in to your account"
3. Click the **Username** field and type `admin`
4. Click the **Password** field and type `admin123`
5. Click the **"Sign in"** button (blue, `button[type=submit].btn.btn-primary`)
6. You are redirected to the **Dashboard** (`dashboard.php`)
7. **Verify:** The page shows a greeting like "Good morning/afternoon, System Administrator!" and stat cards

**Step 2 — Verify Dashboard Stats**
1. Look at the **stat cards** across the top. Admin should see 5 cards:
   - "Total Groups" with a count
   - "Total Members" ("People tracked")
   - "Total Savings" with a K amount ("Cash position")
   - "Active Loans" with a K amount ("Credit exposure")
   - "Portfolio at Risk (PAR)" with a percentage (green/orange/red)
2. **Verify:** At least 3 stat cards are visible
3. Scroll down. Look for the **"Recent Transactions"** section with a table of recent financial activity
4. **Verify:** The table has columns: Date, Type, Amount, Description, Status
5. Look for the **"Groups & Invitation Codes"** section with group names and copy buttons
6. Look for the **"Dashboard Modes"** section with cards

**Step 3 — Sidebar Navigation**
1. Look at the **sidebar** on the left with the RSGMS logo and "v1.0"
2. Under "System" you should see: Groups, Members, Savings, Loans, Reports, Meetings
3. Below that: Notifications, Profile
4. At the bottom of the sidebar: collapse button (chevron icon) and **"Logout"** link
5. Click each nav item to verify the corresponding page loads:
   - Click **"Groups"** → `groups.php` — Group Management page with accordion cards
   - Click **"Members"** → `members.php` — Member Management page with data table

**Step 4 — Members Page**
1. Ensure you are on `members.php` (click "Members" in the sidebar if not)
2. **Verify:** The heading says "Member Management"
3. Look at the **"All Members"** table with columns: #, Full Name, Username, Email, Phone, Group, Joined, Actions
4. **Verify:** There are rows of members from the seed data
5. Click the **"View"** button on any member row (e.g., Alice Banda)
6. A **modal** pops up titled "Member Details" showing: Full Name, Username, Email, Phone, Group, Role ("Member"), Joined
7. Click the **×** (close) button on the modal to dismiss it
8. Click the **"Edit"** button on a member row — "Edit Member" modal opens
9. Click the **×** to close the modal without saving
10. Click the **"+ Add New Member"** button above the table →
    - "Add New Member" modal appears with fields: Full Name, Username, Email, Phone, Password, Assign to Group (dropdown of all groups for admin)
    - Click **×** to close (don't add now)

**Step 5 — Savings Page (Admin)**
1. Click **"Savings"** in the sidebar → `savings.php`
2. **Verify:** Heading says "Savings Management"
3. Look at the **"Savings Summary by Member"** table:
   - Columns: #, Member Name, Total Savings (K)
   - Values should be in bold green
4. Scroll to **"Recent Savings History"** table: Date, Member, Amount, Method, Recorded By
5. If data exists, a Chart.js bar chart appears under **"Member Savings Overview"**

**Step 6 — Loans Page (Admin)**
1. Click **"Loans"** in the sidebar → `loans.php`
2. **Verify:** Heading says "Loan Management"
3. Look for **"Pending Loan Approvals"** section (if any pending loans exist):
   - Table: Member, Group, Principal, Interest Rate, Total Payable, Actions
   - Actions: "View", "Approve", "Disburse" buttons
4. Scroll to **"All Loans"** section:
   - Table: Member, Principal, Total Payable, Paid, Balance, Status, Actions
   - Status badges: yellow=pending, blue=approved, green=disbursed
   - Actions: "View" (opens Loan Details modal), "Repay" (if disbursed), "Delete"
5. Click **"View"** on any loan → "Loan Details" modal shows full info
6. Close the modal

**Step 7 — Reports Page**
1. Click **"Reports"** in the sidebar → `reports.php`
2. **Verify:** Heading says "Financial Reports"
3. Look for **6 stat cards**: Total Groups, Total Members, Total Savings, Active Loans, Total Repaid, Repayment Rate
4. Scroll to **"Member Savings Report"** table: Member Name, Total Savings, Last Contribution, Contribution Count
5. Scroll to **"Loan Portfolio Report"** table: Member Name, Loan Amount, Status, Application Date, Disbursement Date
6. Scroll to **"Export Reports"** section:
   - Click **"Export to CSV"** → a CSV file downloads
   - Click **"Export to Excel"** → an XLS file downloads
7. **Verify:** At least 2 export buttons exist

**Step 8 — Meetings Page**
1. Click **"Meetings"** in the sidebar → `meetings.php`
2. **Verify:** Heading says "Meeting Management"
3. Look for the **Record Meeting Form**:
   - Meeting Date (date field, defaults to today)
   - Meeting Type (dropdown: Regular Meeting, Special Meeting, Annual General Meeting)
   - Attendance Count (number)
   - Savings Collected (K), Loans Disbursed (K), Loans Repaid (K) (number fields)
   - Meeting Minutes (textarea)
   - **"Record Meeting"** submit button
4. Scroll to **"Meeting History"** table

**Step 9 — Notifications Page**
1. Click **"Notifications"** in the sidebar → `notifications.php`
2. **Verify:** Heading says "Notifications" with subtitle
3. Look at the **"Compose"** notification section — as admin, you see "Send a notification" form:
   - Title (text), Message (textarea)
   - Recipients panel with checkboxes for each user
   - **"Select all recipients"** checkbox
   - **"Send"** button
4. Scroll to **"Your notifications"** list — shows any existing notifications

**Step 10 — Profile Page**
1. Click **"Profile"** in the sidebar → `profile.php`
2. **Verify:** Heading says "My Profile"
3. See profile header with avatar (user icon), full name, username + role, "Member since"
4. View **stats grid**: Total Savings, Contributions, Total Loans, Outstanding Balance
5. Click the **"Change Password"** tab — form with Current Password, New Password, Confirm New Password, "Change Password" button
6. Click the **"Notifications"** tab — SMS Notifications checkbox, In-app Notifications checkbox, Delivery Frequency dropdown, "Save Notification Preferences" button
7. Click **"Profile Information"** tab again — form with Username (readonly), Role (readonly), Full Name (editable), Email, Phone, "Update Profile" button

**Step 11 — Logout**
1. Click **"Logout"** at the bottom of the sidebar
2. You are redirected to the landing page (`index.php`)

---

### Scenario 2: Member — Alice Banda (Grade A)

**Step 1 — Login as Alice**
1. Go to `http://localhost:8080/login.php`
2. Type `alice` in the Username field
3. Type `password123` in the Password field
4. Click **"Sign in"**
5. You are redirected to the member dashboard

**Step 2 — Member Dashboard**
1. **Verify:** Greeting says "Good morning/afternoon, Alice Banda!"
2. Look at **member stat cards** (5 cards):
   - Group name ("Pamodzi Savings Group") with member count
   - "My Savings" with K amount ("Personal savings")
   - "My Loans" with K amount ("Borrowing")
   - "Loan Balance" with K amount ("Outstanding")
   - "Group Invitation Code" with copy button
3. Look for **"Recent Meetings"** section if any meetings exist
4. **"Recent Transactions"** table shows Alice's activity

**Step 3 — My Savings Page**
1. Click **"My Savings"** in the sidebar → `my_savings.php`
2. **Verify:** Heading says "My Savings"
3. Look at the savings form card titled **"Record Savings Contribution"**:
   - Amount (K) — number field, step=0.01
   - Contribution Date — date field, defaults to today
   - Payment Method — dropdown: "", Cash, Mobile Money, Bank Transfer, Cheque
   - Reference — text field, optional
   - **"Record Savings"** submit button (class `.btn-save`)
4. Look at the **stats grid** (4 cards):
   - Total Savings (K amount)
   - Total Contributions (count)
   - Regular Contribution (K, from group settings)
   - Active Months (count)
5. **Verify:** At least some stat cards are visible
6. Scroll down to **"Monthly Savings Summary"** — Chart.js bar chart or "No savings data yet"
7. Scroll to **"Savings Contribution History"** table: Date, Amount, Payment Method, Reference
8. **Verify:** Table has Alice's 12 contribution records
9. Scroll to **"Savings Goals"** section showing current balance

**Step 4 — Record a Savings Contribution**
1. In the "Record Savings Contribution" form, type `250` in **Amount (K)**
2. Leave **Contribution Date** as today's date
3. Click the **Payment Method** dropdown and select **"Cash"**
4. Click **"Record Savings"** button
5. **Verify:** The page refreshes, a success message or toast appears, and the stat cards update

**Step 5 — My Loans Page**
1. Click **"My Loans"** in the sidebar → `my_loans.php`
2. **Verify:** Heading says "My Loans"
3. Look at the top-right **"Apply for Loan"** button (`.btn-apply`, links to `new_loan.php`)
4. View the **stats grid** (4 cards):
   - Total Loans (K)
   - Total Paid (K)
   - Outstanding Balance (K)
   - Active Loans (count)
5. **Verify:** As Alice has no loans, the values should be K0.00 or "0"
6. Scroll to **"My Loan History"** table — should be empty for Alice

**Step 6 — New Loan Application (Risk Profile Test)**
1. Click the **"Apply for Loan"** button → `new_loan.php`
2. **Verify:** Heading says "New Loan Application"
3. Look for the **Risk Profile Card** (`#riskProfileCard`) — it loads automatically via AJAX:
   - Shows "Alice Banda" name and "Pamodzi Savings Group"
   - **Expected:** Grade badge shows **"GRADE A"** with a high score (e.g., "SCORE 8.5")
   - 4 metrics grid:
     - Historical Savings: K~4,800
     - Outstanding Loans: K0.00
     - Net Collateral Equity: positive
     - Repayment Compliance: 100%
   - **Capacity-Driven Eligibility Ceiling** — a Safe Borrowing Limit amount
4. Below the risk card, the **"Apply for Loan"** form:
   - Member name (static, read-only)
   - **Loan Amount (K)** — number field (`#principal`), step=0.01, its `max` is set by the risk profile
   - **Interest Rate (%)** — readonly, pulled from group settings (10%)
   - **Repayment Period** — dropdown: "-- Select Period --", 1 month, 3 months, 6 months, 12 months
   - **Repayment Frequency** — dropdown: Monthly, Weekly
5. Type `500` in the **Loan Amount (K)** field
6. Select **"6 months"** from the Repayment Period dropdown
7. **Verify:** The **Loan Calculation Preview** section appears below showing:
   - Principal Amount: K500.00
   - Interest Amount: K50.00
   - Total Payable: K550.00
   - Monthly Payment: K91.67
8. **Verify:** The **"Submit Loan Application"** button is enabled (not greyed out)
9. (Optional) Click **"Submit Loan Application"** to actually submit, or use the back link to cancel

---

### Scenario 3: Member — Bob Chanda (Grade B)

1. **Logout** from Alice (click "Logout" in sidebar)
2. Login as `bob` / `password123`
3. Go to **My Loans** → verify stats show active loan (K1,200) with outstanding balance
4. Click **"Apply for Loan"** → `new_loan.php`
5. **Verify Risk Profile Card shows:**
   - **Grade B** badge with moderate score
   - Historical Savings: K2,500
   - Outstanding Loans: K~600+ balance (active loan)
   - Repayment Compliance: ~85% (1 late out of 6 payments)
   - Safe Borrowing Limit lower than Alice's
6. Check the principal field's `max` attribute is set below Alice's limit

---

### Scenario 4: Member — Carol Daka (Grade C)

1. Logout, login as `carol` / `password123`
2. **My Savings** → verify 6 contributions of K200 = K1,200
3. **My Loans** → verify active loan (K800) with balance
4. Click **"Apply for Loan"** → `new_loan.php`
5. **Verify Risk Profile:**
   - **Grade C** badge (yellow/warning color)
   - Historical Savings: lower
   - Repayment Compliance: ~50% (1 late out of 2 payments)
   - Safe Borrowing Limit significantly lower

---

### Scenario 5: Member — David Mwale (Grade E)

1. Logout, login as `david` / `password123`
2. **My Savings** → verify only 3 contributions of K100 = K300
3. **My Loans** → verify overdue loan of K500 with full balance, no repayments
4. Click **"Apply for Loan"** → `new_loan.php`
5. **Verify Risk Profile:**
   - **Grade E** badge (red/danger color)
   - Outstanding Loans: K500 overdue (likely defaulted status)
   - Repayment Compliance: 0%
   - Safe Borrowing Limit: very low or K0 (loan application may be blocked)
6. The **"Submit Loan Application"** button should be **disabled** with a warning message

---

### Scenario 6: Member — Eve Banda (Grade A, Low Ceiling)

1. Logout, login as `eve` / `password123`
2. **My Savings** → verify 1 contribution of K100
3. **My Loans** → no loans, all zeros
4. Click **"Apply for Loan"** → `new_loan.php`
5. **Verify Risk Profile:**
   - **Grade A** badge (green) — paradoxically high grade
   - Historical Savings: only K100
   - Safe Borrowing Limit: very low (despite Grade A, the ceiling is tiny due to negligible savings)
   - This demonstrates the **capacity-driven** aspect: grade is A, but credit ceiling is low

---

### Scenario 7: Member — Frank Zulu (Grade B)

1. Logout, login as `frank` / `password123`
2. **My Savings** → verify 12 contributions of K350 = K4,200
3. **My Loans** → verify 2 active loans:
   - Loan 1: K1,000 (3/6 payments made)
   - Loan 2: K800 (2/4 payments made)
4. Click **"Apply for Loan"** → `new_loan.php`
5. **Verify Risk Profile:**
   - **Grade B** badge
   - Historical Savings: K4,200 (high)
   - Outstanding Loans: significant combined balance
   - Repayment Compliance: ~83% (1 late out of 5 total payments)
   - Safe Borrowing Limit: moderate (good savings but high existing debt)

---

### Scenario 8: Loan Officer Workflow

**Prerequisite:** Loan officer must be created first:
```bash
php scripts/create_loan_officer.php loanofficer pass123 "John Doe" 1
```

1. Logout, login as `loanofficer` / `pass123`
2. **Verify:** Dashboard greeting shows "Good afternoon, John Doe!"
3. **Verify:** Loan officer stat cards show:
   - "Pending Loans" — "Awaiting review"
   - "Active Loans" — "Credit exposure"
   - "Total Repaid" — "Repayments"
   - "Overdue Loans" — "At risk"
4. **Sidebar:** Only shows Members, Loans, Reports (under their role section) + Notifications, Profile
5. Click **"Members"** → member data table is visible (read-only, no Edit/Delete buttons)
6. Click **"Loans"** → `loans.php`
   - **"Pending Loan Approvals"** section (if any pending loans exist)
   - Click **"View"** on a pending loan → Loan Details modal
   - Click **"Approve"** to approve a loan → page refreshes, status changes to approved
   - Click **"Disburse"** to disburse → status changes to disbursed
   - For disbursed loans, click **"Repay"** → Repayment modal with:
     - Shows member name + loan balance
     - "Member Savings Wallet" balance
     - Amount (K), Payment Date, Payment Source (Cash, Mobile Money, Bank Transfer, Member's Savings Wallet)
     - Click **"Record Repayment"** to submit
7. Click **"Reports"** → reports page with all 6 stat cards and export buttons

---

### Scenario 9: Group Admin — Grace Mwila

1. Logout, login as `gmwila` / `password123`
2. **Dashboard:**
   - Stat cards: Group Name with member count, Total Savings, Active Loans, Interest Rate, Portfolio at Risk (PAR), Invitation Code with "Copy" button
   - **"Quick Actions"** section: "View Reports", "Manage Meetings"
3. **Sidebar:** Shows "My Group" section with: Group Settings, Members, Savings, Loans, Reports, Meetings
4. Click **"Group Settings"** → `group_settings.php`
   - **Verify:** Heading with group name, stats row (Members, Pending, Savings, Active Loans)
   - **Invitation Code** card: large code + "Copy" button + "Generate New Code" button
   - **Group Details** card: form to edit group name, description, interest rate, penalty rate, meeting day, contribution amount
5. Click **"Members"**:
   - **Invitation Card** at top showing the group code
   - **"Pending Approvals"** section if any members are waiting (with "Approve" button per row)
   - **"All Members"** table with Edit/Delete/View buttons — group admin has full member management
6. Click **"Savings"** → same savings management as admin (but scoped to group)
7. Click **"Loans"** → same loan management (scoped to group)
8. **Meetings** → record meeting form + meeting history table

---

### Scenario 10: Join Group Flow (Pending Member)

**Prerequisite:** Register a new member via `register.php` or `signup.php` first.

1. Go to `http://localhost:8080/signup.php`
2. Fill in: Full Name, Username, Email, Phone, Password, Confirm Password
3. Click **"Sign Up"** button
4. You are redirected to `join_group.php` with the message "Join a savings group" and instruction to enter invitation code
5. The group's invitation code is visible on the **group admin's dashboard** or **Group Settings** page (login as `gmwila` to find it)
6. Type the 6-digit code in the input field and click **"Join group"**
7. You are redirected to the dashboard with a **"Pending Approval"** stat card
8. Login as `gmwila` (group admin), go to **Members** → **"Pending Approvals"** section
9. Click **"Approve"** on the new member
10. Login as the new member again — now the dashboard shows full member stat cards

### Expected Risk Grade Display

When viewing `new_loan.php` as each member:

| Member | Grade | Credit Ceiling | Key Driver |
|--------|-------|----------------|------------|
| `alice` | **A** | High (K2,000+) | 12 savings, no debt |
| `bob` | **B** | Moderate | Good savings, active loan with 1 late |
| `carol` | **C** | Low | Low savings, late payments |
| `david` | **E** | K0 / blocked | Overdue loan, 0% compliance |
| `eve` | **A** | Very low | Grade A but only 1 savings (K100) |
| `frank` | **B** | Moderate | High savings, high debt, 1 late |

**Note:** The `max` attribute on the Loan Amount input is dynamically set to the Safe Borrowing Limit from the risk engine. If amount exceeds the limit, a warning appears and the Submit button is disabled.

---

## Quick Reference Card

| Action | Credentials |
|--------|-------------|
| Admin login | `admin` / `admin123` |
| Group admin login | `gmwila` / `password123` |
| Loan officer login | `loanofficer` / `pass123` |
| Member login (shared password) | `<username>` / `password123` |
| Dense-data member login | `pamodzi_savings_m1` / `password123` (etc.) |
| Database | `rsgms_db` on `localhost`, user `root`, no password |
| PHP server | `php -S localhost:8080` from project root |
| E2E test runner | `npm test` (or `node e2e/run.js`) |
