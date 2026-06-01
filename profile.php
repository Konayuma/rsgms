<?php
require_once 'includes/init.php';

$user = requireLogin();
$user_id = $user['id'];

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('error', 'User not found.');
    header('Location: login.php');
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $phone, $user_id]);
            
            $_SESSION['full_name'] = $full_name;
            setFlash('success', 'Profile updated successfully!');
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            setFlash('error', "Error updating profile: " . $e->getMessage());
        }
    } elseif ($_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (!password_verify($current_password, $user['password'])) {
            setFlash('error', "Current password is incorrect.");
        } elseif (strlen($new_password) < 6) {
            setFlash('error', "New password must be at least 6 characters long.");
        } elseif ($new_password !== $confirm_password) {
            setFlash('error', "New passwords do not match.");
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                setFlash('success', 'Password changed successfully!');
            } catch (PDOException $e) {
                setFlash('error', "Error changing password: " . $e->getMessage());
            }
        }
    }
}

// Handle notification preferences update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_notifications') {
    $sms_enabled = isset($_POST['sms_enabled']) ? 1 : 0;
    $in_app_enabled = isset($_POST['in_app_enabled']) ? 1 : 0;
    $frequency = in_array($_POST['frequency'] ?? '', ['immediate','daily','weekly']) ? $_POST['frequency'] : 'immediate';

    try {
        $stmt = $pdo->prepare("SELECT id FROM notification_preferences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $pref = $stmt->fetch();
        if ($pref) {
            $stmt = $pdo->prepare("UPDATE notification_preferences SET sms_enabled = ?, in_app_enabled = ?, frequency = ? WHERE user_id = ?");
            $stmt->execute([$sms_enabled, $in_app_enabled, $frequency, $user_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO notification_preferences (user_id, sms_enabled, in_app_enabled, frequency) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $sms_enabled, $in_app_enabled, $frequency]);
        }
        setFlash('success', 'Notification preferences updated.');
    } catch (PDOException $e) {
        setFlash('error', "Error saving preferences: " . $e->getMessage());
    }
}

// Get user statistics
$stats = [];

// Savings stats
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_savings, COUNT(*) as contribution_count FROM savings_contributions WHERE member_id = ?");
$stmt->execute([$user_id]);
$savings_stats = $stmt->fetch();
$stats['total_savings'] = $savings_stats['total_savings'];
$stats['contribution_count'] = $savings_stats['contribution_count'];

// Loan stats
$stmt = $pdo->prepare("SELECT COALESCE(SUM(principal_amount), 0) as total_loans, COALESCE(SUM(amount_paid), 0) as total_paid, COUNT(*) as loan_count FROM loans WHERE member_id = ?");
$stmt->execute([$user_id]);
$loan_stats = $stmt->fetch();
$stats['total_loans'] = $loan_stats['total_loans'];
$stats['total_paid'] = $loan_stats['total_paid'];
$stats['loan_count'] = $loan_stats['loan_count'];

// Active loans
$stmt = $pdo->prepare("SELECT COALESCE(SUM(balance), 0) as outstanding_balance FROM loans WHERE member_id = ? AND status != 'repaid'");
$stmt->execute([$user_id]);
$stats['outstanding_balance'] = $stmt->fetch()['outstanding_balance'];

