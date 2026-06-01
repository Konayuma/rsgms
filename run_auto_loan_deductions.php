<?php
// Script: run_auto_loan_deductions.php
// Run this script via cron to auto-deduct overdue loan repayments from members' savings.
// Recommended cron: 0 6 * * * php /path/to/run_auto_loan_deductions.php >> /var/log/loan_deductions.log 2>&1
require_once __DIR__ . '/config/database.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting auto loan deduction runner...\n";

try {
    $today = date('Y-m-d');

    $stmt = $pdo->prepare("SELECT l.*, u.full_name as member_name, sg.group_name 
        FROM loans l 
        JOIN users u ON l.member_id = u.id 
        LEFT JOIN savings_groups sg ON l.group_id = sg.id 
        WHERE l.status = 'disbursed' AND l.balance > 0");
    $stmt->execute();
    $loans = $stmt->fetchAll();

    $processed = 0;
    $skipped_no_savings = 0;
    $skipped_not_due = 0;
    $errors = 0;

    foreach ($loans as $loan) {
        $due_date = $loan['disbursement_date']
            ? date('Y-m-d', strtotime("+{$loan['repayment_period']} months", strtotime($loan['disbursement_date'])))
            : null;

        if (!$due_date || $due_date > $today) {
            $skipped_not_due++;
            continue;
        }

        $member_id = $loan['member_id'];
        $group_id = $loan['group_id'];
        $loan_id = $loan['id'];
        $outstanding = $loan['balance'];

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_savings FROM savings_contributions WHERE member_id = ? AND group_id = ?");
        $stmt->execute([$member_id, $group_id]);
        $savings_balance = $stmt->fetch()['total_savings'];

        if ($savings_balance <= 0) {
            $skipped_no_savings++;
            echo "  SKIP loan #{$loan_id} ({$loan['member_name']}): No savings balance (K 0.00)\n";
            continue;
        }

        $deduct_amount = min($savings_balance, $outstanding);

        if ($deduct_amount <= 0) {
            $skipped_no_savings++;
            continue;
        }

        try {
            $pdo->beginTransaction();

            $interest_paid = $deduct_amount * 0.3;
            $principal_paid = $deduct_amount - $interest_paid;
            $new_balance = $loan['balance'] - $principal_paid;
            if ($new_balance < 0) $new_balance = 0;
            $is_late = 1;

            $stmt = $pdo->prepare("INSERT INTO loan_repayments (loan_id, amount, principal_paid, interest_paid, penalty_amount, payment_date, due_date, payment_method, is_late, recorded_by) VALUES (?, ?, ?, ?, 0, ?, ?, 'auto_deduction', ?, NULL)");
            $stmt->execute([$loan_id, $deduct_amount, $principal_paid, $interest_paid, $today, $due_date, $is_late]);

            $status = $new_balance <= 0 ? 'repaid' : 'disbursed';
            $stmt = $pdo->prepare("UPDATE loans SET balance = ?, amount_paid = amount_paid + ?, status = ? WHERE id = ?");
            $stmt->execute([$new_balance, $deduct_amount, $status, $loan_id]);

            $stmt = $pdo->prepare("INSERT INTO transactions (group_id, transaction_type, amount, member_id, loan_id, description, created_by) VALUES (?, 'loan_repayment', ?, ?, ?, ?, NULL)");
            $stmt->execute([$group_id, $deduct_amount, $member_id, $loan_id, "Auto-deduction: loan repayment of K{$deduct_amount}"]);

            $stmt = $pdo->prepare("INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, transaction_ref, recorded_by, is_self_service) VALUES (?, ?, ?, ?, 'auto_deduction', ?, NULL, 0)");
            $txref = 'auto-repay-' . $loan_id . '-' . time();
            $stmt->execute([$member_id, $group_id, -$deduct_amount, $today, $txref]);

            $stmt = $pdo->prepare("INSERT INTO transactions (group_id, transaction_type, amount, member_id, loan_id, description, reference, created_by) VALUES (?, 'withdrawal', ?, ?, ?, ?, ?, NULL)");
            $stmt->execute([$group_id, $deduct_amount, $member_id, $loan_id, "Auto-deduction: savings withdrawal for loan repayment K{$deduct_amount}", $txref]);

            $stmt = $pdo->prepare("UPDATE savings_groups SET total_savings = total_savings - ? WHERE id = ?");
            $stmt->execute([$deduct_amount, $group_id]);

            $pdo->commit();

            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            $notifyMsg = "An auto-deduction of K " . number_format($deduct_amount, 2) . " was applied from your savings to loan #{$loan_id}.";
            if ($new_balance <= 0) {
                $notifyMsg .= " Your loan is now fully repaid!";
            }
            $stmt->execute([$member_id, 'Auto Loan Deduction', $notifyMsg]);

            $processed++;
            echo "  DEDUCTED loan #{$loan_id} ({$loan['member_name']}): K {$deduct_amount} from savings → loan (balance now K {$new_balance})\n";
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors++;
            echo "  ERROR loan #{$loan_id} ({$loan['member_name']}): " . $e->getMessage() . "\n";
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Auto deduction finished.\n";
    echo "  Processed: {$processed}\n";
    echo "  Skipped (not due yet): {$skipped_not_due}\n";
    echo "  Skipped (no savings): {$skipped_no_savings}\n";
    echo "  Errors: {$errors}\n";
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Runner failed: " . $e->getMessage() . "\n";
}
