<?php
/**
 * Shared savings helper functions
 */

function getSavingsCollectionWindow(PDO $pdo, int $group_id): array {
    $stmt = $pdo->prepare("SELECT cycle_start_date, cycle_end_date FROM savings_groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $row = $stmt->fetch();
    if (!$row) return ['start' => null, 'end' => null];
    return ['start' => $row['cycle_start_date'] ? (string)$row['cycle_start_date'] : null, 'end' => $row['cycle_end_date'] ? (string)$row['cycle_end_date'] : null];
}

function blockSavingsOutsideWindow(PDO $pdo, int $user_id, int $member_id, int $group_id, string $attempt_date): array {
    $window = getSavingsCollectionWindow($pdo, $group_id);
    $start = $window['start']; $end = $window['end'];
    if ($start === null || $end === null) return ['allowed' => true];
    if ($attempt_date < $start || $attempt_date > $end) {
        $stmt = $pdo->prepare("INSERT INTO transactions (group_id, transaction_type, amount, member_id, description, reference, created_by) VALUES (?, 'savings', 0, ?, ?, ?, ?)");
        $stmt->execute([$group_id, $member_id, "Savings blocked — window {$start} to {$end}, attempted {$attempt_date}.", 'blocked-' . $group_id . '-' . $attempt_date, $user_id]);
        return ['allowed' => false, 'message' => "Savings collection is closed for this date. Valid period: {$start} to {$end}."];
    }
    return ['allowed' => true];
}
