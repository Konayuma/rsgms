<?php
require_once 'includes/init.php';
$user = requireRole(['group_admin']);
$user_id = $user['id'];
$group_id = $user['group_id'];

if (!$group_id) {
    setFlash('error', 'You are not assigned to any group.');
    header('Location: dashboard.php');
    exit();
}

// Get current group
$stmt = $pdo->prepare("SELECT * FROM savings_groups WHERE id = ?");
$stmt->execute([$group_id]);
$group = $stmt->fetch();

if (!$group) {
    setFlash('error', 'Group not found.');
    header('Location: dashboard.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_settings') {
        $name = trim($_POST['group_name'] ?? '');
        $interest = (float) ($_POST['interest_rate'] ?? 10);
        $penalty = (float) ($_POST['penalty_rate'] ?? 5);
        $meeting = trim($_POST['meeting_day'] ?? '');
        $contribution = (float) ($_POST['contribution_amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if (!$name) {
            setFlash('error', 'Group name is required.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE savings_groups SET group_name = ?, description = ?, interest_rate = ?, penalty_rate = ?, meeting_day = ?, contribution_amount = ? WHERE id = ?");
                $stmt->execute([$name, $description, $interest, $penalty, $meeting, $contribution, $group_id]);
                setFlash('success', 'Group settings updated successfully!');
                // Refresh
                $stmt = $pdo->prepare("SELECT * FROM savings_groups WHERE id = ?");
                $stmt->execute([$group_id]);
                $group = $stmt->fetch();
            } catch (PDOException $e) {
                setFlash('error', 'Error updating group: ' . $e->getMessage());
            }
        }
    } elseif ($_POST['action'] === 'reset_code') {
        do {
            $new_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $check = $pdo->prepare("SELECT COUNT(*) FROM savings_groups WHERE invitation_code = ? AND id != ?");
            $check->execute([$new_code, $group_id]);
        } while ($check->fetchColumn() > 0);
        try {
            $stmt = $pdo->prepare("UPDATE savings_groups SET invitation_code = ? WHERE id = ?");
            $stmt->execute([$new_code, $group_id]);
            setFlash('success', 'Invitation code reset successfully!');
            $stmt = $pdo->prepare("SELECT * FROM savings_groups WHERE id = ?");
            $stmt->execute([$group_id]);
            $group = $stmt->fetch();
        } catch (PDOException $e) {
            setFlash('error', 'Error resetting code: ' . $e->getMessage());
        }
    }
}

// Group stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE group_id = ? AND role = 'member'");
$stmt->execute([$group_id]);
$member_count = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE group_id = ? AND role = 'member' AND status = 'pending'");
$stmt->execute([$group_id]);
$pending_count = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM savings_contributions WHERE group_id = ?");
$stmt->execute([$group_id]);
$total_savings = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(principal_amount),0) FROM loans WHERE group_id = ? AND status != 'repaid'");
$stmt->execute([$group_id]);
$active_loans = (float) $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Settings - RSGMS</title>
    <link rel="stylesheet" href="assets/css/icons.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/toast.css">
    <style>
        .page-head { margin-bottom: 28px; }
        .page-head h1 { font-size: clamp(1.4rem,2.2vw,1.8rem); font-weight: 700; color: #241f1a; letter-spacing: -0.02em; }
        .page-head p { color: #8c8580; font-size: 0.92rem; margin-top: 4px; }

        .settings-card { background: #fff; border: 1px solid #e6e2dc; border-radius: 14px; margin-bottom: 20px; overflow: hidden; }
        .settings-header { padding: 16px 20px; border-bottom: 1px solid #f0ede8; }
        .settings-header h2 { font-size: 1rem; font-weight: 600; color: #241f1a; }
        .settings-body { padding: 20px; }

        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px,1fr)); gap: 14px; margin-bottom: 24px; }
        .stat-block { text-align: center; padding: 14px; background: #faf9f7; border-radius: 10px; }
        .stat-block .num { font-size: 1.35rem; font-weight: 700; color: #241f1a; }
        .stat-block .label { font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.06em; color: #8c8580; margin-top: 2px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-grid .full { grid-column: 1 / -1; }
        .form-grid label { font-size: 0.78rem; font-weight: 600; color: #4a4440; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; display: block; }
        .form-grid input, .form-grid textarea { width: 100%; padding: 9px 12px; border: 1px solid #e6e2dc; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.9rem; color: #241f1a; background: #faf9f7; transition: border-color 0.12s ease; }
        .form-grid input:focus, .form-grid textarea:focus { outline: none; border-color: #241f1a; background: #fff; }
        .form-grid textarea { resize: vertical; min-height: 70px; }

        .code-display { display: flex; align-items: center; gap: 14px; padding: 14px 18px; background: #faf9f7; border: 1px solid #e6e2dc; border-radius: 10px; margin-bottom: 18px; }
        .code-display .code { font-family: monospace; font-size: 1.4rem; font-weight: 700; letter-spacing: 0.1em; color: #241f1a; }
        .code-display .hint { font-size: 0.82rem; color: #8c8580; margin-left: auto; }

        .action-row { display: flex; align-items: center; gap: 10px; margin-top: 18px; padding-top: 16px; border-top: 1px solid #f0ede8; }
        .btn-outline { }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .code-display { flex-direction: column; text-align: center; }
            .code-display .hint { margin-left: 0; }
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    <?php include 'config/shared_navbar.php'; ?>
    <div class="main-content">
        <div id="flash-data" data-flash='<?php echo json_encode(flashMessages()); ?>' style="display:none"></div>

        <div class="page-head">
            <h1>Group Settings</h1>
            <p><?php echo htmlspecialchars($group['group_name']); ?></p>
        </div>

        <div class="stats-row">
            <div class="stat-block"><div class="num"><?php echo $member_count; ?></div><div class="label">Members</div></div>
            <div class="stat-block"><div class="num"><?php echo $pending_count; ?></div><div class="label">Pending</div></div>
            <div class="stat-block"><div class="num">K <?php echo number_format($total_savings, 0); ?></div><div class="label">Savings</div></div>
            <div class="stat-block"><div class="num">K <?php echo number_format($active_loans, 0); ?></div><div class="label">Active Loans</div></div>
        </div>

        <div class="settings-card">
            <div class="settings-header"><h2>Invitation Code</h2></div>
            <div class="settings-body">
                <div class="code-display">
                    <span class="code"><?php echo htmlspecialchars($group['invitation_code'] ?? '—'); ?></span>
                    <button class="btn btn-sm" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($group['invitation_code'] ?? ''); ?>');this.innerHTML='<i class=\'fa-regular fa-check\'></i> Copied';setTimeout(function(){this.innerHTML='<i class=\'fa-regular fa-copy\'></i> Copy'}.bind(this),1800)"><i class="fa-regular fa-copy"></i> Copy</button>
                    <span class="hint">Share this 6-digit code with new members</span>
                </div>
                <form method="POST" onsubmit="return confirm('Reset the invitation code? Members with the old code will need the new one.')">
                    <input type="hidden" name="action" value="reset_code">
                    <?php echo csrfField(); ?>
                    <button type="submit" class="btn btn-outline"><i class="fa-regular fa-rotate"></i> Generate New Code</button>
                </form>
            </div>
        </div>

        <div class="settings-card">
            <div class="settings-header"><h2>Group Details</h2></div>
            <div class="settings-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_settings">
                    <?php echo csrfField(); ?>
                    <div class="form-grid">
                        <div class="full">
                            <label>Group Name</label>
                            <input type="text" name="group_name" value="<?php echo htmlspecialchars($group['group_name']); ?>" required>
                        </div>
                        <div class="full">
                            <label>Description</label>
                            <textarea name="description"><?php echo htmlspecialchars($group['description'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label>Interest Rate (%)</label>
                            <input type="number" name="interest_rate" step="0.01" value="<?php echo htmlspecialchars($group['interest_rate']); ?>">
                        </div>
                        <div>
                            <label>Penalty Rate (%)</label>
                            <input type="number" name="penalty_rate" step="0.01" value="<?php echo htmlspecialchars($group['penalty_rate']); ?>">
                        </div>
                        <div>
                            <label>Meeting Day</label>
                            <input type="text" name="meeting_day" placeholder="e.g. Monday" value="<?php echo htmlspecialchars($group['meeting_day'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Contribution (K)</label>
                            <input type="number" name="contribution_amount" step="0.01" value="<?php echo htmlspecialchars($group['contribution_amount']); ?>">
                        </div>
                    </div>
                    <div class="action-row">
                        <button type="submit" class="btn btn-primary"><i class="fa-regular fa-floppy-disk"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<script src="assets/js/toast.js"></script>
</body>
</html>
