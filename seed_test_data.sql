-- ============================================================
-- RSGMS Full Database Creation + Seed Test Data
-- Run via:
--   mysql -u root < seed_test_data.sql
-- ============================================================

DROP DATABASE IF EXISTS rsgms_db;
CREATE DATABASE rsgms_db;
USE rsgms_db;

-- ============================================================
-- SCHEMA
-- ============================================================

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin', 'group_admin', 'loan_officer', 'member') DEFAULT 'member',
    group_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Savings groups table
CREATE TABLE IF NOT EXISTS savings_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(100) NOT NULL,
    group_code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    interest_rate DECIMAL(5,2) DEFAULT 10.00,
    penalty_rate DECIMAL(5,2) DEFAULT 5.00,
    meeting_day VARCHAR(20),
    contribution_amount DECIMAL(10,2) DEFAULT 0.00,
    cycle_start_date DATE,
    cycle_end_date DATE,
    total_savings DECIMAL(15,2) DEFAULT 0.00,
    total_loans DECIMAL(15,2) DEFAULT 0.00,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Savings contributions table
CREATE TABLE IF NOT EXISTS savings_contributions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    group_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    contribution_date DATE NOT NULL,
    payment_method VARCHAR(50),
    transaction_ref VARCHAR(100),
    recorded_by INT,
    is_self_service TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES users(id),
    FOREIGN KEY (group_id) REFERENCES savings_groups(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- Loans table
CREATE TABLE IF NOT EXISTS loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    group_id INT NOT NULL,
    principal_amount DECIMAL(10,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    total_payable DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0.00,
    balance DECIMAL(10,2) NOT NULL,
    application_date DATE NOT NULL,
    approval_date DATE,
    disbursement_date DATE,
    repayment_period INT COMMENT 'in months',
    repayment_frequency VARCHAR(20) DEFAULT 'monthly',
    status ENUM('pending', 'approved', 'disbursed', 'repaid', 'defaulted') DEFAULT 'pending',
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES users(id),
    FOREIGN KEY (group_id) REFERENCES savings_groups(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Loan repayments table
CREATE TABLE IF NOT EXISTS loan_repayments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    principal_paid DECIMAL(10,2) NOT NULL,
    interest_paid DECIMAL(10,2) NOT NULL,
    penalty_amount DECIMAL(10,2) DEFAULT 0.00,
    payment_date DATE NOT NULL,
    due_date DATE NOT NULL,
    payment_method VARCHAR(50),
    is_late BOOLEAN DEFAULT FALSE,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- Transactions log table
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    transaction_type ENUM('savings', 'loan_disbursement', 'loan_repayment', 'penalty', 'withdrawal') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    member_id INT,
    loan_id INT,
    description TEXT,
    reference VARCHAR(100),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES savings_groups(id),
    FOREIGN KEY (member_id) REFERENCES users(id)
);

-- Meeting records table
CREATE TABLE IF NOT EXISTS meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    meeting_date DATE NOT NULL,
    meeting_type VARCHAR(50),
    attendance_count INT,
    savings_collected DECIMAL(10,2),
    loans_disbursed DECIMAL(10,2),
    loans_repaid DECIMAL(10,2),
    minutes TEXT,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES savings_groups(id)
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Notification preferences table
CREATE TABLE IF NOT EXISTS notification_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sms_enabled TINYINT(1) DEFAULT 1,
    in_app_enabled TINYINT(1) DEFAULT 1,
    frequency ENUM('immediate','daily','weekly') DEFAULT 'immediate',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Recurring savings table
CREATE TABLE IF NOT EXISTS recurring_savings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    group_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    frequency ENUM('weekly','monthly','custom') DEFAULT 'monthly',
    custom_interval INT DEFAULT NULL COMMENT 'number of days for custom frequency',
    next_run_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES users(id),
    FOREIGN KEY (group_id) REFERENCES savings_groups(id)
);

-- Risk mitigations table
CREATE TABLE IF NOT EXISTS risk_mitigations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    risk_type VARCHAR(50) NOT NULL,
    target_id INT NOT NULL,
    mitigation_note TEXT NOT NULL,
    status ENUM('open', 'resolved', 'monitored') DEFAULT 'open',
    updated_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- ============================================================
-- SCHEMA MIGRATIONS (idempotent ALTER TABLE statements)
-- ============================================================
SET @s = NULL;
SELECT 'ALTER TABLE users ADD COLUMN status ENUM(''active'',''pending'') DEFAULT ''active''',
       'ALTER TABLE savings_groups ADD COLUMN invitation_code VARCHAR(6) UNIQUE',
       'CREATE INDEX idx_users_status ON users(status)',
       'ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL'
INTO @s1, @s2, @s3, @s4;

-- Use prepared statements to avoid errors if columns already exist
-- (MySQL 8.0+ ignores IF NOT EXISTS for columns, so we use try-catch equivalents)
SET @sql1 = 'ALTER TABLE users ADD COLUMN status ENUM(''active'',''pending'') DEFAULT ''active''';
PREPARE stmt FROM @sql1;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql2 = 'ALTER TABLE savings_groups ADD COLUMN invitation_code VARCHAR(6) UNIQUE';
PREPARE stmt FROM @sql2;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql3 = 'CREATE INDEX idx_users_status ON users(status)';
PREPARE stmt FROM @sql3;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql4 = 'ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL';
PREPARE stmt FROM @sql4;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- DEFAULT ADMIN USER
-- ============================================================
INSERT INTO users (username, password, full_name, role)
SELECT 'admin', '$2y$12$DPIHj6nevRbKtHP2w5IxRuySstbHa8ANVOSQW0YCX4ZwXuT4P5dsm', 'System Administrator', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

-- ============================================================
-- SEED TEST DATA
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Clear existing demo data (preserve admin user)
DELETE FROM loan_repayments;
DELETE FROM transactions;
DELETE FROM savings_contributions;
DELETE FROM loans;
DELETE FROM meetings;
DELETE FROM notifications;
DELETE FROM notification_preferences;
DELETE FROM recurring_savings;
DELETE FROM risk_mitigations;
DELETE FROM savings_groups;
DELETE FROM users WHERE role != 'admin';

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- PASSWORD HASHES (bcrypt)
-- password123 => $2y$12$vVoy7KJM729142UOmlFJIeiQyin.iGHymljal1dWu8u0nD/BMo5oq
-- admin123    => $2y$12$DPIHj6nevRbKtHP2w5IxRuySstbHa8ANVOSQW0YCX4ZwXuT4P5dsm
-- ============================================================

-- -------------------------------------------------------
-- 1. GROUP ADMIN
-- -------------------------------------------------------
INSERT INTO users (username, password, full_name, email, phone, role, group_id, created_at)
SELECT 'gmwila', '$2y$12$vVoy7KJM729142UOmlFJIeiQyin.iGHymljal1dWu8u0nD/BMo5oq',
       'Grace Mwila', 'gmwila@example.com', '+260970000001', 'group_admin', NULL, NOW()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'gmwila');