// Load notification preferences
$stmt = $pdo->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
$stmt->execute([$user_id]);
$prefs = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - RSGMS</title>
    <link rel="stylesheet" href="assets/css/icons.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <style>
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: clamp(60px,15vw,100px);
            height: clamp(60px,15vw,100px);
            background: var(--ink);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(1.5rem,6vw,3rem);
            color: var(--cream);
            margin: 0 auto 15px;
        }
        
        .btn-submit {
            background: var(--clay);
            color: var(--cream);
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 1rem;
            font-family:'Sora',sans-serif;
            font-weight:500;
            width:100%;
            min-height:44px;
        }
        
        .btn-submit:hover {
            background: var(--clay-dark);
        }
        
        .tab-buttons {
            display: flex;
            margin-bottom: 20px;
            background: var(--cream-light);
            border-radius: 10px;
            padding: 5px;
            overflow-x:auto;
            gap:4px;
            -webkit-overflow-scrolling:touch;
        }
        
        .tab-btn {
            flex: 1;
            padding: 10px;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            white-space:nowrap;
            font-family:'Sora',sans-serif;
            font-weight:500;
        }
        
        .tab-btn.active {
            background: var(--white-soft);
            box-shadow: 0 1px 3px rgba(15,23,42,0.08);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }

        @media(max-width:480px){
            .tab-buttons{flex-wrap:nowrap;padding:4px;gap:3px}
            .tab-btn{font-size:0.78rem;padding:8px 6px;flex:0 1 auto}
            .profile-header{margin-bottom:20px}
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    <?php include 'config/shared_navbar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h2><i class="fa-solid fa-user section-icon"></i> My Profile</h2>
        </div>
        
        <div id="flash-data" data-flash='<?php echo json_encode(flashMessages()); ?>' style="display:none"></div>
        
        <!-- Profile Header -->
        <div class="section">
            <div class="profile-header">
                <div class="profile-avatar"><i class="fa-solid fa-user"></i></div>
                <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                <p><?php echo htmlspecialchars($user['username']); ?> • <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></p>
                <p>Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">K <?php echo number_format($stats['total_savings'], 2); ?></div>
                <div class="stat-label">Total Savings</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['contribution_count']; ?></div>
                <div class="stat-label">Contributions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">K <?php echo number_format($stats['total_loans'], 2); ?></div>
                <div class="stat-label">Total Loans</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">K <?php echo number_format($stats['outstanding_balance'], 2); ?></div>
                <div class="stat-label">Outstanding Balance</div>
            </div>
        </div>
        
        <!-- Profile Tabs -->
        <div class="section">
            <div class="tab-buttons">
                <button class="tab-btn active" onclick="showTab('profile')">Profile Information</button>
                <button class="tab-btn" onclick="showTab('password')">Change Password</button>
                <button class="tab-btn" onclick="showTab('notifications')">Notifications</button>
            </div>
            
            <!-- Profile Information Tab -->
            <div id="profile-tab" class="tab-content active">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Username (cannot be changed)</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" readonly style="background: #f8f9fa;">
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <input type="text" value="<?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>" readonly style="background: #f8f9fa;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn-submit">Update Profile</button>
                </form>
            </div>
            
            <!-- Change Password Tab -->
            <div id="password-tab" class="tab-content">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label>Current Password *</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>New Password *</label>
                        <input type="password" name="new_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password *</label>
                        <input type="password" name="confirm_password" required minlength="6">
                    </div>
                    <button type="submit" class="btn-submit">Change Password</button>
                </form>
            </div>

            <!-- Notifications Tab -->
            <div id="notifications-tab" class="tab-content">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_notifications">
                    <div class="form-group">
                        <label><input type="checkbox" name="sms_enabled" <?php echo (!empty($prefs) && $prefs['sms_enabled']) ? 'checked' : ''; ?>> SMS Notifications</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="in_app_enabled" <?php echo (!empty($prefs) && $prefs['in_app_enabled']) ? 'checked' : ''; ?>> In-app Notifications</label>
                    </div>
                    <div class="form-group">
                        <label>Delivery Frequency</label>
                        <select name="frequency">
                            <option value="immediate" <?php echo (empty($prefs) || $prefs['frequency']=='immediate') ? 'selected' : ''; ?>>Immediate</option>
                            <option value="daily" <?php echo (!empty($prefs) && $prefs['frequency']=='daily') ? 'selected' : ''; ?>>Daily digest</option>
                            <option value="weekly" <?php echo (!empty($prefs) && $prefs['frequency']=='weekly') ? 'selected' : ''; ?>>Weekly digest</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit">Save Notification Preferences</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Remove active class from all buttons
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
    </script>
    <link rel="stylesheet" href="assets/css/toast.css">
    <script src="assets/js/toast.js"></script>
</body>
</html>
