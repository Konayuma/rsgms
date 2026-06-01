-- ============================================================
-- RSGMS Schema + Seed Test Data (PostgreSQL)
-- Run:
--   psql -h <host> -p <port> -U <user> -d <dbname> < seed_test_data.pgsql
-- ============================================================

-- ============================================================
-- SCHEMA
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role VARCHAR(20) NOT NULL DEFAULT 'member'
        CHECK (role IN ('admin', 'group_admin', 'loan_officer', 'member')),
    group_id INT,
    status VARCHAR(10) NOT NULL DEFAULT 'active'
        CHECK (status IN ('active', 'pending')),
    last_login TIMESTAMP DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);

CREATE TABLE IF NOT EXISTS savings_groups (
    id SERIAL PRIMARY KEY,
    group_name VARCHAR(100) NOT NULL,
    group_code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    interest_rate DECIMAL(5,2) DEFAULT 10.00,
    penalty_rate DECIMAL(5,2) DEFAULT 5.00,
    meeting_day VARCHAR(20),
    contribution_amount DECIMAL(10,2) DEFAULT 0.00,
    invitation_code VARCHAR(6) UNIQUE,
    cycle_start_date DATE,
    cycle_end_date DATE,
    total_savings DECIMAL(15,2) DEFAULT 0.00,
    total_loans DECIMAL(15,2) DEFAULT 0.00,
    created_by INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS savings_contributions (
    id SERIAL PRIMARY KEY,
    member_id INT NOT NULL REFERENCES users(id),
    group_id INT NOT NULL REFERENCES savings_groups(id),
    amount DECIMAL(10,2) NOT NULL,
    contribution_date DATE NOT NULL,
    payment_method VARCHAR(50),
    transaction_ref VARCHAR(100),
    recorded_by INT REFERENCES users(id),
    is_self_service SMALLINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS loans (
    id SERIAL PRIMARY KEY,
    member_id INT NOT NULL REFERENCES users(id),
    group_id INT NOT NULL REFERENCES savings_groups(id),
    principal_amount DECIMAL(10,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    total_payable DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0.00,
    balance DECIMAL(10,2) NOT NULL,
    application_date DATE NOT NULL,
    approval_date DATE,
    disbursement_date DATE,
    repayment_period INT,
    repayment_frequency VARCHAR(20) DEFAULT 'monthly',
    status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'approved', 'disbursed', 'repaid', 'defaulted')),
    approved_by INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS loan_repayments (
    id SERIAL PRIMARY KEY,
    loan_id INT NOT NULL REFERENCES loans(id),
    amount DECIMAL(10,2) NOT NULL,
    principal_paid DECIMAL(10,2) NOT NULL,
    interest_paid DECIMAL(10,2) NOT NULL,
    penalty_amount DECIMAL(10,2) DEFAULT 0.00,
    payment_date DATE NOT NULL,
    due_date DATE NOT NULL,
    payment_method VARCHAR(50),
    is_late BOOLEAN DEFAULT FALSE,
    recorded_by INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS transactions (
    id SERIAL PRIMARY KEY,
    group_id INT NOT NULL REFERENCES savings_groups(id),
    transaction_type VARCHAR(30) NOT NULL
        CHECK (transaction_type IN ('savings', 'loan_disbursement', 'loan_repayment', 'penalty', 'withdrawal')),
    amount DECIMAL(10,2) NOT NULL,
    member_id INT REFERENCES users(id),
    loan_id INT,
    description TEXT,
    reference VARCHAR(100),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS meetings (
    id SERIAL PRIMARY KEY,
    group_id INT NOT NULL REFERENCES savings_groups(id),
    meeting_date DATE NOT NULL,
    meeting_type VARCHAR(50),
    attendance_count INT,
    savings_collected DECIMAL(10,2),
    loans_disbursed DECIMAL(10,2),
    loans_repaid DECIMAL(10,2),
    minutes TEXT,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id),
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notification_preferences (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id),
    sms_enabled SMALLINT DEFAULT 1,
    in_app_enabled SMALLINT DEFAULT 1,
    frequency VARCHAR(20) DEFAULT 'immediate'
        CHECK (frequency IN ('immediate', 'daily', 'weekly')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS recurring_savings (
    id SERIAL PRIMARY KEY,
    member_id INT NOT NULL REFERENCES users(id),
    group_id INT NOT NULL REFERENCES savings_groups(id),
    amount DECIMAL(10,2) NOT NULL,
    frequency VARCHAR(20) NOT NULL DEFAULT 'monthly'
        CHECK (frequency IN ('weekly', 'monthly', 'custom')),
    custom_interval INT DEFAULT NULL,
    next_run_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    active SMALLINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS risk_mitigations (
    id SERIAL PRIMARY KEY,
    risk_type VARCHAR(50) NOT NULL,
    target_id INT NOT NULL,
    mitigation_note TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open'
        CHECK (status IN ('open', 'resolved', 'monitored')),
    updated_by INT NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE OR REPLACE FUNCTION update_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_risk_mitigations_updated_at') THEN
        CREATE TRIGGER trg_risk_mitigations_updated_at
            BEFORE UPDATE ON risk_mitigations
            FOR EACH ROW EXECUTE FUNCTION update_updated_at();
    END IF;
END;
$$ LANGUAGE plpgsql;

-- ============================================================
-- DEFAULT ADMIN USER
-- ============================================================
INSERT INTO users (username, password, full_name, role)
SELECT 'admin', '$2y$12$DPIHj6nevRbKtHP2w5IxRuySstbHa8ANVOSQW0YCX4ZwXuT4P5dsm', 'System Administrator', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

-- ============================================================
-- SEED TEST DATA
-- ============================================================

DO $$
DECLARE
    group_admin_id INT;
    group_id INT;
    alice_id INT;
    bob_id INT;
    carol_id INT;
    david_id INT;
    eve_id INT;
    frank_id INT;
    bob_old_loan INT;
    bob_active_loan INT;
    carol_loan INT;
    david_loan INT;
    frank_loan1 INT;
    frank_loan2 INT;
BEGIN

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

-- -------------------------------------------------------
-- 1. GROUP ADMIN
-- -------------------------------------------------------
INSERT INTO users (username, password, full_name, email, phone, role)
SELECT 'gmwila', '$2y$12$vVoy7KJM729142UOmlFJIeiQyin.iGHymljal1dWu8u0nD/BMo5oq',
       'Grace Mwila', 'gmwila@example.com', '+260970000001', 'group_admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'gmwila')
RETURNING id INTO group_admin_id;

IF group_admin_id IS NULL THEN
    SELECT id INTO group_admin_id FROM users WHERE username = 'gmwila';
END IF;

-- -------------------------------------------------------
-- 2. SAVINGS GROUP
-- -------------------------------------------------------
INSERT INTO savings_groups (group_name, group_code, description, interest_rate, penalty_rate,
                            meeting_day, contribution_amount, cycle_start_date, created_by)
VALUES ('Pamodzi Savings Group', 'PAMODZI001', 'Community savings group in Ikelenge, Zambia',
        10.00, 5.00, 'Saturday', 200.00, CURRENT_DATE, group_admin_id)
RETURNING id INTO group_id;

UPDATE users SET group_id = (SELECT id FROM savings_groups WHERE group_code = 'PAMODZI001') WHERE username = 'gmwila';

-- -------------------------------------------------------
-- ALICE BANDA
-- -------------------------------------------------------
INSERT INTO users (username, password, full_name, email, phone, role, group_id)
VALUES ('alice', '$2y$12$vVoy7KJM729142UOmlFJIeiQyin.iGHymljal1dWu8u0nD/BMo5oq',
        'Alice Banda', 'alice@example.com', '+260970000002', 'member', group_id)
RETURNING id INTO alice_id;

INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, recorded_by) VALUES
(alice_id, group_id, 400, CURRENT_DATE - INTERVAL '11 months', 'cash', group_admin_id),
(alice_id, group_id, 350, CURRENT_DATE - INTERVAL '10 months', 'cash', group_admin_id),
(alice_id, group_id, 450, CURRENT_DATE - INTERVAL '9 months', 'cash', group_admin_id),
(alice_id, group_id, 400, CURRENT_DATE - INTERVAL '8 months', 'cash', group_admin_id),
(alice_id, group_id, 350, CURRENT_DATE - INTERVAL '7 months', 'cash', group_admin_id),
(alice_id, group_id, 500, CURRENT_DATE - INTERVAL '6 months', 'cash', group_admin_id),
(alice_id, group_id, 400, CURRENT_DATE - INTERVAL '5 months', 'cash', group_admin_id),
(alice_id, group_id, 350, CURRENT_DATE - INTERVAL '4 months', 'cash', group_admin_id),
(alice_id, group_id, 450, CURRENT_DATE - INTERVAL '3 months', 'cash', group_admin_id),
(alice_id, group_id, 400, CURRENT_DATE - INTERVAL '2 months', 'cash', group_admin_id),
(alice_id, group_id, 350, CURRENT_DATE - INTERVAL '1 month', 'cash', group_admin_id),
(alice_id, group_id, 400, CURRENT_DATE, 'cash', group_admin_id);

-- -------------------------------------------------------
-- BOB CHANDA
-- -------------------------------------------------------
INSERT INTO users (username, password, full_name, email, phone, role, group_id)
VALUES ('bob', '$2y$12$vVoy7KJM729142UOmlFJIeiQyin.iGHymljal1dWu8u0nD/BMo5oq',
        'Bob Chanda', 'bob@example.com', '+260970000003', 'member', group_id)
RETURNING id INTO bob_id;

INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, recorded_by) VALUES
(bob_id, group_id, 250, CURRENT_DATE - INTERVAL '9 months', 'cash', group_admin_id),
(bob_id, group_id, 250, CURRENT_DATE - INTERVAL '8 months', 'cash', group_admin_id),
(bob_id, group_id, 250, CURRENT_DATE - INTERVAL '7 months', 'cash', group_admin_id),
(bob_id, group_id, 250, CURRENT_DATE - INTERVAL '6 months', 'cash', group_admin_id),
(bob_id, group_id, 250, CURRENT_DATE - INTERVAL '5 months', 'cash', group_admin_id),
(bob_id, group_id, 250, CURRENT_DATE - INTERVAL '4 months', 'cash', group_admin_id),
(bob_id, group_id, 250, CURRENT_DATE - INTERVAL '3 months', 'cash', group_admin_id),
(bob_id, group_id, 250, CURRENT_DATE - INTERVAL '2 months', 'cash', group_admin_id),
(bob_id, group_id, 250, CURRENT_DATE - INTERVAL '1 month', 'cash', group_admin_id),
(bob_id, group_id, 250, CURRENT_DATE, 'cash', group_admin_id);

INSERT INTO loans (member_id, group_id, principal_amount, interest_rate, total_payable, amount_paid, balance,
                   application_date, approval_date, disbursement_date, repayment_period, status, approved_by)
VALUES (bob_id, group_id, 600, 10, 660, 660, 0,
        CURRENT_DATE - INTERVAL '8 months', CURRENT_DATE - INTERVAL '8 months',
        CURRENT_DATE - INTERVAL '8 months', 3, 'repaid', group_admin_id)
RETURNING id INTO bob_old_loan;

INSERT INTO loan_repayments (loan_id, amount, principal_paid, interest_paid, payment_date, due_date, is_late, recorded_by) VALUES
(bob_old_loan, 220, 154, 66, CURRENT_DATE - INTERVAL '7 months', CURRENT_DATE - INTERVAL '5 months', FALSE, group_admin_id),
(bob_old_loan, 220, 154, 66, CURRENT_DATE - INTERVAL '6 months', CURRENT_DATE - INTERVAL '5 months', FALSE, group_admin_id),
(bob_old_loan, 220, 154, 66, CURRENT_DATE - INTERVAL '5 months', CURRENT_DATE - INTERVAL '5 months', FALSE, group_admin_id);

INSERT INTO loans (member_id, group_id, principal_amount, interest_rate, total_payable, amount_paid, balance,
                   application_date, approval_date, disbursement_date, repayment_period, status, approved_by)
VALUES (bob_id, group_id, 1200, 10, 1320, 540, 780,
        CURRENT_DATE - INTERVAL '3 months', CURRENT_DATE - INTERVAL '3 months',
        CURRENT_DATE - INTERVAL '3 months', 6, 'disbursed', group_admin_id)
RETURNING id INTO bob_active_loan;

INSERT INTO loan_repayments (loan_id, amount, principal_paid, interest_paid, payment_date, due_date, is_late, recorded_by) VALUES
(bob_active_loan, 180, 126, 54, CURRENT_DATE - INTERVAL '2 months', CURRENT_DATE - INTERVAL '2 months', FALSE, group_admin_id),
(bob_active_loan, 180, 126, 54, CURRENT_DATE - INTERVAL '1 month', CURRENT_DATE - INTERVAL '1 month', FALSE, group_admin_id),
(bob_active_loan, 180, 126, 54, CURRENT_DATE - INTERVAL '10 days', CURRENT_DATE - INTERVAL '20 days', TRUE, group_admin_id);

-- -------------------------------------------------------
-- CAROL DAKA
-- -------------------------------------------------------
INSERT INTO users (username, password, full_name, email, phone, role, group_id)
VALUES ('carol', '$2y$12$vVoy7KJM729142UOmlFJIeiQyin.iGHymljal1dWu8u0nD/BMo5oq',
        'Carol Daka', 'carol@example.com', '+260970000004', 'member', group_id)
RETURNING id INTO carol_id;

INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, recorded_by) VALUES
(carol_id, group_id, 200, CURRENT_DATE - INTERVAL '5 months', 'cash', group_admin_id),
(carol_id, group_id, 200, CURRENT_DATE - INTERVAL '4 months', 'cash', group_admin_id),
(carol_id, group_id, 200, CURRENT_DATE - INTERVAL '3 months', 'cash', group_admin_id),
(carol_id, group_id, 200, CURRENT_DATE - INTERVAL '2 months', 'cash', group_admin_id),
(carol_id, group_id, 200, CURRENT_DATE - INTERVAL '1 month', 'cash', group_admin_id),
(carol_id, group_id, 200, CURRENT_DATE, 'cash', group_admin_id);

INSERT INTO loans (member_id, group_id, principal_amount, interest_rate, total_payable, amount_paid, balance,
                   application_date, approval_date, disbursement_date, repayment_period, status, approved_by)
VALUES (carol_id, group_id, 800, 10, 880, 360, 520,
        CURRENT_DATE - INTERVAL '4 months', CURRENT_DATE - INTERVAL '4 months',
        CURRENT_DATE - INTERVAL '4 months', 4, 'disbursed', group_admin_id)
RETURNING id INTO carol_loan;

INSERT INTO loan_repayments (loan_id, amount, principal_paid, interest_paid, payment_date, due_date, is_late, recorded_by) VALUES
(carol_loan, 180, 126, 54, CURRENT_DATE - INTERVAL '3 months', CURRENT_DATE - INTERVAL '3 months', FALSE, group_admin_id),
(carol_loan, 180, 126, 54, CURRENT_DATE - INTERVAL '5 days', CURRENT_DATE - INTERVAL '15 days', TRUE, group_admin_id);

-- -------------------------------------------------------
-- DAVID MWALE
-- -------------------------------------------------------
INSERT INTO users (username, password, full_name, email, phone, role, group_id)
VALUES ('david', '$2y$12$vVoy7KJM729142UOmlFJIeiQyin.iGHymljal1dWu8u0nD/BMo5oq',
        'David Mwale', 'david@example.com', '+260970000005', 'member', group_id)
RETURNING id INTO david_id;

INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, recorded_by) VALUES
(david_id, group_id, 100, CURRENT_DATE - INTERVAL '4 months', 'cash', group_admin_id),
(david_id, group_id, 100, CURRENT_DATE - INTERVAL '3 months', 'cash', group_admin_id),
(david_id, group_id, 100, CURRENT_DATE - INTERVAL '2 months', 'cash', group_admin_id);

INSERT INTO loans (member_id, group_id, principal_amount, interest_rate, total_payable, amount_paid, balance,
                   application_date, approval_date, disbursement_date, repayment_period, status, approved_by)
VALUES (david_id, group_id, 500, 10, 550, 0, 550,
        CURRENT_DATE - INTERVAL '7 months', CURRENT_DATE - INTERVAL '7 months',
        CURRENT_DATE - INTERVAL '6 months', 4, 'disbursed', group_admin_id)
RETURNING id INTO david_loan;

-- -------------------------------------------------------
-- EVE BANDA
-- -------------------------------------------------------
INSERT INTO users (username, password, full_name, email, phone, role, group_id)
VALUES ('eve', '$2y$12$vVoy7KJM729142UOmlFJIeiQyin.iGHymljal1dWu8u0nD/BMo5oq',
        'Eve Banda', 'eve@example.com', '+260970000006', 'member', group_id)
RETURNING id INTO eve_id;

INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, recorded_by)
VALUES (eve_id, group_id, 100, CURRENT_DATE, 'cash', group_admin_id);

-- -------------------------------------------------------
-- FRANK ZULU
-- -------------------------------------------------------
INSERT INTO users (username, password, full_name, email, phone, role, group_id)
VALUES ('frank', '$2y$12$vVoy7KJM729142UOmlFJIeiQyin.iGHymljal1dWu8u0nD/BMo5oq',
        'Frank Zulu', 'frank@example.com', '+260970000007', 'member', group_id)
RETURNING id INTO frank_id;

INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, recorded_by) VALUES
(frank_id, group_id, 350, CURRENT_DATE - INTERVAL '11 months', 'cash', group_admin_id),
(frank_id, group_id, 350, CURRENT_DATE - INTERVAL '10 months', 'cash', group_admin_id),
(frank_id, group_id, 350, CURRENT_DATE - INTERVAL '9 months', 'cash', group_admin_id),
(frank_id, group_id, 350, CURRENT_DATE - INTERVAL '8 months', 'cash', group_admin_id),
(frank_id, group_id, 350, CURRENT_DATE - INTERVAL '7 months', 'cash', group_admin_id),
(frank_id, group_id, 350, CURRENT_DATE - INTERVAL '6 months', 'cash', group_admin_id),
(frank_id, group_id, 350, CURRENT_DATE - INTERVAL '5 months', 'cash', group_admin_id),
(frank_id, group_id, 350, CURRENT_DATE - INTERVAL '4 months', 'cash', group_admin_id),
(frank_id, group_id, 350, CURRENT_DATE - INTERVAL '3 months', 'cash', group_admin_id),
(frank_id, group_id, 350, CURRENT_DATE - INTERVAL '2 months', 'cash', group_admin_id),
(frank_id, group_id, 350, CURRENT_DATE - INTERVAL '1 month', 'cash', group_admin_id),
(frank_id, group_id, 350, CURRENT_DATE, 'cash', group_admin_id);

INSERT INTO loans (member_id, group_id, principal_amount, interest_rate, total_payable, amount_paid, balance,
                   application_date, approval_date, disbursement_date, repayment_period, status, approved_by)
VALUES (frank_id, group_id, 1000, 10, 1100, 480, 620,
        CURRENT_DATE - INTERVAL '5 months', CURRENT_DATE - INTERVAL '5 months',
        CURRENT_DATE - INTERVAL '5 months', 6, 'disbursed', group_admin_id)
RETURNING id INTO frank_loan1;

INSERT INTO loan_repayments (loan_id, amount, principal_paid, interest_paid, payment_date, due_date, is_late, recorded_by) VALUES
(frank_loan1, 160, 112, 48, CURRENT_DATE - INTERVAL '4 months', CURRENT_DATE - INTERVAL '4 months', FALSE, group_admin_id),
(frank_loan1, 160, 112, 48, CURRENT_DATE - INTERVAL '3 months', CURRENT_DATE - INTERVAL '3 months', FALSE, group_admin_id),
(frank_loan1, 160, 112, 48, CURRENT_DATE - INTERVAL '2 months', CURRENT_DATE - INTERVAL '2 months', FALSE, group_admin_id);

INSERT INTO loans (member_id, group_id, principal_amount, interest_rate, total_payable, amount_paid, balance,
                   application_date, approval_date, disbursement_date, repayment_period, status, approved_by)
VALUES (frank_id, group_id, 800, 10, 880, 360, 520,
        CURRENT_DATE - INTERVAL '3 months', CURRENT_DATE - INTERVAL '3 months',
        CURRENT_DATE - INTERVAL '3 months', 4, 'disbursed', group_admin_id)
RETURNING id INTO frank_loan2;

INSERT INTO loan_repayments (loan_id, amount, principal_paid, interest_paid, payment_date, due_date, is_late, recorded_by) VALUES
(frank_loan2, 180, 126, 54, CURRENT_DATE - INTERVAL '2 months', CURRENT_DATE - INTERVAL '2 months', FALSE, group_admin_id),
(frank_loan2, 180, 126, 54, CURRENT_DATE - INTERVAL '7 days', CURRENT_DATE - INTERVAL '10 days', TRUE, group_admin_id);

-- -------------------------------------------------------
-- TRANSACTIONS LOG
-- -------------------------------------------------------
INSERT INTO transactions (group_id, transaction_type, amount, member_id, description, created_by)
SELECT sc.group_id, 'savings', sc.amount, sc.member_id,
       'Savings contribution of K' || sc.amount::TEXT, sc.recorded_by
FROM savings_contributions sc;

INSERT INTO transactions (group_id, transaction_type, amount, member_id, loan_id, description, created_by) VALUES
(group_id, 'loan_disbursement', 600, bob_id, bob_old_loan, 'Loan disbursement of K600', group_admin_id),
(group_id, 'loan_disbursement', 1200, bob_id, bob_active_loan, 'Loan disbursement of K1200', group_admin_id),
(group_id, 'loan_disbursement', 800, carol_id, carol_loan, 'Loan disbursement of K800', group_admin_id),
(group_id, 'loan_disbursement', 500, david_id, david_loan, 'Loan disbursement of K500', group_admin_id),
(group_id, 'loan_disbursement', 1000, frank_id, frank_loan1, 'Loan disbursement of K1000', group_admin_id),
(group_id, 'loan_disbursement', 800, frank_id, frank_loan2, 'Loan disbursement of K800', group_admin_id);

INSERT INTO transactions (group_id, transaction_type, amount, loan_id, description, created_by)
SELECT l.group_id, 'loan_repayment', lr.amount, lr.loan_id,
       'Loan repayment of K' || lr.amount::TEXT, lr.recorded_by
FROM loan_repayments lr
JOIN loans l ON l.id = lr.loan_id;

-- -------------------------------------------------------
-- UPDATE GROUP AGGREGATES
-- -------------------------------------------------------
UPDATE savings_groups sg
SET total_savings = (SELECT COALESCE(SUM(amount), 0) FROM savings_contributions WHERE savings_contributions.group_id = sg.id),
    total_loans   = (SELECT COALESCE(SUM(principal_amount), 0) FROM loans WHERE loans.group_id = sg.id AND loans.status != 'repaid')
WHERE sg.id = group_id;

RAISE NOTICE 'SEED COMPLETE';

END;
$$ LANGUAGE plpgsql;

-- ============================================================
-- LOAN OFFICER (outside DO block to use simple SELECT)
-- ============================================================
INSERT INTO users (username, password, full_name, email, phone, role)
SELECT 'loanofficer', '$2y$12$8XBJtcncpaPbmWPXmbAzuuE4ifxVi.XvsTxbaz55JsRHKsaUhwsEi',
       'Loan Officer', 'loanofficer@example.com', '+260970000008', 'loan_officer'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'loanofficer');