SET @group_admin_id = (SELECT id FROM users WHERE username = 'gmwila');

-- -------------------------------------------------------
-- 1b. LOAN OFFICER
-- -------------------------------------------------------
INSERT INTO users (username, password, full_name, email, phone, role, created_at)
SELECT 'loanofficer', '$2y$12$8XBJtcncpaPbmWPXmbAzuuE4ifxVi.XvsTxbaz55JsRHKsaUhwsEi',
       'Loan Officer', 'loanofficer@example.com', '+260970000008', 'loan_officer', NOW()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'loanofficer');

-- -------------------------------------------------------
-- 2. SAVINGS GROUP
-- -------------------------------------------------------
INSERT INTO savings_groups (group_name, group_code, description, interest_rate, penalty_rate,
                            meeting_day, contribution_amount, cycle_start_date, created_by, created_at)
VALUES ('Pamodzi Savings Group', 'PAMODZI001', 'Community savings group in Ikelenge, Zambia',
        10.00, 5.00, 'Saturday', 200.00, CURDATE(), @group_admin_id, NOW());

SET @group_id = LAST_INSERT_ID();

-- Link group admin to the group
UPDATE users SET group_id = @group_id WHERE id = @group_admin_id;

-- -------------------------------------------------------
-- 3. HELPERS (use variables for member IDs)
-- -------------------------------------------------------

