<?php
// File: get_member_risk_profile.php
// AJAX API endpoint returning dynamic capacity risk profiling data in JSON format

header('Content-Type: application/json; charset=utf-8');
session_start();

// 1. Authorize: Session must be active
if (!isset($_SESSION['user_id'])) {
    http_response_code(410); // Unauthorized
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required. Please log in.'
    ]);
    exit();
}

require_once 'config/database.php';
require_once 'config/risk_engine.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$user_group_id = intval($_SESSION['group_id'] ?? 0);

// 2. Parse target member_id
$member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;
if ($member_id <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'success' => false,
        'error' => 'Invalid or missing Member ID parameter.'
    ]);
    exit();
}

// 3. Strict RBAC checks
// - Members can only query their own risk profile details.
// - Group admins can only query members belonging to their same savings group.
// - Admins can query any member.
if ($role === 'member' && $member_id !== $user_id) {
    http_response_code(403); // Forbidden
    echo json_encode([
        'success' => false,
        'error' => 'Access denied: Members can only inspect their own risk profiles.'
    ]);
    exit();
}

if ($role === 'group_admin' || $role === 'loan_officer') {
    // Verify that the requested member belongs to the same group
    $stmt = $pdo->prepare("SELECT group_id FROM users WHERE id = ?");
    $stmt->execute([$member_id]);
    $target_member = $stmt->fetch();
    
    if (!$target_member || intval($target_member['group_id']) !== $user_group_id) {
        $role_label = $role === 'loan_officer' ? 'Loan Officers' : 'Group Administrators';
        http_response_code(403); // Forbidden
        echo json_encode([
            'success' => false,
            'error' => "Access denied: {$role_label} can only profile members of their own group."
        ]);
        exit();
    }
}

// 4. Generate Risk Profile
$profile = getMemberRiskProfile($pdo, $member_id);

if (!$profile) {
    http_response_code(404); // Not Found
    echo json_encode([
        'success' => false,
        'error' => 'Member profile not found or user is not a savings group member.'
    ]);
    exit();
}

// Return profile payload
echo json_encode([
    'success' => true,
    'profile' => $profile
]);
exit();
