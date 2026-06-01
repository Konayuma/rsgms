<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

header('Content-Type: application/json');

$stmt = $pdo->prepare(
    "SELECT id, title, message, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 5"
);
$stmt->execute([$_SESSION['user_id']]);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

function truncate(string $text, int $limit = 82): string {
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $limit, '...');
    }
    return strlen($text) > $limit ? substr($text, 0, $limit) . '...' : $text;
}

foreach ($list as &$item) {
    $item['preview'] = truncate($item['message'] ?? '');
    $item['is_read'] = (bool) $item['is_read'];
}
unset($item);

echo json_encode(['notifications' => $list]);
