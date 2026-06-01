<?php
// File: config/database.php
// Database configuration file

$host     = getenv('DB_HOST') ?: 'localhost';
$port     = getenv('DB_PORT') ?: '3306';
$dbname   = getenv('DB_NAME') ?: 'rsgms_db';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$sslCa    = getenv('DB_SSL_CA') ?: '';

$sslOpt = [];
if ($sslCa) {
    $sslOpt = [
        PDO::MYSQL_ATTR_SSL_CA                => $sslCa,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];
}

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $username, $password, $sslOpt);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    createTables($pdo);
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

function createTables($pdo) {
    // Users table
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
    
    // Savings groups table
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
    
    // Savings contributions table
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
    
    // Loans table
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
        repayment_period INT COMMENT 'in months',
        repayment_frequency VARCHAR(20) DEFAULT 'monthly',
        status ENUM('pending', 'approved', 'disbursed', 'repaid', 'defaulted') DEFAULT 'pending',
        approved_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (member_id) REFERENCES users(id),
        FOREIGN KEY (group_id) REFERENCES savings_groups(id),
        FOREIGN KEY (approved_by) REFERENCES users(id)
    )");
    
    // Loan repayments table
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
    
    // Transactions log table
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
    
    // Meeting records table
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
    
    // Notifications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Notification preferences table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_preferences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        sms_enabled TINYINT(1) DEFAULT 1,
        in_app_enabled TINYINT(1) DEFAULT 1,
        frequency ENUM('immediate','daily','weekly') DEFAULT 'immediate',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Recurring savings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS recurring_savings (
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
    )");
    
    // Risk mitigations table
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
    
    // Insert default admin if not exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES ('admin', ?, 'System Administrator', 'admin')")
            ->execute([$hashedPassword]);
    }
}
?>
