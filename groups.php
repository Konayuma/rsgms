<?php
require_once 'includes/init.php';
$admin = requireRole(['admin']);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'edit_group') {
        $gid = (int) ($_POST['group_id'] ?? 0);
        $name = trim($_POST['group_name'] ?? '');
        $interest = (float) ($_POST['interest_rate'] ?? 10);
        $penalty = (float) ($_POST['penalty_rate'] ?? 5);
        $meeting = trim($_POST['meeting_day'] ?? '');
        $contribution = (float) ($_POST['contribution_amount'] ?? 0);

        if (!$name) {
            setFlash('error', 'Group name is required.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE savings_groups SET group_name = ?, interest_rate = ?, penalty_rate = ?, meeting_day = ?, contribution_amount = ? WHERE id = ?");
                $stmt->execute([$name, $interest, $penalty, $meeting, $contribution, $gid]);
                setFlash('success', 'Group settings updated successfully!');
            } catch (PDOException $e) {
                setFlash('error', 'Error updating group: ' . $e->getMessage());
            }
        }
    } elseif ($_POST['action'] === 'reset_code') {
        $gid = (int) ($_POST['group_id'] ?? 0);
        do {
            $new_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $check = $pdo->prepare("SELECT COUNT(*) FROM savings_groups WHERE invitation_code = ? AND id != ?");
            $check->execute([$new_code, $gid]);
        } while ($check->fetchColumn() > 0);

        try {
            $stmt = $pdo->prepare("UPDATE savings_groups SET invitation_code = ? WHERE id = ?");
            $stmt->execute([$new_code, $gid]);
            setFlash('success', 'Invitation code reset successfully!');
        } catch (PDOException $e) {
            setFlash('error', 'Error resetting code: ' . $e->getMessage());
        }
    } elseif ($_POST['action'] === 'delete_group') {
        $gid = (int) ($_POST['group_id'] ?? 0);
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE users SET group_id = NULL, status = 'active' WHERE group_id = ?");
            $stmt->execute([$gid]);
            $stmt = $pdo->prepare("DELETE FROM savings_contributions WHERE group_id = ?");
            $stmt->execute([$gid]);
            $stmt = $pdo->prepare("DELETE FROM loan_repayments WHERE loan_id IN (SELECT id FROM loans WHERE group_id = ?)");
            $stmt->execute([$gid]);
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE group_id = ?");
            $stmt->execute([$gid]);
            $stmt = $pdo->prepare("DELETE FROM loans WHERE group_id = ?");
            $stmt->execute([$gid]);
            $stmt = $pdo->prepare("DELETE FROM meetings WHERE group_id = ?");
            $stmt->execute([$gid]);
            $stmt = $pdo->prepare("DELETE FROM savings_groups WHERE id = ?");
            $stmt->execute([$gid]);
            $pdo->commit();
            setFlash('success', 'Group deleted successfully.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('error', 'Error deleting group: ' . $e->getMessage());
        }
    }
}