-- -------------------------------------------------------
-- ALICE BANDA — Heavy saver, no debt → Grade A
-- -------------------------------------------------------
INSERT INTO users (username, password, full_name, email, phone, role, group_id, created_at)
VALUES ('alice', '$2y$12$vVoy7KJM729142UOmlFJIeiQyin.iGHymljal1dWu8u0nD/BMo5oq',
        'Alice Banda', 'alice@example.com', '+260970000002', 'member', @group_id, NOW());
SET @alice = LAST_INSERT_ID();

INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, recorded_by, created_at) VALUES
(@alice, @group_id, 400, DATE_SUB(CURDATE(), INTERVAL 11 MONTH), 'cash', @group_admin_id, NOW()),
(@alice, @group_id, 350, DATE_SUB(CURDATE(), INTERVAL 10 MONTH), 'cash', @group_admin_id, NOW()),
(@alice, @group_id, 450, DATE_SUB(CURDATE(), INTERVAL 9 MONTH), 'cash', @group_admin_id, NOW()),
(@alice, @group_id, 400, DATE_SUB(CURDATE(), INTERVAL 8 MONTH), 'cash', @group_admin_id, NOW()),
(@alice, @group_id, 350, DATE_SUB(CURDATE(), INTERVAL 7 MONTH), 'cash', @group_admin_id, NOW()),
(@alice, @group_id, 500, DATE_SUB(CURDATE(), INTERVAL 6 MONTH), 'cash', @group_admin_id, NOW()),
(@alice, @group_id, 400, DATE_SUB(CURDATE(), INTERVAL 5 MONTH), 'cash', @group_admin_id, NOW()),
(@alice, @group_id, 350, DATE_SUB(CURDATE(), INTERVAL 4 MONTH), 'cash', @group_admin_id, NOW()),
(@alice, @group_id, 450, DATE_SUB(CURDATE(), INTERVAL 3 MONTH), 'cash', @group_admin_id, NOW()),
(@alice, @group_id, 400, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 'cash', @group_admin_id, NOW()),
(@alice, @group_id, 350, DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 'cash', @group_admin_id, NOW()),
(@alice, @group_id, 400, CURDATE(), 'cash', @group_admin_id, NOW());

-- -------------------------------------------------------
-- BOB CHANDA — Good saver, repaid old loan + active loan → Grade B
-- -------------------------------------------------------
INSERT INTO users (username, password, full_name, email, phone, role, group_id, created_at)
VALUES ('bob', '$2y$12$vVoy7KJM729142UOmlFJIeiQyin.iGHymljal1dWu8u0nD/BMo5oq',
        'Bob Chanda', 'bob@example.com', '+260970000003', 'member', @group_id, NOW());
SET @bob = LAST_INSERT_ID();

INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, recorded_by, created_at) VALUES
(@bob, @group_id, 250, DATE_SUB(CURDATE(), INTERVAL 9 MONTH), 'cash', @group_admin_id, NOW()),
(@bob, @group_id, 250, DATE_SUB(CURDATE(), INTERVAL 8 MONTH), 'cash', @group_admin_id, NOW()),
(@bob, @group_id, 250, DATE_SUB(CURDATE(), INTERVAL 7 MONTH), 'cash', @group_admin_id, NOW()),
(@bob, @group_id, 250, DATE_SUB(CURDATE(), INTERVAL 6 MONTH), 'cash', @group_admin_id, NOW()),
(@bob, @group_id, 250, DATE_SUB(CURDATE(), INTERVAL 5 MONTH), 'cash', @group_admin_id, NOW()),
(@bob, @group_id, 250, DATE_SUB(CURDATE(), INTERVAL 4 MONTH), 'cash', @group_admin_id, NOW()),
(@bob, @group_id, 250, DATE_SUB(CURDATE(), INTERVAL 3 MONTH), 'cash', @group_admin_id, NOW()),
(@bob, @group_id, 250, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 'cash', @group_admin_id, NOW()),
(@bob, @group_id, 250, DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 'cash', @group_admin_id, NOW()),
(@bob, @group_id, 250, CURDATE(), 'cash', @group_admin_id, NOW());

-- Repaid loan (K600, 3 months, fully repaid)
INSERT INTO loans (member_id, group_id, principal_amount, interest_rate, total_payable, amount_paid, balance,
                   application_date, approval_date, disbursement_date, repayment_period, status, approved_by, created_at)
VALUES (@bob, @group_id, 600, 10, 660, 660, 0,
        DATE_SUB(CURDATE(), INTERVAL 8 MONTH), DATE_SUB(CURDATE(), INTERVAL 8 MONTH),
        DATE_SUB(CURDATE(), INTERVAL 8 MONTH), 3, 'repaid', @group_admin_id, NOW());
SET @bob_old_loan = LAST_INSERT_ID();

INSERT INTO loan_repayments (loan_id, amount, principal_paid, interest_paid, payment_date, due_date, is_late, recorded_by) VALUES
(@bob_old_loan, 220, 154, 66, DATE_SUB(CURDATE(), INTERVAL 7 MONTH), DATE_SUB(CURDATE(), INTERVAL 5 MONTH), FALSE, @group_admin_id),
(@bob_old_loan, 220, 154, 66, DATE_SUB(CURDATE(), INTERVAL 6 MONTH), DATE_SUB(CURDATE(), INTERVAL 5 MONTH), FALSE, @group_admin_id),
(@bob_old_loan, 220, 154, 66, DATE_SUB(CURDATE(), INTERVAL 5 MONTH), DATE_SUB(CURDATE(), INTERVAL 5 MONTH), FALSE, @group_admin_id);

-- Active loan (K1,200, 6 months, 3 payments so far, 1 late)
INSERT INTO loans (member_id, group_id, principal_amount, interest_rate, total_payable, amount_paid, balance,
                   application_date, approval_date, disbursement_date, repayment_period, status, approved_by, created_at)
VALUES (@bob, @group_id, 1200, 10, 1320, 540, 780,
        DATE_SUB(CURDATE(), INTERVAL 3 MONTH), DATE_SUB(CURDATE(), INTERVAL 3 MONTH),
        DATE_SUB(CURDATE(), INTERVAL 3 MONTH), 6, 'disbursed', @group_admin_id, NOW());
SET @bob_active_loan = LAST_INSERT_ID();

INSERT INTO loan_repayments (loan_id, amount, principal_paid, interest_paid, payment_date, due_date, is_late, recorded_by) VALUES
(@bob_active_loan, 180, 126, 54, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), DATE_SUB(CURDATE(), INTERVAL 2 MONTH), FALSE, @group_admin_id),
(@bob_active_loan, 180, 126, 54, DATE_SUB(CURDATE(), INTERVAL 1 MONTH), DATE_SUB(CURDATE(), INTERVAL 1 MONTH), FALSE, @group_admin_id),
(@bob_active_loan, 180, 126, 54, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 20 DAY), TRUE, @group_admin_id);

-- -------------------------------------------------------
-- CAROL DAKA — Moderate saver, active loan with late payments → Grade C
-- -------------------------------------------------------
INSERT INTO users (username, password, full_name, email, phone, role, group_id, created_at)
VALUES ('carol', '$2y$12$vVoy7KJM729142UOmlFJIeiQyin.iGHymljal1dWu8u0nD/BMo5oq',
        'Carol Daka', 'carol@example.com', '+260970000004', 'member', @group_id, NOW());
