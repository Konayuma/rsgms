<?php
/**
 * Seed Test Data for RSGMS
 *
 * Run: php seed_test_data.php
 * Populates the database with demo users/groups/transactions
 * to exercise the capacity-driven risk profiling engine.
 */

require_once 'config/database.php';

echo "=== RSGMS Test Data Seeder ===\n\n";

// ---------------------------------------------------------------------------
// 1. Clear existing demo data (preserve admin)
// ---------------------------------------------------------------------------
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    foreach (['loan_repayments', 'transactions', 'savings_contributions', 'loans',
              'meetings', 'notifications', 'notification_preferences',
              'recurring_savings', 'risk_mitigations', 'users', 'savings_groups'] as $table) {
        // Don't truncate — we need to avoid re-creating admin. Delete non-admin.
        if ($table === 'users') {
            $pdo->exec("DELETE FROM users WHERE role != 'admin'");
        } elseif ($table === 'savings_groups') {
            $pdo->exec("DELETE FROM savings_groups WHERE 1");
        } else {
            $pdo->exec("DELETE FROM $table WHERE 1");
        }
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "[OK] Cleared existing demo data\n";
} catch (PDOException $e) {
    echo "[WARN] Could not clear tables (OK if first run): " . $e->getMessage() . "\n";
}

// ---------------------------------------------------------------------------
// 2. Re-ensure admin exists
// ---------------------------------------------------------------------------
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
$stmt->execute();
$admin = $stmt->fetch();
if (!$admin) {
    $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES ('admin', ?, 'System Administrator', 'admin')")
        ->execute([password_hash('admin123', PASSWORD_DEFAULT)]);
    $admin_id = $pdo->lastInsertId();
    echo "[OK] Created admin user (admin / admin123)\n";
} else {
    $admin_id = $admin['id'];
    echo "[OK] Admin user exists (id=$admin_id)\n";
}