// Fetch all groups with stats
$stmt = $pdo->query("
    SELECT sg.*,
           (SELECT COUNT(*) FROM users WHERE group_id = sg.id AND role = 'member') AS member_count,
           (SELECT COUNT(*) FROM users WHERE group_id = sg.id AND role = 'member' AND status = 'pending') AS pending_count,
           (SELECT COALESCE(SUM(amount),0) FROM savings_contributions WHERE group_id = sg.id) AS total_savings,
           (SELECT COALESCE(SUM(principal_amount),0) FROM loans WHERE group_id = sg.id AND status != 'repaid') AS active_loans,
           u.full_name AS admin_name
    FROM savings_groups sg
    LEFT JOIN users u ON u.id = sg.created_by AND u.role = 'group_admin'
    ORDER BY sg.group_name
");
$groups = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Management - RSGMS</title>
    <link rel="stylesheet" href="assets/css/icons.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/toast.css">
    <style>
        .page-head { margin-bottom: 28px; }
        .page-head h1 { font-size: clamp(1.4rem,2.2vw,1.8rem); font-weight: 700; color: #241f1a; letter-spacing: -0.02em; }
        .page-head p { color: #8c8580; font-size: 0.92rem; margin-top: 4px; }

        .groups-grid { display: flex; flex-direction: column; gap: 14px; }
        .group-card {
            background: #fff; border: 1px solid #e6e2dc; border-radius: 14px; overflow: hidden;
            transition: box-shadow 0.15s ease;
        }
        .group-card:hover { box-shadow: 0 4px 14px rgba(36,31,26,0.06); }
        .group-card-header {
            display: flex; align-items: center; justify-content: space-between; gap: 14px;
            padding: 16px 20px; cursor: pointer; user-select: none;
        }
        .group-card-header h3 { font-size: 1.05rem; font-weight: 600; color: #241f1a; }
        .group-meta { display: flex; align-items: center; gap: 16px; font-size: 0.82rem; color: #8c8580; }
        .group-meta span { display: inline-flex; align-items: center; gap: 4px; }
        .group-meta .badge { display: inline-flex; padding: 2px 8px; border-radius: 999px; font-size: 0.72rem; font-weight: 600; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-code { font-family: monospace; letter-spacing: 0.08em; background: #f0ede8; padding: 3px 8px; border-radius: 6px; color: #241f1a; font-size: 0.88rem; font-weight: 700; }

        .group-card-body { border-top: 1px solid #f0ede8; padding: 18px 20px; display: none; }
        .group-card-body.is-open { display: block; }

        .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr)); gap: 14px; margin-bottom: 20px; }
        .detail-item dt { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: #8c8580; font-weight: 600; margin-bottom: 2px; }
        .detail-item dd { font-size: 0.95rem; color: #241f1a; font-weight: 600; }
        .detail-item dd.mono { font-family: monospace; letter-spacing: 0.06em; }

        .inline-form { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .inline-form .full { grid-column: 1 / -1; }
        .inline-form label { font-size: 0.78rem; font-weight: 600; color: #4a4440; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; display: block; }
        .inline-form input { width: 100%; padding: 8px 12px; border: 1px solid #e6e2dc; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.88rem; color: #241f1a; background: #faf9f7; transition: border-color 0.12s ease; }
        .inline-form input:focus { outline: none; border-color: #241f1a; background: #fff; }

        .action-row { display: flex; align-items: center; gap: 10px; margin-top: 16px; padding-top: 14px; border-top: 1px solid #f0ede8; flex-wrap: wrap; }
        .empty-state { text-align: center; padding: 60px 20px; color: #8c8580; }
        .empty-state h3 { font-size: 1.1rem; color: #241f1a; margin-bottom: 6px; }

        .chevron { transition: transform 0.2s ease; font-size: 0.8rem; color: #8c8580; }
        .chevron.is-open { transform: rotate(180deg); }

        @media (max-width: 768px) {
            .group-card-header { flex-direction: column; align-items: flex-start; }
            .group-meta { flex-wrap: wrap; }
            .inline-form { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    <?php include 'config/shared_navbar.php'; ?>
    <div class="main-content">
        <div id="flash-data" data-flash='<?php echo json_encode(flashMessages()); ?>' style="display:none"></div>

        <div class="page-head">
            <h1>Group Management</h1>
            <p>Manage all savings groups across the platform</p>
        </div>

        <?php if (count($groups) === 0): ?>
        <div class="empty-state">
            <h3>No groups yet</h3>
            <p>Groups will appear here once they are registered.</p>
        </div>
        <?php else: ?>
        <div class="groups-grid">
            <?php foreach ($groups as $g): ?>
            <div class="group-card">
                <div class="group-card-header" data-toggle>
                    <div>
                        <h3><?php echo htmlspecialchars($g['group_name']); ?></h3>
                        <span class="badge-code"><?php echo htmlspecialchars($g['invitation_code'] ?? '—'); ?></span>
                    </div>
                    <div class="group-meta">
                        <span><?php echo (int)$g['member_count']; ?> members</span>
                        <?php if ((int)$g['pending_count'] > 0): ?>
                        <span class="badge badge-pending"><?php echo (int)$g['pending_count']; ?> pending</span>
                        <?php endif; ?>
                        <span>K <?php echo number_format($g['total_savings'], 0); ?></span>
                        <span class="chevron"><i class="fa-solid fa-chevron-down"></i></span>
                    </div>
                </div>
                <div class="group-card-body">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <dt>Group Code</dt>
                            <dd class="mono"><?php echo htmlspecialchars($g['group_code']); ?></dd>
                        </div>
                        <div class="detail-item">
                            <dt>Interest Rate</dt>
                            <dd><?php echo htmlspecialchars($g['interest_rate']); ?>%</dd>
                        </div>
                        <div class="detail-item">
                            <dt>Penalty Rate</dt>
                            <dd><?php echo htmlspecialchars($g['penalty_rate']); ?>%</dd>
                        </div>
                        <div class="detail-item">
                            <dt>Meeting Day</dt>
                            <dd><?php echo htmlspecialchars($g['meeting_day'] ?? 'Not set'); ?></dd>
                        </div>
                        <div class="detail-item">
                            <dt>Contribution</dt>
                            <dd>K <?php echo number_format($g['contribution_amount'], 2); ?></dd>
                        </div>
                        <div class="detail-item">
                            <dt>Active Loans</dt>
                            <dd>K <?php echo number_format($g['active_loans'], 0); ?></dd>
                        </div>
                        <div class="detail-item">
                            <dt>Created</dt>
                            <dd><?php echo date('d M Y', strtotime($g['created_at'])); ?></dd>
                        </div>
                        <div class="detail-item">
                            <dt>Group Admin</dt>
                            <dd><?php echo htmlspecialchars($g['admin_name'] ?? '—'); ?></dd>
                        </div>
                    </div>

                    <form method="POST" class="inline-form">
                        <input type="hidden" name="action" value="edit_group">
                        <input type="hidden" name="group_id" value="<?php echo (int)$g['id']; ?>">
                        <?php echo csrfField(); ?>
                        <div class="full">
                            <label>Group Name</label>
                            <input type="text" name="group_name" value="<?php echo htmlspecialchars($g['group_name']); ?>" required>
                        </div>
                        <div>
                            <label>Interest Rate (%)</label>
                            <input type="number" name="interest_rate" step="0.01" value="<?php echo htmlspecialchars($g['interest_rate']); ?>">
                        </div>
                        <div>
                            <label>Penalty Rate (%)</label>
                            <input type="number" name="penalty_rate" step="0.01" value="<?php echo htmlspecialchars($g['penalty_rate']); ?>">
                        </div>
                        <div>
                            <label>Meeting Day</label>
                            <input type="text" name="meeting_day" placeholder="e.g. Monday" value="<?php echo htmlspecialchars($g['meeting_day'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Contribution Amount (K)</label>
                            <input type="number" name="contribution_amount" step="0.01" value="<?php echo htmlspecialchars($g['contribution_amount']); ?>">
                        </div>
                        <div class="full action-row">
                            <button type="submit" class="btn btn-primary"><i class="fa-regular fa-floppy-disk"></i> Save Changes</button>
                    </form>

                    <form method="POST" style="display:inline" onsubmit="return confirm('Reset the invitation code for this group? Members with the old code will need the new one.')">
                        <input type="hidden" name="action" value="reset_code">
                        <input type="hidden" name="group_id" value="<?php echo (int)$g['id']; ?>">
                        <?php echo csrfField(); ?>
                        <button type="submit" class="btn btn-sm"><i class="fa-regular fa-rotate"></i> Reset Code</button>
                    </form>

                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this group and all associated data? This cannot be undone.')">
                        <input type="hidden" name="action" value="delete_group">
                        <input type="hidden" name="group_id" value="<?php echo (int)$g['id']; ?>">
                        <?php echo csrfField(); ?>
                        <button type="submit" class="btn btn-sm btn-danger"><i class="fa-regular fa-trash-can"></i> Delete Group</button>
                    </form>
                        </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

<script>
document.querySelectorAll('[data-toggle]').forEach(function(header) {
    header.addEventListener('click', function() {
        var body = this.nextElementSibling;
        var chevron = this.querySelector('.chevron');
        body.classList.toggle('is-open');
        if (chevron) chevron.classList.toggle('is-open');
    });
});
</script>
<script src="assets/js/toast.js"></script>
</body>
</html>
