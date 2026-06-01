<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/validation.php';
require_once __DIR__ . '/../config/db_helpers.php';

// ── Flash messages ──────────────────────────────────────────
function setFlash(string $type, string $message, array $opts = []): void {
    $_SESSION['_flash'][] = array_merge(['type' => $type, 'message' => $message], $opts);
}

function flashMessages(): array {
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

function hasFlashes(): bool {
    return !empty($_SESSION['_flash']);
}

// ── Auth helpers ────────────────────────────────────────────
function requireLogin(): array {
    if (!isset($_SESSION['user_id'])) {
        setFlash('error', 'Please log in to continue.');
        header('Location: login.php');
        exit();
    }
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['role'] ?? '',
        'group_id'  => $_SESSION['group_id'] ?? 0,
    ];
}

function requireRole(array $roles): array {
    $user = requireLogin();
    if (!in_array($user['role'], $roles)) {
        setFlash('error', 'You do not have permission to access this page.');
        header('Location: dashboard.php');
        exit();
    }
    return $user;
}

function hasRole(string $role): bool {
    return ($_SESSION['role'] ?? '') === $role;
}

function hasAnyRole(array $roles): bool {
    return in_array($_SESSION['role'] ?? '', $roles);
}

// ── CSRF protection ─────────────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="_csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf(): bool {
    $token = $_POST['_csrf_token'] ?? '';
    return !empty($token) && hash_equals($_SESSION['_csrf_token'] ?? '', $token);
}

function requireCsrf(): void {
    if (!verifyCsrf()) {
        setFlash('error', 'Security token expired. Please try again.');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// ── Notification helpers ────────────────────────────────────
function notifyUser(int $userId, string $title, string $message): void {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $title, $message]);
}

function notifyGroup(int $groupId, string $title, string $message): void {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE group_id = ?");
    $stmt->execute([$groupId]);
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    foreach ($users as $userId) {
        $stmt->execute([$userId, $title, $message]);
    }
}

function notifyAllAdmins(string $title, string $message): void {
    global $pdo;
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
    $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    foreach ($admins as $adminId) {
        $stmt->execute([$adminId, $title, $message]);
    }
}