SET @carol = LAST_INSERT_ID();

INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, recorded_by, created_at) VALUES
(@carol, @group_id, 200, DATE_SUB(CURDATE(), INTERVAL 5 MONTH), 'cash', @group_admin_id, NOW()),
(@carol, @group_id, 200, DATE_SUB(CURDATE(), INTERVAL 4 MONTH), 'cash', @group_admin_id, NOW()),
(@carol, @group_id, 200, DATE_SUB(CURDATE(), INTERVAL 3 MONTH), 'cash', @group_admin_id, NOW()),
(@carol, @group_id, 200, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 'cash', @group_admin_id, NOW()),
(@carol, @group_id, 200, DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 'cash', @group_admin_id, NOW()),
(@carol, @group_id, 200, CURDATE(), 'cash', @group_admin_id, NOW());

INSERT INTO loans (member_id, group_id, principal_amount, interest_rate, total_payable, amount_paid, balance,
                   application_date, approval_date, disbursement_date, repayment_period, status, approved_by, created_at)
VALUES (@carol, @group_id, 800, 10, 880, 360, 520,
        DATE_SUB(CURDATE(), INTERVAL 4 MONTH), DATE_SUB(CURDATE(), INTERVAL 4 MONTH),
        DATE_SUB(CURDATE(), INTERVAL 4 MONTH), 4, 'disbursed', @group_admin_id, NOW());
SET @carol_loan = LAST_INSERT_ID();

INSERT INTO loan_repayments (loan_id, amount, principal_paid, interest_paid, payment_date, due_date, is_late, recorded_by) VALUES
(@carol_loan, 180, 126, 54, DATE_SUB(CURDATE(), INTERVAL 3 MONTH), DATE_SUB(CURDATE(), INTERVAL 3 MONTH), FALSE, @group_admin_id),
(@carol_loan, 180, 126, 54, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_SUB(CURDATE(), INTERVAL 15 DAY), TRUE, @group_admin_id);

-- -------------------------------------------------------
-- DAVID MWALE — Low savings, overdue loan (no repayments) → Grade E
-- -------------------------------------------------------
INSERT INTO users (username, password, full_name, email, phone, role, group_id, created_at)
VALUES ('david', '$2y$12$vVoy7KJM729142UOmlFJIeiQyin.iGHymljal1dWu8u0nD/BMo5oq',
        'David Mwale', 'david@example.com', '+260970000005', 'member', @group_id, NOW());
SET @david = LAST_INSERT_ID();

INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, recorded_by, created_at) VALUES
(@david, @group_id, 100, DATE_SUB(CURDATE(), INTERVAL 4 MONTH), 'cash', @group_admin_id, NOW()),
(@david, @group_id, 100, DATE_SUB(CURDATE(), INTERVAL 3 MONTH), 'cash', @group_admin_id, NOW()),
(@david, @group_id, 100, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 'cash', @group_admin_id, NOW());

-- Overdue loan (disbursed 6 months ago, 4-month term, past due, no repayments)
INSERT INTO loans (member_id, group_id, principal_amount, interest_rate, total_payable, amount_paid, balance,
                   application_date, approval_date, disbursement_date, repayment_period, status, approved_by, created_at)
VALUES (@david, @group_id, 500, 10, 550, 0, 550,
        DATE_SUB(CURDATE(), INTERVAL 7 MONTH), DATE_SUB(CURDATE(), INTERVAL 7 MONTH),
        DATE_SUB(CURDATE(), INTERVAL 6 MONTH), 4, 'disbursed', @group_admin_id, NOW());
SET @david_loan = LAST_INSERT_ID();

