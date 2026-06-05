# RSGMS — Rural Savings Group Management System

> **Version:** v1.0  
> **Institution:** Zambia University College of Technology  
> **Target:** Rural community savings groups (VSLAs) in Zambia  
> **Stack:** PHP 8.2 · MySQL/PostgreSQL · Apache · HTML5/CSS3/JS

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [System Architecture](#2-system-architecture)
3. [Technology Stack](#3-technology-stack)
4. [Database Schema](#4-database-schema)
5. [Role-Based Access Control](#5-role-based-access-control)
6. [Features](#6-features)
7. [Risk Profiling Engine](#7-risk-profiling-engine)
8. [Requirements](#8-requirements)
9. [Default Accounts](#9-default-accounts)
10. [Deployment](#10-deployment)
11. [Testing](#11-testing)

---

## 1. System Overview

RSGMS is a purpose-built, responsive web application for managing community-based savings groups (Village Savings and Loan Associations — VSLAs) in rural Zambia. It replaces manual paper-based record-keeping with a digital platform that handles:

- Member and group registration with invitation codes
- Savings contribution recording (admin and self-service)
- Automated recurring savings scheduling
- Loan lifecycle management with capacity-driven risk profiling
- Meeting recording and attendance tracking
- Financial reporting with CSV/Excel export
- In-app notification system with real-time polling

---

## 2. System Architecture

### 2.1 Three-Tier Architecture

```
┌─────────────────────────────────────────────────────┐
│                   PRESENTATION TIER                  │
│  HTML5 · CSS3 (OKLCH Design System) · Vanilla JS     │
│  Chart.js · Font Awesome · Custom Design System      │
│  Responsive (desktop sidebar / mobile overlay)       │
├─────────────────────────────────────────────────────┤
│                   APPLICATION TIER                   │
│  PHP 8.2 (No framework — vanilla PDO)                │
│  ┌──────────────┐  ┌──────────────────────────┐     │
│  │  Auth Layer  │  │  Business Logic Layer     │     │
│  │  · Session   │  │  · Risk Engine            │     │
│  │  · RBAC      │  │  · Savings Helpers        │     │
│  │  · CSRF      │  │  · Validation Engine      │     │
│  │  · Rate Lim  │  │  · Notification Dispatch  │     │
│  └──────────────┘  └──────────────────────────┘     │
│  ┌──────────────────────────────────────────────┐   │
│  │         CRON / Background Jobs               │   │
│  │  · run_recurring_savings.php                  │   │
│  │  · run_auto_loan_deductions.php               │   │
│  └──────────────────────────────────────────────┘   │
├─────────────────────────────────────────────────────┤
│                   DATA TIER                         │
│  PDO Abstraction Layer                              │
│  ┌──────────────┐  ┌──────────────────────────┐    │
│  │   MySQL 8    │  │  PostgreSQL 16            │    │
│  │              │  │  (Docker default)         │    │
│  └──────────────┘  └──────────────────────────┘    │
└─────────────────────────────────────────────────────┘
```

### 2.2 Application Flow

```
index.php (landing page)
  ├── login.php ─────────────────────────────────────┐
  │    ├── register.php (group registration)          │
  │    └── signup.php (member signup)                 │
  │         └── join_group.php (invitation code)      │
  │                                                   │
  └── dashboard.php (role-scoped)                     │
       ├── my_savings.php        (member)             │
       ├── my_loans.php          (member)             │
       ├── new_loan.php          (member)             │
       ├── statements.php        (member)             │
       ├── members.php           (admin/gA/lO)        │
       ├── savings.php           (admin/gA)           │
       ├── loans.php             (admin/gA/lO)        │
       ├── reports.php           (admin/gA/lO)        │
       ├── meetings.php          (admin/gA)           │
       ├── groups.php            (admin)              │
       ├── group_settings.php    (group_admin)        │
       ├── notifications.php     (all)                │
       ├── profile.php           (all)                │
       └── logout.php            (all)                │
```

### 2.3 Key Design Decisions

| Decision | Rationale |
|---|---|
| No framework (vanilla PHP) | Academic requirement; demonstrates low-level proficiency |
| Dual MySQL/PostgreSQL support | PDO abstraction with dialect-specific SQL via `sqlDateAdd()` / `sqlDateFormat()` helpers |
| Auto-schema creation | `config/database.php` creates all tables on first connection — zero manual migration steps |
| Session-based auth + RBAC | Simple, effective for monolithic PHP; `requireRole()` enforces on every page |
| CSRF tokens per session | `hash_equals()` comparison prevents timing attacks |
| OKLCH color space in CSS | Perceptually uniform colors; earthy Zambian-inspired palette (clay, cream, gold, moss, sand) |

---

## 3. Technology Stack

### 3.1 Frontend

| Component | Technology |
|---|---|
| Markup | HTML5 |
| Styling | CSS3 with custom properties (OKLCH color space) |
| Fonts | Fraunces (serif) + Sora (sans-serif) via Google Fonts |
| Icons | Font Awesome 6 (free) |
| Charts | Chart.js 4.x (bundled locally) |
| Responsive | CSS media queries + localStorage sidebar state |

### 3.2 Backend

| Component | Technology |
|---|---|
| Language | PHP 8.2 |
| Database Access | PDO (prepared statements) |
| Auth | Session-based with bcrypt password hashing |
| Validation | Custom `Validation` class (field rules, sanitization) |
| Risk Engine | Custom `getMemberRiskProfile()` in `config/risk_engine.php` |
| Cron Jobs | Two PHP scripts for recurring savings and auto loan deductions |

### 3.3 Database

| Feature | MySQL | PostgreSQL |
|---|---|---|
| Enum constraints | `ENUM` type | `VARCHAR + CHECK` |
| Auto-increment | `INT AUTO_INCREMENT` | `SERIAL` |
| Indexes | `INDEX` | `CREATE INDEX IF NOT EXISTS` |
| Timestamp trigger | `ON UPDATE CURRENT_TIMESTAMP` | Custom `update_updated_at()` trigger function |
| Date arithmetic | `DATE_ADD()` | `INTERVAL` cast |

### 3.4 Infrastructure

| Environment | Setup |
|---|---|
| Docker | Apache + PHP 8.2 + PostgreSQL 16 (`docker-compose.yml`) |
| Cloud (Render) | Docker-based deployment (`render.yaml`) |
| Local | Any PHP-capable server (XAMPP, WAMP, LAMP) |

---

## 4. Database Schema

The system defines **10 tables**, auto-created in `config/database.php:createTablesMysql()` / `createTablesPgsql()`.

### 4.1 Entity Relationship Overview

```
 users ───< savings_contributions
   │    ───< loans ───< loan_repayments
   │    ───< notifications
   │    ───< notification_preferences
   │    ───< recurring_savings
   │    ───< risk_mitigations
   │    │
   └───> savings_groups ───< meetings
                          ───< transactions
                          ───< savings_contributions
                          ───< loans
                          ───< recurring_savings
```

### 4.2 Table: `users`

Stores all system users — members, group admins, loan officers, and super admins.

| Column | Type (MySQL) | Type (PostgreSQL) | Constraints |
|---|---|---|---|
| `id` | `INT AUTO_INCREMENT` | `SERIAL` | PRIMARY KEY |
| `username` | `VARCHAR(50)` | `VARCHAR(50)` | UNIQUE, NOT NULL |
| `password` | `VARCHAR(255)` | `VARCHAR(255)` | bcrypt hash |
| `full_name` | `VARCHAR(100)` | `VARCHAR(100)` | NOT NULL |
| `email` | `VARCHAR(100)` | `VARCHAR(100)` | nullable |
| `phone` | `VARCHAR(20)` | `VARCHAR(20)` | nullable |
| `role` | `ENUM('admin','group_admin','loan_officer','member')` | `VARCHAR(20)` + CHECK | DEFAULT 'member' |
| `group_id` | `INT` | `INT` | FK → savings_groups.id |
| `status` | `ENUM('active','pending')` | `VARCHAR(10)` + CHECK | DEFAULT 'active' |
| `last_login` | `DATETIME` | `TIMESTAMP` | nullable |
| `created_at` | `TIMESTAMP` | `TIMESTAMP` | DEFAULT CURRENT_TIMESTAMP |

**Indexes:** `idx_users_status` on `status`.

### 4.3 Table: `savings_groups`

Represents a single rural savings group with its configuration and aggregated financial data.

| Column | Type | Constraints |
|---|---|---|
| `id` | `INT AUTO_INCREMENT` / `SERIAL` | PRIMARY KEY |
| `group_name` | `VARCHAR(100)` | NOT NULL |
| `group_code` | `VARCHAR(20)` | UNIQUE, NOT NULL |
| `description` | `TEXT` | nullable |
| `interest_rate` | `DECIMAL(5,2)` | DEFAULT 10.00 |
| `penalty_rate` | `DECIMAL(5,2)` | DEFAULT 5.00 |
| `meeting_day` | `VARCHAR(20)` | nullable |
| `contribution_amount` | `DECIMAL(10,2)` | DEFAULT 0.00 |
| `invitation_code` | `VARCHAR(6)` | UNIQUE |
| `cycle_start_date` | `DATE` | nullable |
| `cycle_end_date` | `DATE` | nullable |
| `total_savings` | `DECIMAL(15,2)` | DEFAULT 0.00 |
| `total_loans` | `DECIMAL(15,2)` | DEFAULT 0.00 |
| `created_by` | `INT` | FK → users.id |
| `created_at` | `TIMESTAMP` | DEFAULT CURRENT_TIMESTAMP |

### 4.4 Table: `savings_contributions`

Records every savings deposit made by members.

| Column | Type | Constraints |
|---|---|---|
| `id` | `INT AUTO_INCREMENT` / `SERIAL` | PRIMARY KEY |
| `member_id` | `INT` | FK → users.id |
| `group_id` | `INT` | FK → savings_groups.id |
| `amount` | `DECIMAL(10,2)` | NOT NULL |
| `contribution_date` | `DATE` | NOT NULL |
| `payment_method` | `VARCHAR(50)` | nullable |
| `transaction_ref` | `VARCHAR(100)` | nullable |
| `recorded_by` | `INT` | FK → users.id |
| `is_self_service` | `TINYINT(1)` / `SMALLINT` | DEFAULT 0 |
| `created_at` | `TIMESTAMP` | DEFAULT CURRENT_TIMESTAMP |

### 4.5 Table: `loans`

Tracks the full lifecycle of every loan from application to settlement.

| Column | Type | Constraints |
|---|---|---|
| `id` | `INT AUTO_INCREMENT` / `SERIAL` | PRIMARY KEY |
| `member_id` | `INT` | FK → users.id |
| `group_id` | `INT` | FK → savings_groups.id |
| `principal_amount` | `DECIMAL(10,2)` | NOT NULL |
| `interest_rate` | `DECIMAL(5,2)` | NOT NULL |
| `total_payable` | `DECIMAL(10,2)` | NOT NULL |
| `amount_paid` | `DECIMAL(10,2)` | DEFAULT 0.00 |
| `balance` | `DECIMAL(10,2)` | NOT NULL |
| `application_date` | `DATE` | NOT NULL |
| `approval_date` | `DATE` | nullable |
| `disbursement_date` | `DATE` | nullable |
| `repayment_period` | `INT` | months |
| `repayment_frequency` | `VARCHAR(20)` | DEFAULT 'monthly' |
| `status` | `ENUM('pending','approved','disbursed','repaid','defaulted')` | NOT NULL |
| `approved_by` | `INT` | FK → users.id |
| `created_at` | `TIMESTAMP` | DEFAULT CURRENT_TIMESTAMP |

**Status lifecycle:** `pending` → `approved` → `disbursed` → `repaid` or `defaulted`.

### 4.6 Table: `loan_repayments`

Records individual payments made against loans, tracking late flags and principal/interest split.

| Column | Type | Constraints |
|---|---|---|
| `id` | `INT AUTO_INCREMENT` / `SERIAL` | PRIMARY KEY |
| `loan_id` | `INT` | FK → loans.id |
| `amount` | `DECIMAL(10,2)` | NOT NULL |
| `principal_paid` | `DECIMAL(10,2)` | NOT NULL |
| `interest_paid` | `DECIMAL(10,2)` | NOT NULL |
| `penalty_amount` | `DECIMAL(10,2)` | DEFAULT 0.00 |
| `payment_date` | `DATE` | NOT NULL |
| `due_date` | `DATE` | NOT NULL |
| `payment_method` | `VARCHAR(50)` | nullable |
| `is_late` | `BOOLEAN` | DEFAULT FALSE |
| `recorded_by` | `INT` | FK → users.id |
| `created_at` | `TIMESTAMP` | DEFAULT CURRENT_TIMESTAMP |

### 4.7 Table: `transactions`

General ledger tracking all financial events for audit and reporting.

| Column | Type | Constraints |
|---|---|---|
| `id` | `INT AUTO_INCREMENT` / `SERIAL` | PRIMARY KEY |
| `group_id` | `INT` | FK → savings_groups.id |
| `transaction_type` | `ENUM('savings','loan_disbursement','loan_repayment','penalty','withdrawal')` | NOT NULL |
| `amount` | `DECIMAL(10,2)` | NOT NULL |
| `member_id` | `INT` | FK → users.id, nullable |
| `loan_id` | `INT` | nullable |
| `description` | `TEXT` | nullable |
| `reference` | `VARCHAR(100)` | nullable |
| `created_by` | `INT` | nullable |
| `created_at` | `TIMESTAMP` | DEFAULT CURRENT_TIMESTAMP |

### 4.8 Table: `meetings`

Records of group meetings for attendance tracking and historical record.

| Column | Type | Constraints |
|---|---|---|
| `id` | `INT AUTO_INCREMENT` / `SERIAL` | PRIMARY KEY |
| `group_id` | `INT` | FK → savings_groups.id |
| `meeting_date` | `DATE` | NOT NULL |
| `meeting_type` | `VARCHAR(50)` | nullable |
| `attendance_count` | `INT` | nullable |
| `savings_collected` | `DECIMAL(10,2)` | nullable |
| `loans_disbursed` | `DECIMAL(10,2)` | nullable |
| `loans_repaid` | `DECIMAL(10,2)` | nullable |
| `minutes` | `TEXT` | nullable |
| `recorded_by` | `INT` | nullable |
| `created_at` | `TIMESTAMP` | DEFAULT CURRENT_TIMESTAMP |

### 4.9 Table: `notifications`

In-app notification messages delivered to individual users.

| Column | Type | Constraints |
|---|---|---|
| `id` | `INT AUTO_INCREMENT` / `SERIAL` | PRIMARY KEY |
| `user_id` | `INT` | FK → users.id |
| `title` | `VARCHAR(200)` | NOT NULL |
| `message` | `TEXT` | NOT NULL |
| `is_read` | `BOOLEAN` | DEFAULT FALSE |
| `created_at` | `TIMESTAMP` | DEFAULT CURRENT_TIMESTAMP |

### 4.10 Table: `notification_preferences`

Per-user notification delivery settings.

| Column | Type | Constraints |
|---|---|---|
| `id` | `INT AUTO_INCREMENT` / `SERIAL` | PRIMARY KEY |
| `user_id` | `INT` | FK → users.id |
| `sms_enabled` | `TINYINT(1)` / `SMALLINT` | DEFAULT 1 |
| `in_app_enabled` | `TINYINT(1)` / `SMALLINT` | DEFAULT 1 |
| `frequency` | `ENUM('immediate','daily','weekly')` | DEFAULT 'immediate' |
| `created_at` | `TIMESTAMP` | DEFAULT CURRENT_TIMESTAMP |

### 4.11 Table: `recurring_savings`

Scheduled automatic savings configurations for opt-in members.

| Column | Type | Constraints |
|---|---|---|
| `id` | `INT AUTO_INCREMENT` / `SERIAL` | PRIMARY KEY |
| `member_id` | `INT` | FK → users.id |
| `group_id` | `INT` | FK → savings_groups.id |
| `amount` | `DECIMAL(10,2)` | NOT NULL |
| `frequency` | `ENUM('weekly','monthly','custom')` | DEFAULT 'monthly' |
| `custom_interval` | `INT` | nullable (days) |
| `next_run_date` | `DATE` | NOT NULL |
| `end_date` | `DATE` | nullable |
| `active` | `TINYINT(1)` / `SMALLINT` | DEFAULT 1 |
| `created_at` | `TIMESTAMP` | DEFAULT CURRENT_TIMESTAMP |

### 4.12 Table: `risk_mitigations`

Tracks risk-mitigation actions taken on flagged accounts.

| Column | Type | Constraints |
|---|---|---|
| `id` | `INT AUTO_INCREMENT` / `SERIAL` | PRIMARY KEY |
| `risk_type` | `VARCHAR(50)` | NOT NULL |
| `target_id` | `INT` | NOT NULL |
| `mitigation_note` | `TEXT` | NOT NULL |
| `status` | `ENUM('open','resolved','monitored')` | DEFAULT 'open' |
| `updated_by` | `INT` | FK → users.id |
| `created_at` | `TIMESTAMP` | DEFAULT CURRENT_TIMESTAMP |
| `updated_at` | `TIMESTAMP` (auto-updated) | MySQL: `ON UPDATE`; PG: trigger |

---

## 5. Role-Based Access Control

### 5.1 Roles & Permissions

| Feature | admin | group_admin | loan_officer | member |
|---|---|---|---|---|
| System configuration | ✅ | — | — | — |
| View all groups | ✅ | — | — | — |
| Manage own group settings | ✅ | ✅ | — | — |
| Invitation code management | ✅ | ✅ | — | — |
| Add/edit/approve members | ✅ | ✅ | — | — |
| Delete members | ✅ | ✅ | — | — |
| Record savings (any member) | ✅ | ✅ | — | — |
| Record own savings | ✅ | ✅ | ✅ | ✅ |
| View all savings | ✅ | ✅ (group) | — | ✅ (own) |
| Approve loans | ✅ | ✅ | ✅ | — |
| Disburse loans | ✅ | ✅ | ✅ | — |
| Record repayments | ✅ | ✅ | ✅ | — |
| View loan portfolio | ✅ | ✅ (group) | ✅ | — |
| Apply for loans | ✅ | ✅ | ✅ | ✅ |
| View own loans | ✅ | ✅ | ✅ | ✅ |
| Generate reports | ✅ | ✅ (group) | ✅ | — |
| Record meetings | ✅ | ✅ | — | — |
| Send notifications | ✅ | ✅ | ✅ | — |
| Manage profile | ✅ | ✅ | ✅ | ✅ |

### 5.2 Enforcement Mechanism

```php
// includes/init.php
function requireLogin(): array     // Redirects to login.php if no session
function requireRole(array $roles): array  // Redirects to dashboard if unauthorized
function hasRole(string $role): bool       // Inline check
function hasAnyRole(array $roles): bool    // Inline check
```

Every page calls `requireRole()` at the top. Example from `members.php`:
```php
$user = requireRole(['admin', 'group_admin', 'loan_officer']);
```

---

## 6. Features

### 6.1 Authentication & Security

- Session-based login with bcrypt password hashing
- 4 role levels enforced via `requireRole()` on every page
- CSRF protection with `csrfToken()` / `verifyCsrf()` using `hash_equals()`
- Rate-limited signup (max 3 attempts per hour per IP)
- Session regeneration on login
- Input sanitization via `Validation::sanitize()` (strip_tags + trim)
- SQL injection prevention via PDO prepared statements

### 6.2 Group Management

- Group registration with auto-generated group code
- 6-digit invitation code generation for member self-joining
- Group settings (name, interest rate, penalty rate, meeting day, contribution amount)
- Invitation code reset
- Delete group with cascading cleanup
- Dashboard with member count, total savings, total loans, Portfolio at Risk (PAR)

### 6.3 Member Management

- Add, edit, approve, and deactivate members
- Pending approval workflow for new members joining via invitation code
- Role-based scoping (admin sees all; group_admin sees only their group)
- Self-service join with invitation code on `join_group.php`
- Phone number validated for Zambian format (`+260` or `0` prefix)

### 6.4 Savings Module

- **Self-service contributions** on `my_savings.php` — members record their own deposits
- **Admin recording** on `record_savings.php` — admin/group_admin record for any member
- **Savings collection window** enforced via `config/savings_helpers.php` — transactions blocked outside `cycle_start_date` – `cycle_end_date` with explicit error message
- **Recurring/auto savings** via `run_recurring_savings.php` cron job — processes weekly, monthly, or custom-interval schedules
- **Savings milestones** — congratulatory flash messages at K1,000, K5,000, K10,000, K25,000, K50,000, K100,000
- **Chart visualization** — Chart.js bar chart showing per-member savings overview

### 6.5 Loan Management

- **Full lifecycle:** Apply → Pending → Approved → Disbursed → Repaid / Defaulted
- **Risk-profiled loan applications** on `new_loan.php` — real-time credit ceiling display
- **Loan approval workflow** — approval/disbursement with notification alerts
- **Repayment recording** — with savings wallet deduction option; auto-calculates principal vs interest split (70/30)
- **Auto loan deductions** via `run_auto_loan_deductions.php` — automatically deducts overdue repayments from savings
- **Notifications** on approval, disbursement, and repayment events

### 6.6 Risk Profiling Engine

See [Section 7 — Risk Profiling Engine](#7-risk-profiling-engine) for full details.

### 6.7 Financial Reports

- Summary statistics: groups, members, total savings, total loans, repaid amount, PAR
- Member savings report (name, total saved, last contribution date, contribution count)
- Loan portfolio report (member, principal, balance, status, repayment progress)
- CSV export: `reports.php?export=csv`
- Excel export (tab-separated): `reports.php?export=excel`

### 6.8 Meeting Recording

- Record meetings with date, type, attendance count, amounts collected/disbursed/repaid
- Free-text minutes field
- Meeting history table with sortable columns
- Upcoming meeting calculation based on group's meeting day

### 6.9 Notifications

- In-app notification delivery with read/unread status
- Real-time polling: unread count every 30 s, notification previews every 60 s
- Admin/group_admin can compose and send notifications to selected users or entire groups
- Multi-select recipient picker with "select all" / "deselect all" support
- Individual notification preferences (SMS toggle, in-app toggle, delivery frequency)
- Mark individual or all notifications as read
- AJAX endpoints: `ajax/unread_count.php`, `ajax/recent_notifications.php`, `ajax/mark_notification.php`
- Helper functions: `notifyUser()`, `notifyGroup()`, `notifyAllAdmins()`

### 6.10 User Profile

- View/edit profile (full name, email, phone)
- Change password with current password verification
- Notification preferences (SMS, in-app, frequency)
- Personal stats: total savings, contribution count, total loans, total paid

### 6.11 Landing Page

- Professional marketing page with scroll animations
- Hero section, feature grid, statistics banner, call-to-action, footer
- Claims: 85% fewer record errors, 70% less disputes, 60% time saved, 50% more engagement

### 6.12 Cron Jobs

| Script | Purpose | Suggested Schedule |
|---|---|---|
| `run_recurring_savings.php` | Processes recurring savings schedules (weekly/monthly/custom) | Daily |
| `run_auto_loan_deductions.php` | Deducts overdue loan repayments from member savings | Daily |

---

## 7. Risk Profiling Engine

**File:** `config/risk_engine.php`  
**Function:** `getMemberRiskProfile(PDO $pdo, int $member_id): array|null`

The risk engine implements the **capacity-driven credit-scoring** model mandated by supervisor amendments (May 2026). It replaces flat binary rejection with dynamic, proportional borrowing limits.

### 7.1 Input Factors

| Factor | Source | Weight |
|---|---|---|
| Historical savings total | `savings_contributions` SUM | Base capacity |
| Outstanding debt balance | `loans` WHERE status IN ('disbursed','approved') | Liability deduction |
| Overdue loan count & balance | `loans` WHERE status='disbursed' AND past repayment period | Stress penalty |
| Late repayment ratio | `loan_repayments.is_late` | Discipline signal (20%) |
| Active loan count | Count of non-repaid loans | Concentration penalty |

### 7.2 Scoring Formula

```
equity_component     = savings_equity_ratio × 70.0
discipline_component = (1 - late_ratio) × 20.0
overdue_penalty      = min(30, (overdue_count × 6) + structural_risk_excess)
active_loan_penalty  = min(10, active_loans_count × 2)
base_score           = 10.0 (floor)
risk_score           = clamp( equity_component + discipline_component + 10.0
                              - overdue_penalty - active_loan_penalty, 0, 100 )
```

### 7.3 Risk Grades & Multipliers

| Grade | Score Range | Multiplier | Label | Color |
|---|---|---|---|---|
| **A** | 80 – 100 | 2.8× | Low Risk (High Capacity) | `#10b981` (green) |
| **B** | 65 – 79 | 2.2× | Low-Medium Risk (Stable Capacity) | `#3b82f6` (blue) |
| **C** | 50 – 64 | 1.4× | Medium Risk (Managed Capacity) | `#f59e0b` (yellow) |
| **D** | 35 – 49 | 0.8× | High Risk (Restricted Capacity) | `#f97316` (orange) |
| **E** | 0 – 34 | 0.35× | Severe Risk (Minimal Capacity) | `#ef4444` (red) |

### 7.4 Credit Ceiling Calculation

```
structural_ceiling = historical_savings × multiplier
capacity_headroom  = max(0, structural_ceiling - outstanding_debt)

// Overdue loans compress headroom by 40%
if overdue_count > 0: capacity_headroom ×= 0.60

// Grace policy: small access for active savers with ≥ K150 savings and no overdue
if capacity_headroom ≤ 0 AND historical_savings ≥ 150 AND overdue_count = 0:
    capacity_headroom = min(200, historical_savings × 0.20)

// Grade E hard floor
if grade == 'E': capacity_headroom = min(capacity_headroom, historical_savings × 0.10)
```

### 7.5 Return Value

The function returns a detailed associative array with 20+ fields including `risk_score`, `risk_grade`, `risk_label`, `risk_color`, `multiplier`, `safe_limit` (eligible amount), `structural_ceiling`, `historical_savings`, `outstanding_debt`, `net_equity`, breakdown ratios, and human-readable descriptions.

---

## 8. Requirements

### 8.1 Functional Requirements

| ID | Requirement | Status |
|---|---|---|
| FR-01 | Member registration and profile management | ✅ |
| FR-02 | Group registration with invitation codes | ✅ |
| FR-03 | Savings contribution recording (admin) | ✅ |
| FR-04 | Self-service savings contributions (member direct) | ✅ |
| FR-05 | Automated savings collection window enforcement | ✅ |
| FR-06 | Recurring / automatic savings scheduling | ✅ |
| FR-07 | Full loan lifecycle workflow (apply → repay) | ✅ |
| FR-08 | Capacity-driven risk profiling (dynamic credit ceiling) | ✅ |
| FR-09 | Interest calculation engine | ✅ |
| FR-10 | Automated penalty assessment for overdue accounts | ✅ |
| FR-11 | Repayment tracking with principal/interest split | ✅ |
| FR-12 | Meeting recording with attendance and financials | ✅ |
| FR-13 | Financial reports with CSV/Excel export | ✅ |
| FR-14 | In-app notification system with real-time polling | ✅ |
| FR-15 | Multi-select recipient notification composition | ✅ |
| FR-16 | Member notification preferences (SMS, in-app, frequency) | ✅ |
| FR-17 | Role-based access control (4 roles) | ✅ |
| FR-18 | Member savings statement viewing | ✅ |
| FR-19 | Loan portfolio summary reports | ✅ |
| FR-20 | User authentication with CSRF protection | ✅ |
| FR-21 | Rate-limited signup | ✅ |

### 8.2 Non-Functional Requirements

| ID | Requirement | Target |
|---|---|---|
| NFR-01 | Low-literacy optimization | Simplified UI with intuitive navigation |
| NFR-02 | Onboarding time | ≤ 10 min for new users to complete primary workflows |
| NFR-03 | Low-bandwidth optimization | Lightweight under 2G/3G conditions |
| NFR-04 | Savings recording response | ≤ 2.1 s average |
| NFR-05 | Loan application processing | ≤ 2.3 s average |
| NFR-06 | Report generation | ≤ 2.7 s average |
| NFR-07 | RBAC enforcement | Backend-gated, no role escalation possible |
| NFR-08 | Encryption | HTTPS in transit; bcrypt for passwords |
| NFR-09 | Uptime | ≥ 99.0% monthly |
| NFR-10 | Database integrity | Zero data loss across multi-table operations |
| NFR-11 | Scalability | Seamless from single group to multi-group deployment |
| NFR-12 | Dual database support | MySQL and PostgreSQL via PDO abstraction |

---

## 9. Default Accounts

All accounts are seeded by `config/database.php` (admin) and `seed_test_data.php` / `seed_test_data.sql` / `seed_test_data.pgsql`.

| Username | Password | Role | Group |
|---|---|---|---|
| `admin` | `admin123` | admin | — |
| `gmwila` | `password123` | group_admin | Pamodzi Savings Group |
| `loanofficer` | `pass123` | loan_officer | Pamodzi Savings Group |
| `alice` | `password123` | member | Pamodzi Savings Group |
| `bob` | `password123` | member | Pamodzi Savings Group |
| `carol` | `password123` | member | Pamodzi Savings Group |
| `david` | `password123` | member | Pamodzi Savings Group |
| `eve` | `password123` | member | Pamodzi Savings Group |
| `frank` | `password123` | member | Pamodzi Savings Group |

---

## 10. Deployment

### 10.1 Docker (Local Development)

```bash
docker compose up --build
# Web:      http://localhost:8080
# Database: postgresql://postgres:postpass@localhost:5432/rsgms_db
```

### 10.2 Manual (Any PHP Server)

1. Configure database credentials as environment variables or edit `config/database.php` defaults.
2. The schema auto-creates on first page load.
3. Optionally run `seed_test_data.php` for sample data.
4. Point web server document root to the project directory.

### 10.3 Environment Variables

| Variable | Default (MySQL) | Default (PostgreSQL) |
|---|---|---|
| `DB_DRIVER` | `mysql` | `pgsql` |
| `DB_HOST` | `localhost` | `localhost` |
| `DB_PORT` | `3306` | `5432` |
| `DB_NAME` | `rsgms_db` | `rsgms_db` |
| `DB_USER` | `root` | `postgres` |
| `DB_PASSWORD` | `""` | `""` |

### 10.4 Render Cloud

`render.yaml` provides a Docker-based deployment to Render.com with a managed PostgreSQL database.

---

## 11. Testing

### 11.1 End-to-End Tests (Playwright)

Located in `e2e/` directory:

| Test File | Coverage |
|---|---|
| `auth.test.js` | Login, logout, RBAC sidebar, invalid login, unauthenticated redirect |
| `members.test.js` | Member table rendering, view modal |
| `savings.test.js` | Admin savings table, member self-savings, member savings view |
| `loans.test.js` | Loan application, loan officer view, admin overview |
| `dashboard.test.js` | Stat cards, transactions, quick actions, reports, notifications, profile |

**Run tests:**
```bash
cd e2e && node run.js
```

The test runner auto-starts a PHP development server on port 8080.

### 11.2 Seed Data

| Script | Format |
|---|---|
| `seed_test_data.php` | PHP PDO (works with MySQL or PostgreSQL) |
| `seed_test_data.sql` | MySQL SQL script (472 lines, 9 users, transactions, loans) |
| `seed_test_data.pgsql` | PostgreSQL SQL script |
| `scripts/seed_dense_data.php` | CLI script generating ~180 members for dense demo data |

---

## Appendix A: File Map

```
rsgms/
├── index.php                  Landing page
├── login.php                  Authentication
├── register.php               Group registration
├── signup.php                 Member signup
├── join_group.php             Join via invitation code
├── dashboard.php              Main dashboard (role-scoped)
├── groups.php                 Group management (admin)
├── group_settings.php         Group configuration (group_admin)
├── members.php                Member management
├── add_member.php             Add member form
├── savings.php                Savings management (admin)
├── record_savings.php         Record savings (admin)
├── my_savings.php             Self-service savings (member)
├── loans.php                  Loan management
├── new_loan.php               Loan application with risk profile
├── my_loans.php               Member loan view
├── reports.php                Financial reports & export
├── meetings.php               Meeting records
├── notifications.php          Notification center
├── profile.php                User profile & preferences
├── statements.php             Member statements
├── about.php                  About page
├── contact.php                Contact page
├── features.php               Features page
├── logout.php                 Session destroy
│
├── config/
│   ├── database.php           DB connection + auto-schema
│   ├── validation.php         Validation class
│   ├── risk_engine.php        Risk profiling engine
│   ├── savings_helpers.php    Savings window helpers
│   ├── db_helpers.php         SQL dialect helpers
│   └── shared_navbar.php      Reusable navigation
│
├── includes/
│   ├── init.php               Session + auth + CSRF + notifications
│   └── sidebar.php            Sidebar navigation
│
├── ajax/
│   ├── unread_count.php       Notification polling
│   ├── recent_notifications.php
│   └── mark_notification.php
│
├── assets/
│   ├── css/
│   │   ├── design-system.css  Complete design system (OKLCH)
│   │   ├── icons.css          Font Awesome import
│   │   └── toast.css          Toast notification styles
│   └── js/
│       ├── chart.umd.min.js   Chart.js bundle
│       ├── loading.js         Loading states
│       └── toast.js           Toast notification JS
│
├── scripts/
│   ├── check_db.php           DB health check
│   ├── create_loan_officer.php CLI helper
│   └── seed_dense_data.php    Dense data seeder
│
├── e2e/                       Playwright end-to-end tests
├── seed_test_data.php         PHP seed script
├── seed_test_data.sql         MySQL seed script
├── seed_test_data.pgsql       PostgreSQL seed script
├── Dockerfile                 Apache + PHP 8.2
├── docker-compose.yml         PostgreSQL + web service
├── docker-entrypoint.sh       DB wait + seed
└── render.yaml                Render deployment
```
