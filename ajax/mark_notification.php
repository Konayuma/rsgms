<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($action === 'mark_read' && !empty($_POST['notification_id'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
    $stmt->execute([(int)$_POST['notification_id'], $_SESSION['user_id']]);
    echo json_encode(['success' => true]);
    exit();
}

if ($action === 'mark_all_read') {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['success' => true]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