-- -------------------------------------------------------
-- EVE BANDA — New member, tiny savings, no loans → Grade A (low ceiling)
-- -------------------------------------------------------
INSERT INTO users (username, password, full_name, email, phone, role, group_id, created_at)
VALUES ('eve', '$2y$12$vVoy7KJM729142UOmlFJIeiQyin.iGHymljal1dWu8u0nD/BMo5oq',
        'Eve Banda', 'eve@example.com', '+260970000006', 'member', @group_id, NOW());
SET @eve = LAST_INSERT_ID();

INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, recorded_by, created_at)
VALUES (@eve, @group_id, 100, CURDATE(), 'cash', @group_admin_id, NOW());

-- -------------------------------------------------------
-- FRANK ZULU — High saver, multiple active loans → Grade B
-- -------------------------------------------------------
INSERT INTO users (username, password, full_name, email, phone, role, group_id, created_at)
VALUES ('frank', '$2y$12$vVoy7KJM729142UOmlFJIeiQyin.iGHymljal1dWu8u0nD/BMo5oq',
        'Frank Zulu', 'frank@example.com', '+260970000007', 'member', @group_id, NOW());
SET @frank = LAST_INSERT_ID();

INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, recorded_by, created_at) VALUES
(@frank, @group_id, 350, DATE_SUB(CURDATE(), INTERVAL 11 MONTH), 'cash', @group_admin_id, NOW()),
(@frank, @group_id, 350, DATE_SUB(CURDATE(), INTERVAL 10 MONTH), 'cash', @group_admin_id, NOW()),
(@frank, @group_id, 350, DATE_SUB(CURDATE(), INTERVAL 9 MONTH), 'cash', @group_admin_id, NOW()),
(@frank, @group_id, 350, DATE_SUB(CURDATE(), INTERVAL 8 MONTH), 'cash', @group_admin_id, NOW()),
(@frank, @group_id, 350, DATE_SUB(CURDATE(), INTERVAL 7 MONTH), 'cash', @group_admin_id, NOW()),
(@frank, @group_id, 350, DATE_SUB(CURDATE(), INTERVAL 6 MONTH), 'cash', @group_admin_id, NOW()),
(@frank, @group_id, 350, DATE_SUB(CURDATE(), INTERVAL 5 MONTH), 'cash', @group_admin_id, NOW()),
(@frank, @group_id, 350, DATE_SUB(CURDATE(), INTERVAL 4 MONTH), 'cash', @group_admin_id, NOW()),
(@frank, @group_id, 350, DATE_SUB(CURDATE(), INTERVAL 3 MONTH), 'cash', @group_admin_id, NOW()),
(@frank, @group_id, 350, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 'cash', @group_admin_id, NOW()),
(@frank, @group_id, 350, DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 'cash', @group_admin_id, NOW()),
(@frank, @group_id, 350, CURDATE(), 'cash', @group_admin_id, NOW());

-- Loan 1 (K1,000, 6 months, 3 payments on time)
INSERT INTO loans (member_id, group_id, principal_amount, interest_rate, total_payable, amount_paid, balance,
                   application_date, approval_date, disbursement_date, repayment_period, status, approved_by, created_at)
VALUES (@frank, @group_id, 1000, 10, 1100, 480, 620,
        DATE_SUB(CURDATE(), INTERVAL 5 MONTH), DATE_SUB(CURDATE(), INTERVAL 5 MONTH),
        DATE_SUB(CURDATE(), INTERVAL 5 MONTH), 6, 'disbursed', @group_admin_id, NOW());
SET @frank_loan1 = LAST_INSERT_ID();

INSERT INTO loan_repayments (loan_id, amount, principal_paid, interest_paid, payment_date, due_date, is_late, recorded_by) VALUES
(@frank_loan1, 160, 112, 48, DATE_SUB(CURDATE(), INTERVAL 4 MONTH), DATE_SUB(CURDATE(), INTERVAL 4 MONTH), FALSE, @group_admin_id),
(@frank_loan1, 160, 112, 48, DATE_SUB(CURDATE(), INTERVAL 3 MONTH), DATE_SUB(CURDATE(), INTERVAL 3 MONTH), FALSE, @group_admin_id),
(@frank_loan1, 160, 112, 48, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), DATE_SUB(CURDATE(), INTERVAL 2 MONTH), FALSE, @group_admin_id);