// ---------------------------------------------------------------------------
// 3. Create Group Admin
// ---------------------------------------------------------------------------
$password = password_hash('password123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role)
                        VALUES ('gmwila', ?, 'Grace Mwila', 'gmwila@example.com', '+260970000001', 'group_admin')");
$stmt->execute([$password]);
$group_admin_id = $pdo->lastInsertId();
echo "[OK] Created group admin: Grace Mwila (gmwila / password123)\n";

// ---------------------------------------------------------------------------
// 4. Create Savings Group
// ---------------------------------------------------------------------------
$stmt = $pdo->prepare("INSERT INTO savings_groups (group_name, group_code, description, interest_rate, penalty_rate,
                        meeting_day, contribution_amount, cycle_start_date, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([
    'Pamodzi Savings Group',
    'PAMODZI001',
    'Community savings group in Ikelenge, Zambia',
    10.00,
    5.00,
    'Saturday',
    200.00,
    '2025-06-01',
    $group_admin_id
]);
$group_id = $pdo->lastInsertId();

// Link group admin to the group
$pdo->prepare("UPDATE users SET group_id = ? WHERE id = ?")->execute([$group_id, $group_admin_id]);

// Update aggregate totals on the group
echo "[OK] Created savings group: Pamodzi Savings Group\n";

// ---------------------------------------------------------------------------
// 5. Helper: insert member
// ---------------------------------------------------------------------------
function createMember($pdo, $username, $fullName, $group_id, $passwordHash) {
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, group_id)
                            VALUES (?, ?, ?, ?, ?, 'member', ?)");
    $stmt->execute([$username, $passwordHash, $fullName,
        "$username@example.com", '+26097' . str_pad(random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
        $group_id]);
    return $pdo->lastInsertId();
}

function addSavings($pdo, $member_id, $group_id, $amount, $date, $recorded_by) {
    $stmt = $pdo->prepare("INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, recorded_by)
                            VALUES (?, ?, ?, ?, 'cash', ?)");
    $stmt->execute([$member_id, $group_id, $amount, $date, $recorded_by]);

    $stmt = $pdo->prepare("INSERT INTO transactions (group_id, transaction_type, amount, member_id, description, created_by)
                            VALUES (?, 'savings', ?, ?, ?, ?)");
    $stmt->execute([$group_id, $amount, $member_id, "Savings contribution of K$amount", $recorded_by]);
}

function addLoan($pdo, $member_id, $group_id, $principal, $rate, $repayment_months, $status, $applied, $approved, $disbursed, $recorded_by) {
    $interest = $principal * ($rate / 100);
    $total = $principal + $interest;
    $stmt = $pdo->prepare("INSERT INTO loans (member_id, group_id, principal_amount, interest_rate, total_payable, balance,
                            application_date, approval_date, disbursement_date, repayment_period, status, approved_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$member_id, $group_id, $principal, $rate, $total, $total,
        $applied, $approved, $disbursed, $repayment_months, $status, $recorded_by]);
    $loan_id = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO transactions (group_id, transaction_type, amount, member_id, loan_id, description, created_by)
                            VALUES (?, 'loan_disbursement', ?, ?, ?, ?, ?)");
    $stmt->execute([$group_id, $principal, $member_id, $loan_id, "Loan disbursement of K$principal", $recorded_by]);

    return $loan_id;
}

function addRepayment($pdo, $loan_id, $amount, $payment_date, $due_date, $is_late, $recorded_by) {
    $principal_part = round($amount * 0.7, 2);
    $interest_part = $amount - $principal_part;

    $stmt = $pdo->prepare("INSERT INTO loan_repayments (loan_id, amount, principal_paid, interest_paid, penalty_amount,
                            payment_date, due_date, is_late, recorded_by)
                            VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?)");
    $stmt->execute([$loan_id, $amount, $principal_part, $interest_part, $payment_date, $due_date, $is_late, $recorded_by]);

    // Update loan balance
    $pdo->prepare("UPDATE loans SET amount_paid = amount_paid + ?, balance = total_payable - amount_paid WHERE id = ?")
        ->execute([$amount, $loan_id]);

    $stmt = $pdo->prepare("INSERT INTO transactions (group_id, transaction_type, amount, loan_id, description, created_by)
                            VALUES ((SELECT group_id FROM loans WHERE id = ?), 'loan_repayment', ?, ?, ?, ?)");
    $stmt->execute([$loan_id, $amount, $loan_id, "Loan repayment of K$amount", $recorded_by]);
}

$hash = password_hash('password123', PASSWORD_DEFAULT);

// ---------------------------------------------------------------------------
// ALICE BANDA — Heavy saver, no debt → Grade A
// ---------------------------------------------------------------------------
$alice = createMember($pdo, 'alice', 'Alice Banda', $group_id, $hash);
$dates_alice = [];
for ($m = 11; $m >= 0; $m--) {
    $d = date('Y-m-d', strtotime("-{$m} months"));
    $dates_alice[] = $d;
    addSavings($pdo, $alice, $group_id, 350 + ($m % 3) * 50, $d, $group_admin_id);
}
// 12 contributions of K350-450 = ~K4,800
echo "[OK] Created Alice Banda (alice) — expected Grade A\n";

// ---------------------------------------------------------------------------
// BOB CHANDA — Good saver, repaid old loan + active loan → Grade B
// ---------------------------------------------------------------------------
$bob = createMember($pdo, 'bob', 'Bob Chanda', $group_id, $hash);
for ($m = 9; $m >= 0; $m--) {
    $d = date('Y-m-d', strtotime("-{$m} months"));
    addSavings($pdo, $bob, $group_id, 250, $d, $group_admin_id);
}
// 10 × K250 = K2,500

// Repaid loan from 8 months ago
$old_loan = addLoan($pdo, $bob, $group_id, 600, 10, 3, 'repaid',
    date('Y-m-d', strtotime('-8 months')),
    date('Y-m-d', strtotime('-8 months')),
    date('Y-m-d', strtotime('-8 months')),
    $group_admin_id);
$due_old = date('Y-m-d', strtotime('-5 months'));
addRepayment($pdo, $old_loan, 220, date('Y-m-d', strtotime('-7 months')), $due_old, false, $group_admin_id);
addRepayment($pdo, $old_loan, 220, date('Y-m-d', strtotime('-6 months')), $due_old, false, $group_admin_id);
addRepayment($pdo, $old_loan, 220, date('Y-m-d', strtotime('-5 months')), $due_old, false, $group_admin_id);
// Mark repaid
$pdo->prepare("UPDATE loans SET status = 'repaid', amount_paid = total_payable, balance = 0 WHERE id = ?")
    ->execute([$old_loan]);

// Active loan — 3 months ago, 2 on-time payments, 1 late
$active_loan = addLoan($pdo, $bob, $group_id, 1200, 10, 6, 'disbursed',
    date('Y-m-d', strtotime('-3 months')),
    date('Y-m-d', strtotime('-3 months')),
    date('Y-m-d', strtotime('-3 months')),
    $group_admin_id);
$due_active_base = date('Y-m-d', strtotime('-3 months'));
for ($i = 1; $i <= 3; $i++) {
    $due = date('Y-m-d', strtotime("+" . ($i - 1) . " months", strtotime('-3 months')));
    $paid = date('Y-m-d', strtotime("+" . ($i - 1) . " months", strtotime('-3 months')));
    $late = false;
    // 3rd payment was 10 days late
    if ($i === 3) {
        $paid = date('Y-m-d', strtotime("+" . ($i - 1) . " months +10 days", strtotime('-3 months')));
        $late = true;
    }
    addRepayment($pdo, $active_loan, 180, $paid, $due, $late, $group_admin_id);
}
// Recalculate balance properly
$stmt = $pdo->prepare("SELECT total_payable, amount_paid FROM loans WHERE id = ?");
$stmt->execute([$active_loan]);
$l = $stmt->fetch();
$pdo->prepare("UPDATE loans SET balance = GREATEST(total_payable - amount_paid, 0) WHERE id = ?")
    ->execute([$active_loan]);

echo "[OK] Created Bob Chanda (bob) — expected Grade B\n";

// ---------------------------------------------------------------------------
// CAROL DAKA — Moderate saver, active loan with late payments → Grade C
// ---------------------------------------------------------------------------
$carol = createMember($pdo, 'carol', 'Carol Daka', $group_id, $hash);
for ($m = 5; $m >= 0; $m--) {
    $d = date('Y-m-d', strtotime("-{$m} months"));
    addSavings($pdo, $carol, $group_id, 200, $d, $group_admin_id);
}
// 6 × K200 = K1,200

$carol_loan = addLoan($pdo, $carol, $group_id, 800, 10, 4, 'disbursed',
    date('Y-m-d', strtotime('-4 months')),
    date('Y-m-d', strtotime('-4 months')),
    date('Y-m-d', strtotime('-4 months')),
    $group_admin_id);
for ($i = 1; $i <= 2; $i++) {
    $due = date('Y-m-d', strtotime("+" . ($i - 1) . " months", strtotime('-4 months')));
    $late = ($i === 2);
    $offset = $late ? " +5 days" : "";
    $paid = date('Y-m-d', strtotime("+" . ($i - 1) . " months{$offset}", strtotime('-4 months')));
    addRepayment($pdo, $carol_loan, 180, $paid, $due, $late, $group_admin_id);
}
$pdo->prepare("UPDATE loans SET balance = GREATEST(total_payable - amount_paid, 0) WHERE id = ?")
    ->execute([$carol_loan]);

echo "[OK] Created Carol Daka (carol) — expected Grade C\n";

// ---------------------------------------------------------------------------
// DAVID MWALE — Low savings, overdue loan → Grade E
// ---------------------------------------------------------------------------
$david = createMember($pdo, 'david', 'David Mwale', $group_id, $hash);
addSavings($pdo, $david, $group_id, 100, date('Y-m-d', strtotime('-4 months')), $group_admin_id);
addSavings($pdo, $david, $group_id, 100, date('Y-m-d', strtotime('-3 months')), $group_admin_id);
addSavings($pdo, $david, $group_id, 100, date('Y-m-d', strtotime('-2 months')), $group_admin_id);
// K300 total

// Overdue loan — disbursed 6 months ago, 4 month term, now well past due
$david_loan = addLoan($pdo, $david, $group_id, 500, 10, 4, 'disbursed',
    date('Y-m-d', strtotime('-7 months')),
    date('Y-m-d', strtotime('-7 months')),
    date('Y-m-d', strtotime('-6 months')),
    $group_admin_id);
// No repayments — loan is overdue
echo "[OK] Created David Mwale (david) — expected Grade E\n";

// ---------------------------------------------------------------------------
// EVE BANDA — New member, tiny savings, no loans → Grade A but tiny ceiling
// ---------------------------------------------------------------------------
$eve = createMember($pdo, 'eve', 'Eve Banda', $group_id, $hash);
addSavings($pdo, $eve, $group_id, 100, date('Y-m-d'), $group_admin_id);
echo "[OK] Created Eve Banda (eve) — expected Grade A (low ceiling)\n";

// ---------------------------------------------------------------------------
// FRANK ZULU — High saver, multiple active loans, mostly good → Grade B
// ---------------------------------------------------------------------------
$frank = createMember($pdo, 'frank', 'Frank Zulu', $group_id, $hash);
for ($m = 11; $m >= 0; $m--) {
    $d = date('Y-m-d', strtotime("-{$m} months"));
    addSavings($pdo, $frank, $group_id, 350, $d, $group_admin_id);
}
// 12 × K350 = K4,200

// Loan 1: 5 months ago, 3 repayments
$frank_loan1 = addLoan($pdo, $frank, $group_id, 1000, 10, 6, 'disbursed',
    date('Y-m-d', strtotime('-5 months')),
    date('Y-m-d', strtotime('-5 months')),
    date('Y-m-d', strtotime('-5 months')),
    $group_admin_id);
for ($i = 1; $i <= 3; $i++) {
    $due = date('Y-m-d', strtotime("+" . ($i - 1) . " months", strtotime('-5 months')));
    $paid = date('Y-m-d', strtotime("+" . ($i - 1) . " months", strtotime('-5 months')));
    addRepayment($pdo, $frank_loan1, 160, $paid, $due, false, $group_admin_id);
}

// Loan 2: 3 months ago, 2 repayments (1 late)
$frank_loan2 = addLoan($pdo, $frank, $group_id, 800, 10, 4, 'disbursed',
    date('Y-m-d', strtotime('-3 months')),
    date('Y-m-d', strtotime('-3 months')),
    date('Y-m-d', strtotime('-3 months')),
    $group_admin_id);
for ($i = 1; $i <= 2; $i++) {
    $due = date('Y-m-d', strtotime("+" . ($i - 1) . " months", strtotime('-3 months')));
    $late = ($i === 2);
    $offset = $late ? " +3 days" : "";
    $paid = date('Y-m-d', strtotime("+" . ($i - 1) . " months{$offset}", strtotime('-3 months')));
    addRepayment($pdo, $frank_loan2, 180, $paid, $due, $late, $group_admin_id);
}

$pdo->prepare("UPDATE loans SET balance = GREATEST(total_payable - amount_paid, 0) WHERE id = ?")
    ->execute([$frank_loan1]);
$pdo->prepare("UPDATE loans SET balance = GREATEST(total_payable - amount_paid, 0) WHERE id = ?")
    ->execute([$frank_loan2]);

echo "[OK] Created Frank Zulu (frank) — expected Grade B\n";

// ---------------------------------------------------------------------------
// 6. Update group-level aggregates
// ---------------------------------------------------------------------------
$pdo->exec("UPDATE savings_groups sg
    SET total_savings = (SELECT COALESCE(SUM(amount),0) FROM savings_contributions WHERE group_id = sg.id),
        total_loans   = (SELECT COALESCE(SUM(principal_amount),0) FROM loans WHERE group_id = sg.id AND status != 'repaid')
    WHERE id = $group_id");

echo "\n=== SEED COMPLETE ===\n";
echo "\nTest Credentials:\n";
echo "  Group Admin: gmwila / password123\n";
echo "  Members:     alice, bob, carol, david, eve, frank / password123\n";
echo "  Admin:       admin / admin123\n";
echo "\nGo to new_loan.php, select a member from the dropdown,\n";
echo "and the dynamic risk profile card will show each member's grade.\n";
