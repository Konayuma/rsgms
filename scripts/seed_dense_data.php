<?php
// Dense data seeder for RSGMS - creates many mock users, groups, savings, loans and repayments
// Usage: php scripts/seed_dense_data.php

require_once __DIR__ . '/../config/database.php'; // provides $pdo and ensures tables exist

function random_amount($min, $max) { return round(mt_rand($min*100, $max*100)/100, 2); }

try {
    $pdo->beginTransaction();

    // Create a few groups if not present
    $groups = [
        ['Pamodzi Savings','PAM001'],
        ['Ubulimi Farmers','UBI001'],
        ['Chikondi Community','CHK001']
    ];

    $groupIds = [];
    $stmtGetGroup = $pdo->prepare("SELECT id FROM savings_groups WHERE group_code = ?");
    $stmtInsertGroup = $pdo->prepare("INSERT INTO savings_groups (group_name, group_code, description, interest_rate, penalty_rate, contribution_amount, meeting_day, cycle_start_date, cycle_end_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($groups as $g) {
        $stmtGetGroup->execute([$g[1]]);
        $row = $stmtGetGroup->fetch();
        if ($row) {
            $groupIds[] = $row['id'];
            continue;
        }
        $stmtInsertGroup->execute([$g[0], $g[1], "$g[0] - community savings group.", 10.00, 5.00, 50.00, 'Saturday', date('Y-m-01'), date('Y-m-t'), 1]);
        $groupIds[] = $pdo->lastInsertId();
    }

    // Create many mock members across groups
    $stmtInsertUser = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, group_id) VALUES (?, ?, ?, ?, ?, 'member', ?)");
    $stmtCheckUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");

    $memberIds = [];
    for ($g = 0; $g < count($groupIds); $g++) {
        $gid = $groupIds[$g];
        // create 60 members per group (total ~180)
        for ($i = 1; $i <= 60; $i++) {
            $username = strtolower($groups[$g][0]) . '_m' . $i;
            $username = preg_replace('/[^a-z0-9_]/', '_', $username);
            $stmtCheckUser->execute([$username]);
            if ($stmtCheckUser->fetch()) continue;

            $password = password_hash('password123', PASSWORD_DEFAULT);
            $full = ucfirst("Member") . ' ' . ($i + ($g*60));
            $email = "user" . ($i + ($g*60)) . "@example.test";
            $phone = '260' . str_pad(mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);

            $stmtInsertUser->execute([$username, $password, $full, $email, $phone, $gid]);
            $memberIds[] = $pdo->lastInsertId();
        }
    }

    // Create savings contributions for each member - simulate 6-24 contributions
    $stmtInsertSaving = $pdo->prepare("INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, transaction_ref, recorded_by, is_self_service) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($memberIds as $mid) {
        $stmt = $pdo->prepare("SELECT group_id FROM users WHERE id = ?");
        $stmt->execute([$mid]);
        $g = $stmt->fetchColumn();
        $count = mt_rand(6, 24);
        $running = 0;
        for ($c = 0; $c < $count; $c++) {
            $amt = random_amount(20, 200);
            $running += $amt;
            $date = date('Y-m-d', strtotime('-' . mt_rand(1, 720) . ' days'));
            $stmtInsertSaving->execute([$mid, $g, $amt, $date, 'cash', 'SEED' . mt_rand(100000,999999), 1, mt_rand(0,1)]);
        }
    }

    // Create some loans for a subset of members and repayments
    $stmtInsertLoan = $pdo->prepare("INSERT INTO loans (member_id, group_id, principal_amount, interest_rate, total_payable, amount_paid, balance, application_date, approval_date, disbursement_date, repayment_period, repayment_frequency, status, approved_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtInsertRepayment = $pdo->prepare("INSERT INTO loan_repayments (loan_id, amount, principal_paid, interest_paid, penalty_amount, payment_date, due_date, payment_method, is_late, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $loaned = 0;
    foreach ($memberIds as $mid) {
        if (mt_rand(1,100) > 12) continue; // ~12% get loans
        $stmt = $pdo->prepare("SELECT group_id FROM users WHERE id = ?"); $stmt->execute([$mid]); $g = $stmt->fetchColumn();
        $principal = random_amount(100, 1500);
        $ir = 12.0;
        $interest = round($principal * ($ir/100), 2);
        $total = $principal + $interest;
        $app = date('Y-m-d', strtotime('-' . mt_rand(10, 400) . ' days'));
        $apr = date('Y-m-d', strtotime($app . ' + ' . mt_rand(1,30) . ' days'));
        $disb = date('Y-m-d', strtotime($apr . ' + 2 days'));
        $rep = mt_rand(3,12);
        $status = (mt_rand(1,100) > 30) ? 'disbursed' : 'approved';
        $amount_paid = 0.00;

        $stmtInsertLoan->execute([$mid, $g, $principal, $ir, $total, $amount_paid, $total, $app, $apr, $disb, $rep, 'monthly', $status, 1]);
        $loanId = $pdo->lastInsertId();
        $loaned++;

        // generate 1..repayment_count repayments
        $reps = mt_rand(0, $rep);
        $paid = 0;
        for ($r=0;$r<$reps;$r++) {
            $amt = round($total / $rep, 2);
            $principal_paid = round($amt * 0.7, 2);
            $interest_paid = round($amt * 0.3, 2);
            $pay_date = date('Y-m-d', strtotime($disb . ' + ' . ($r+1) . ' months'));
            $due_date = date('Y-m-d', strtotime($disb . ' + ' . ($r+1) . ' months'));
            $is_late = (mt_rand(1,100) <= 15) ? 1 : 0;
            $stmtInsertRepayment->execute([$loanId, $amt, $principal_paid, $interest_paid, 0.00, $pay_date, $due_date, 'cash', $is_late, 1]);
            $paid += $amt;
        }

        if ($paid > 0) {
            $stmtUpd = $pdo->prepare("UPDATE loans SET amount_paid = amount_paid + ?, balance = balance - ? WHERE id = ?");
            $stmtUpd->execute([$paid, $paid, $loanId]);
        }
    }

    $pdo->commit();
    echo "Seeding complete: created " . count($memberIds) . " members and $loaned loans.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Seeder failed: " . $e->getMessage() . "\n";
}

?>