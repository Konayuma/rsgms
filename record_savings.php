<?php
// File: record_savings.php
// Record savings contribution form page
session_start();
require_once 'config/database.php';

require_once 'config/savings_helpers.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'member') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$group_id = intval($_SESSION['group_id'] ?? 0);
$message = '';
$error = '';

// Members see only themselves
$stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$members = $stmt->fetchAll();
$members_count = count($members);

// Handle savings recording
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $_POST['member_id'] ?? $user_id;
    $amount = floatval($_POST['amount']);
    $contribution_date = $_POST['contribution_date'];
    $payment_method = $_POST['payment_method'];
    $transaction_ref = trim($_POST['transaction_ref'] ?? '');
    $is_recurring = isset($_POST['is_recurring']) && $_POST['is_recurring'] == '1';
    $recurrence_frequency = $_POST['recurrence_frequency'] ?? 'monthly';
    $recurrence_end_date = $_POST['recurrence_end_date'] ?? null;
    
    $target_group_id = $group_id;
    
    try {
        $pdo->beginTransaction();

        // Automated window enforcement (backend autonomous check)
        $windowCheck = blockSavingsOutsideWindow($pdo, (int)$user_id, (int)$member_id, (int)$target_group_id, (string)$contribution_date);
        if (empty($windowCheck['allowed'])) {
            // Block and return explanatory timeline warning
            $pdo->rollBack();
            $error = $windowCheck['message'] ?? 'Savings collection outside permitted dates.';
        } else {
            // Insert savings contribution
            $is_self = ($role == 'member') ? 1 : 0;
            $stmt = $pdo->prepare("INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, transaction_ref, recorded_by, is_self_service) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$member_id, $target_group_id, $amount, $contribution_date, $payment_method, $transaction_ref, $user_id, $is_self]);

            // Log transaction
            $stmt = $pdo->prepare("INSERT INTO transactions (group_id, transaction_type, amount, member_id, description, reference, created_by) VALUES (?, 'savings', ?, ?, ?, ?, ?)");
            $stmt->execute([$target_group_id, $amount, $member_id, "Savings contribution of K$amount", $transaction_ref, $user_id]);

            // Update group total savings
            $stmt = $pdo->prepare("UPDATE savings_groups SET total_savings = total_savings + ? WHERE id = ?");
            $stmt->execute([$amount, $target_group_id]);
            
            // If recurring requested, create recurring_savings entry
            if ($is_recurring) {
                $next_run = $contribution_date;
                $stmt = $pdo->prepare("INSERT INTO recurring_savings (member_id, group_id, amount, frequency, next_run_date, end_date, active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$member_id, $target_group_id, $amount, $recurrence_frequency, $next_run, $recurrence_end_date]);
            }
            
            $pdo->commit();
            $message = "Savings recorded successfully!";
            
            // Clear form
            $_POST = [];
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error recording savings: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Savings - RSGMS</title>
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
            <h2>Record Savings Contribution</h2>
        </div>
        
        <div class="form-container">
            <div class="form-title"><i class="fa-solid fa-sack-dollar section-icon"></i> Record Member Savings</div>
            
            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($members_count === 0): ?>
                <div class="error">No members found for your current group. Please add members first.</div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Select Member *</label>
                    <?php if ($role == 'member'): ?>
                        <input type="hidden" name="member_id" value="<?php echo $members[0]['id']; ?>">
                        <div style="padding:10px; background:#f8f9fa; border-radius:8px;"><?php echo htmlspecialchars($members[0]['full_name']); ?></div>
                    <?php else: ?>
                        <select name="member_id" required>
                            <option value="">-- Select Member --</option>
                            <?php if ($members_count === 0): ?>
                            <option value="">No members found</option>
                            <?php else: ?>
                            <?php foreach ($members as $member): ?>
                            <option value="<?php echo $member['id']; ?>" <?php echo (isset($_POST['member_id']) && $_POST['member_id'] == $member['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($member['full_name']); ?><?php echo $role == 'admin' ? ' (' . htmlspecialchars($member['group_name'] ?? 'No Group') . ')' : ''; ?>
                            </option>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    <?php endif; ?>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Amount (K) *</label>
                        <input type="number" name="amount" step="0.01" min="0" required value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Contribution Date *</label>
                        <input type="date" name="contribution_date" value="<?php echo htmlspecialchars($_POST['contribution_date'] ?? date('Y-m-d')); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Payment Method *</label>
                        <select name="payment_method" required>
                            <option value="">-- Select Method --</option>
                            <option value="cash" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'cash') ? 'selected' : ''; ?>>Cash</option>
                            <option value="mobile_money" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'mobile_money') ? 'selected' : ''; ?>>Mobile Money</option>
                            <option value="bank_transfer" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'bank_transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="cheque" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'cheque') ? 'selected' : ''; ?>>Cheque</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Transaction Reference</label>
                        <input type="text" name="transaction_ref" placeholder="Optional reference number" value="<?php echo htmlspecialchars($_POST['transaction_ref'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-top:10px;">
                    <label><input type="checkbox" name="is_recurring" value="1" <?php echo isset($_POST['is_recurring']) ? 'checked' : ''; ?>> Make this a recurring contribution</label>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Recurrence Frequency</label>
                        <select name="recurrence_frequency">
                            <option value="weekly" <?php echo (isset($_POST['recurrence_frequency']) && $_POST['recurrence_frequency']=='weekly') ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo (isset($_POST['recurrence_frequency']) && $_POST['recurrence_frequency']=='monthly') ? 'selected' : ''; ?>>Monthly</option>
                            <option value="custom" <?php echo (isset($_POST['recurrence_frequency']) && $_POST['recurrence_frequency']=='custom') ? 'selected' : ''; ?>>Custom (days)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Recurrence End Date (optional)</label>
                        <input type="date" name="recurrence_end_date" value="<?php echo htmlspecialchars($_POST['recurrence_end_date'] ?? ''); ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">Record Savings</button>
            </form>
            
            <a href="my_savings.php" class="back-link">← Back to My Savings</a>
        </div>
    </div>
</body>
</html>
