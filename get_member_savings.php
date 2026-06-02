<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit();
}

require_once 'config/database.php';

$member_id = intval($_GET['member_id'] ?? 0);
$group_id = intval($_GET['group_id'] ?? 0);

if (!$member_id || !$group_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing parameters.']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_savings FROM savings_contributions WHERE member_id = ? AND group_id = ?");
    $stmt->execute([$member_id, $group_id]);
    $balance = floatval($stmt->fetch()['total_savings']);
    echo json_encode(['success' => true, 'balance' => $balance]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
