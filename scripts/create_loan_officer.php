<?php
/**
 * Create a Loan Officer Account
 *
 * Usage: php scripts/create_loan_officer.php <username> <password> <full_name> <group_id> [email] [phone]
 *
 * Examples:
 *   php scripts/create_loan_officer.php loanofficer pass123 "John Doe" 1
 *   php scripts/create_loan_officer.php loanofficer pass123 "John Doe" 1 john@example.com
 */

require_once __DIR__ . '/../config/database.php';

// List groups
if ($argc === 1 || (isset($argv[1]) && $argv[1] === '--list-groups')) {
    $groups = $pdo->query("SELECT id, group_name, invitation_code FROM savings_groups ORDER BY group_name")->fetchAll();
    if (empty($groups)) {
        echo "No savings groups found.\n";
        exit(1);
    }
    echo "Available groups:\n";
    foreach ($groups as $g) {
        printf("  [%d] %s (Code: %s)\n", $g['id'], $g['group_name'], $g['invitation_code']);
    }
    echo "\nUsage: php scripts/create_loan_officer.php <username> <password> <full_name> <group_id> [email] [phone]\n";
    exit(0);
}

if ($argc < 5) {
    echo "Usage: php scripts/create_loan_officer.php <username> <password> <full_name> <group_id> [email] [phone]\n";
    echo "       php scripts/create_loan_officer.php --list-groups\n";
    exit(1);
}

$username = $argv[1];
$password = $argv[2];
$fullName = $argv[3];
$groupId  = (int) $argv[4];
$email    = $argv[5] ?? null;
$phone    = $argv[6] ?? null;

// Validate group exists
$stmt = $pdo->prepare("SELECT id, group_name FROM savings_groups WHERE id = ?");
$stmt->execute([$groupId]);
$group = $stmt->fetch();

if (!$group) {
    echo "Error: Group ID $groupId not found.\n";
    exit(1);
}

// Validate password
if (strlen($password) < 6) {
    echo "Error: Password must be at least 6 characters.\n";
    exit(1);
}

$hashed = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password, full_name, email, phone, role, group_id, status)
        VALUES (?, ?, ?, ?, ?, 'loan_officer', ?, 'active')
    ");
    $stmt->execute([$username, $hashed, $fullName, $email, $phone, $groupId]);

    echo "Loan officer created successfully!\n";
    printf("  Username: %s\n", $username);
    printf("  Name:     %s\n", $fullName);
    printf("  Group:    %s (ID: %d)\n", $group['group_name'], $groupId);
    echo "  Role:     loan_officer\n";
} catch (PDOException $e) {
    if ($e->getCode() == 23000 && str_contains($e->getMessage(), 'username')) {
        echo "Error: Username '$username' is already taken.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
    exit(1);
}
