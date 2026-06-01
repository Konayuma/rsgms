<?php
require_once 'includes/init.php';
requireLogin();

$member_id = intval($_GET['member_id'] ?? 0);
$group_id = intval($_GET['group_id'] ?? 0);

if (!$member_id || !$group_id) {
    echo json_encode(['balance' => 0]);
    exit;
}

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_savings FROM savings_contributions WHERE member_id = ? AND group_id = ?");
$stmt->execute([$member_id, $group_id]);
$balance = $stmt->fetch()['total_savings'];

echo json_encode(['balance' => floatval($balance)]);
