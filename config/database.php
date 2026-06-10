<?php

$driver   = getenv('DB_DRIVER') ?: 'mysql';
$host     = getenv('DB_HOST') ?: 'localhost';
$port     = getenv('DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306');
$dbname   = getenv('DB_NAME') ?: 'rsgms_db';
$username = getenv('DB_USER') ?: ($driver === 'pgsql' ? 'postgres' : 'root');
$password = getenv('DB_PASSWORD') ?: '';

if ($driver === 'pgsql') {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $options = [];
} else {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $options = [PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false];
}

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($driver === 'pgsql') {
        createTablesPgsql($pdo);
    } else {
        createTablesMysql($pdo);
    }
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

function createTablesMysql($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20),
        role ENUM('admin', 'group_admin', 'loan_officer', 'member') DEFAULT 'member',
        group_id INT,
        status ENUM('active','pending') DEFAULT 'active',
        last_login DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_users_status (status)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS savings_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
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
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS savings_contributions (
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
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS loans (
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
        repayment_period INT,
        repayment_frequency VARCHAR(20) DEFAULT 'monthly',
        status ENUM('pending', 'approved', 'rejected', 'disbursed', 'repaid', 'defaulted') DEFAULT 'pending',
        approved_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (member_id) REFERENCES users(id),
        FOREIGN KEY (group_id) REFERENCES savings_groups(id),
        FOREIGN KEY (approved_by) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS loan_repayments (
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
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
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
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS meetings (
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
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_preferences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        sms_enabled TINYINT(1) DEFAULT 1,
        in_app_enabled TINYINT(1) DEFAULT 1,
        frequency ENUM('immediate','daily','weekly') DEFAULT 'immediate',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS recurring_savings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        group_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        frequency ENUM('weekly','monthly','custom') DEFAULT 'monthly',
        custom_interval INT DEFAULT NULL,
        next_run_date DATE NOT NULL,
        end_date DATE DEFAULT NULL,
        active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (member_id) REFERENCES users(id),
        FOREIGN KEY (group_id) REFERENCES savings_groups(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS risk_mitigations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        risk_type VARCHAR(50) NOT NULL,
        target_id INT NOT NULL,
        mitigation_note TEXT NOT NULL,
        status ENUM('open', 'resolved', 'monitored') DEFAULT 'open',
        updated_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (updated_by) REFERENCES users(id)
    )");

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES ('admin', ?, 'System Administrator', 'admin')")
            ->execute([$hashedPassword]);
    }
}

function createTablesPgsql($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
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
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_status ON users(status)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS savings_groups (
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
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS savings_contributions (
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
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS loans (
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
            CHECK (status IN ('pending', 'approved', 'rejected', 'disbursed', 'repaid', 'defaulted')),
        approved_by INT REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS loan_repayments (
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
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
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
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS meetings (
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
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL REFERENCES users(id),
        title VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_preferences (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL REFERENCES users(id),
        sms_enabled SMALLINT DEFAULT 1,
        in_app_enabled SMALLINT DEFAULT 1,
        frequency VARCHAR(20) DEFAULT 'immediate'
            CHECK (frequency IN ('immediate', 'daily', 'weekly')),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS recurring_savings (
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
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS risk_mitigations (
        id SERIAL PRIMARY KEY,
        risk_type VARCHAR(50) NOT NULL,
        target_id INT NOT NULL,
        mitigation_note TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'open'
            CHECK (status IN ('open', 'resolved', 'monitored')),
        updated_by INT NOT NULL REFERENCES users(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("
        CREATE OR REPLACE FUNCTION update_updated_at()
        RETURNS TRIGGER AS $$
        BEGIN
            NEW.updated_at = CURRENT_TIMESTAMP;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql
    ");

    $pdo->exec("
        DO $$
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_risk_mitigations_updated_at') THEN
                CREATE TRIGGER trg_risk_mitigations_updated_at
                    BEFORE UPDATE ON risk_mitigations
                    FOR EACH ROW EXECUTE FUNCTION update_updated_at();
            END IF;
        END;
        $$ LANGUAGE plpgsql
    ");

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES ('admin', ?, 'System Administrator', 'admin')")
            ->execute([$hashedPassword]);
    }
}