-- Loan 2 (K800, 4 months, 2 payments, 1 late)
INSERT INTO loans (member_id, group_id, principal_amount, interest_rate, total_payable, amount_paid, balance,
                   application_date, approval_date, disbursement_date, repayment_period, status, approved_by, created_at)
VALUES (@frank, @group_id, 800, 10, 880, 360, 520,
        DATE_SUB(CURDATE(), INTERVAL 3 MONTH), DATE_SUB(CURDATE(), INTERVAL 3 MONTH),
        DATE_SUB(CURDATE(), INTERVAL 3 MONTH), 4, 'disbursed', @group_admin_id, NOW());
SET @frank_loan2 = LAST_INSERT_ID();

INSERT INTO loan_repayments (loan_id, amount, principal_paid, interest_paid, payment_date, due_date, is_late, recorded_by) VALUES
(@frank_loan2, 180, 126, 54, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), DATE_SUB(CURDATE(), INTERVAL 2 MONTH), FALSE, @group_admin_id),
(@frank_loan2, 180, 126, 54, DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_SUB(CURDATE(), INTERVAL 10 DAY), TRUE, @group_admin_id);

-- -------------------------------------------------------
-- 4. TRANSACTIONS LOG
-- -------------------------------------------------------
-- Alice savings
INSERT INTO transactions (group_id, transaction_type, amount, member_id, description, created_by)
SELECT @group_id, 'savings', amount, sc.member_id,
       CONCAT('Savings contribution of K', sc.amount), sc.recorded_by
FROM savings_contributions sc WHERE sc.member_id IN (@alice, @bob, @carol, @david, @eve, @frank) AND sc.group_id = @group_id;

-- Loan disbursements
INSERT INTO transactions (group_id, transaction_type, amount, member_id, loan_id, description, created_by) VALUES
(@group_id, 'loan_disbursement', 600, @bob, @bob_old_loan, 'Loan disbursement of K600', @group_admin_id),
(@group_id, 'loan_disbursement', 1200, @bob, @bob_active_loan, 'Loan disbursement of K1200', @group_admin_id),
(@group_id, 'loan_disbursement', 800, @carol, @carol_loan, 'Loan disbursement of K800', @group_admin_id),
(@group_id, 'loan_disbursement', 500, @david, @david_loan, 'Loan disbursement of K500', @group_admin_id),
(@group_id, 'loan_disbursement', 1000, @frank, @frank_loan1, 'Loan disbursement of K1000', @group_admin_id),
(@group_id, 'loan_disbursement', 800, @frank, @frank_loan2, 'Loan disbursement of K800', @group_admin_id);

-- Repayments
INSERT INTO transactions (group_id, transaction_type, amount, loan_id, description, created_by)
SELECT @group_id, 'loan_repayment', lr.amount, lr.loan_id,
       CONCAT('Loan repayment of K', lr.amount), lr.recorded_by
FROM loan_repayments lr;

-- -------------------------------------------------------
-- 5. UPDATE GROUP AGGREGATES
-- -------------------------------------------------------
UPDATE savings_groups sg
SET total_savings = (SELECT COALESCE(SUM(amount), 0) FROM savings_contributions WHERE group_id = sg.id),
    total_loans   = (SELECT COALESCE(SUM(principal_amount), 0) FROM loans WHERE group_id = sg.id AND status != 'repaid')
WHERE sg.id = @group_id;

-- ============================================================
-- SUMMARY
-- ============================================================
SELECT 'SEED COMPLETE' AS status;
SELECT 'Admin: admin / admin123' AS credentials;
SELECT 'Group Admin: gmwila / password123' AS credentials;
SELECT 'Loan Officer: loanofficer / pass123' AS credentials;
SELECT 'Members: alice, bob, carol, david, eve, frank / password123' AS credentials;
