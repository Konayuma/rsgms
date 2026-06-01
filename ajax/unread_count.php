<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

header('Content-Type: application/json');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
$stmt->execute([$_SESSION['user_id']]);
$unread = (int) $stmt->fetchColumn();

echo json_encode(['unread' => $unread]);
