<?php
// File: add_member.php
// Add new member form page
session_start();
require_once 'config/database.php';
require_once 'config/validation.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'group_admin'])) {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$group_id = $_SESSION['group_id'];
$message = '';
$error = '';
$formData = [];

// Handle member addition
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($role !== 'admin' && $role !== 'group_admin') {
        $error = "Only admins and group admins can add new members.";
    } else {
        $formData = Validation::sanitizeArray($_POST);
        $username = $formData['username'] ?? '';
        $full_name = $formData['full_name'] ?? '';
        $email = $formData['email'] ?? '';
        $phone = $formData['phone'] ?? '';
        $password = $_POST['password'] ?? '';

        $valid = true;
        if (!Validation::required($_POST, ['full_name', 'username', 'password'])) $valid = false;
        if (!Validation::fullName($full_name)) $valid = false;
        if (!Validation::username($username)) $valid = false;
        if (!Validation::uniqueUsername($pdo, $username)) $valid = false;
        if (!Validation::email($email)) $valid = false;
        if ($email !== '' && !Validation::uniqueEmail($pdo, $email)) $valid = false;
        if (!Validation::phone($phone)) $valid = false;
        if (!Validation::password($password, 8)) $valid = false;

        // For admin, get selected group; for group_admin, use their group only
        $member_group_id = $group_id;
        if ($role == 'admin' && isset($_POST['group_id']) && !empty($_POST['group_id'])) {
            $member_group_id = intval($formData['group_id']);
        }

        if ($valid) {
            try {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, group_id) VALUES (?, ?, ?, ?, ?, 'member', ?)");
                $stmt->execute([$username, $hashed, $full_name, $email, $phone, $member_group_id]);
                $message = "Member added successfully!";
                $_POST = [];
                $formData = [];
            } catch (PDOException $e) {
                $error = "Error adding member: " . $e->getMessage();
            }
        } else {
            $error = Validation::firstError();
        }
    }
}

// Get groups for admin (super admin sees all, group admin sees only their group)
$groups = [];
if ($role == 'admin') {
    $stmt = $pdo->query("SELECT * FROM savings_groups ORDER BY group_name");
    $groups = $stmt->fetchAll();
} else if ($role == 'group_admin') {
    // Group admin only sees their own group
    $stmt = $pdo->prepare("SELECT * FROM savings_groups WHERE id = ? ORDER BY group_name");
    $stmt->execute([$group_id]);
    $groups = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Member - RSGMS</title>
    <link rel="stylesheet" href="assets/css/icons.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <style>
        .form-group {
            margin-bottom: 20px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
        }
        
        .back-link:hover {
            color: #667eea;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    <?php include 'config/shared_navbar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <h2>Add New Member</h2>
        </div>
        
        <div class="form-container">
            <div class="form-title"><i class="fa-solid fa-user-plus section-icon"></i> Register New Member</div>
            
            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" required value="<?php echo htmlspecialchars($formData['full_name'] ?? $_POST['full_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" required value="<?php echo htmlspecialchars($formData['username'] ?? $_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($formData['email'] ?? $_POST['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($formData['phone'] ?? $_POST['phone'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" required>
                </div>
                
                <?php if ($role == 'admin'): ?>
                <div class="form-group">
                    <label>Assign to Group *</label>
                    <select name="group_id" required>
                        <option value="">Select Group</option>
                        <?php foreach ($groups as $group): ?>
                        <option value="<?php echo $group['id']; ?>" <?php echo (($formData['group_id'] ?? $_POST['group_id'] ?? '') == $group['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($group['group_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <button type="submit" class="btn-submit">Register Member</button>
            </form>
            
            <a href="members.php" class="back-link">← Back to Members</a>
        </div>
    </div>
</body>
</html>
