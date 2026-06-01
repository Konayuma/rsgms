<?php
// Script: run_recurring_savings.php
// Run this script via cron or CLI to process recurring savings entries.
require_once __DIR__ . '/config/database.php';

echo "Starting recurring savings runner...\n";

try {
    $today = date('Y-m-d');

    $stmt = $pdo->prepare("SELECT * FROM recurring_savings WHERE active = 1 AND next_run_date <= ?");
    $stmt->execute([$today]);
    $jobs = $stmt->fetchAll();

    foreach ($jobs as $job) {
        $pdo->beginTransaction();
        try {
            $member_id = $job['member_id'];
            $group_id = $job['group_id'];
            $amount = $job['amount'];
            $contribution_date = $job['next_run_date'];

            // Insert savings contribution (recorded_by NULL for system)
            $insert = $pdo->prepare("INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, transaction_ref, recorded_by, is_self_service) VALUES (?, ?, ?, ?, ?, ?, NULL, 1)");
            $txref = 'recurring-' . $job['id'] . '-' . $contribution_date;
            $insert->execute([$member_id, $group_id, $amount, $contribution_date, 'recurring', $txref]);

            // Log transaction
            $log = $pdo->prepare("INSERT INTO transactions (group_id, transaction_type, amount, member_id, description, reference, created_by) VALUES (?, 'savings', ?, ?, ?, ?, NULL)");
            $log->execute([$group_id, $amount, $member_id, "Recurring savings auto-recorded", $txref]);

            // Update group total savings
            $upd = $pdo->prepare("UPDATE savings_groups SET total_savings = total_savings + ? WHERE id = ?");
            $upd->execute([$amount, $group_id]);

            // Compute next run date
            $next = $contribution_date;
            if ($job['frequency'] === 'weekly') {
                $next = date('Y-m-d', strtotime($contribution_date . ' +7 days'));
            } elseif ($job['frequency'] === 'monthly') {
                $next = date('Y-m-d', strtotime($contribution_date . ' +1 month'));
            } elseif ($job['frequency'] === 'custom' && !empty($job['custom_interval'])) {
                $next = date('Y-m-d', strtotime($contribution_date . ' +' . intval($job['custom_interval']) . ' days'));
            } else {
                // default to monthly
                $next = date('Y-m-d', strtotime($contribution_date . ' +1 month'));
            }

            $active = 1;
            if (!empty($job['end_date']) && $next > $job['end_date']) {
                $active = 0;
            }

            $updateRec = $pdo->prepare("UPDATE recurring_savings SET next_run_date = ?, active = ? WHERE id = ?");
            $updateRec->execute([$next, $active, $job['id']]);

            $pdo->commit();
            echo "Processed recurring_savings id={$job['id']} for member_id={$member_id} on {$contribution_date}\n";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "Error processing recurring_savings id={$job['id']}: " . $e->getMessage() . "\n";
        }
    }

    echo "Recurring runner finished. Processed " . count($jobs) . " job(s).\n";
} catch (Exception $e) {
    echo "Runner failed: " . $e->getMessage() . "\n";
}

?>
