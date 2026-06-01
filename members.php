<?php
require_once 'includes/init.php';

$user = requireRole(['admin', 'group_admin', 'loan_officer']);
$user_id = $user['id'];
$role = $user['role'];
$group_id = $user['group_id'];

// Handle member addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_member') {
        if ($role !== 'admin' && $role !== 'group_admin') {
            setFlash('error', "Only admins and group admins can add new members.");
        } else {
            $username = trim($_POST['username']);
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $member_group_id = ($role == 'admin' && !empty($_POST['group_id'])) ? intval($_POST['group_id']) : $group_id;
            
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, group_id) VALUES (?, ?, ?, ?, ?, 'member', ?)");
                $stmt->execute([$username, $password, $full_name, $email, $phone, $member_group_id]);
                setFlash('success', 'Member added successfully!');
            } catch (PDOException $e) {
                setFlash('error', "Error adding member: " . $e->getMessage());
            }
        }
    }
}

// Handle member edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'edit_member') {
        if ($role !== 'admin' && $role !== 'group_admin') {
            setFlash('error', "Only admins and group admins can edit members.");
        } else {
            $edit_id = intval($_POST['edit_id']);
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $new_password = $_POST['new_password'] ?? '';
            try {
                if ($new_password !== '') {
                    require_once 'config/validation.php';
                    if (!Validation::password($new_password, 8)) {
                        setFlash('error', Validation::firstError());
                    } else {
                        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, password = ? WHERE id = ? AND role = 'member'");
                        $stmt->execute([$full_name, $email, $phone, $hashed, $edit_id]);
                        setFlash('success', 'Member updated and password changed!');
                    }
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ? AND role = 'member'");
                    $stmt->execute([$full_name, $email, $phone, $edit_id]);
                    setFlash('success', 'Member updated successfully!');
                }
            } catch (PDOException $e) {
                setFlash('error', "Error updating member: " . $e->getMessage());
            }
        }
    } elseif ($_POST['action'] === 'delete_member') {
        if ($role !== 'admin' && $role !== 'group_admin') {
            setFlash('error', "Only admins and group admins can delete members.");
        } else {
            $delete_id = intval($_POST['delete_id']);
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'member'");
                $stmt->execute([$delete_id]);
                setFlash('success', 'Member deleted successfully!');
            } catch (PDOException $e) {
                setFlash('error', "Error deleting member: " . $e->getMessage());
            }
        }
    } elseif ($_POST['action'] === 'approve_member') {
        $approve_id = intval($_POST['approve_id']);
        try {
            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'member' AND status = 'pending'");
            $stmt->execute([$approve_id]);
            if ($stmt->rowCount() > 0) {
                setFlash('success', 'Member approved successfully!');
                notifyUser($approve_id, 'Membership Approved', 'Your membership has been approved. You can now access all group features.');
            } else {
                setFlash('error', 'Member not found or already approved.');
            }
        } catch (PDOException $e) {
            setFlash('error', "Error approving member: " . $e->getMessage());
        }
    }
}

// Get members list
if ($role == 'admin') {
    $stmt = $pdo->prepare("SELECT u.*, sg.group_name FROM users u LEFT JOIN savings_groups sg ON u.group_id = sg.id WHERE u.role = 'member' AND (u.status IS NULL OR u.status = 'active') ORDER BY u.created_at DESC");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE group_id = ? AND role = 'member' AND (status IS NULL OR status = 'active') ORDER BY created_at DESC");
    $stmt->execute([$group_id]);
}
$members = $stmt->fetchAll();

// Get pending members
if ($role == 'admin') {
    $stmt = $pdo->prepare("SELECT u.*, sg.group_name FROM users u LEFT JOIN savings_groups sg ON u.group_id = sg.id WHERE u.role = 'member' AND u.status = 'pending' ORDER BY u.created_at DESC");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE group_id = ? AND role = 'member' AND status = 'pending' ORDER BY created_at DESC");
    $stmt->execute([$group_id]);
}
$pending_members = $stmt->fetchAll();

// Get group invitation code for admin/group_admin
$invitation_code = '';
$group_code = '';
if ($role == 'group_admin' && $group_id) {
    $stmt = $pdo->prepare("SELECT invitation_code, group_code FROM savings_groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $group_code = $row['group_code'] ?? '';
    $invitation_code = $row['invitation_code'] ?? '';
    // Auto-backfill if missing
    if (empty($invitation_code)) {
        do {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $chk = $pdo->prepare("SELECT COUNT(*) as cnt FROM savings_groups WHERE invitation_code = ?");
            $chk->execute([$code]);
        } while ($chk->fetch()['cnt'] > 0);
        $upd = $pdo->prepare("UPDATE savings_groups SET invitation_code = ? WHERE id = ?");
        $upd->execute([$code, $group_id]);
        $invitation_code = $code;
    }
} elseif ($role == 'admin' && !empty($groups)) {
    // For admin, show first group's code as default; user picks which group
}

// Get groups for admin
$groups = [];
if ($role == 'admin') {
    $stmt = $pdo->query("SELECT * FROM savings_groups ORDER BY group_name");
    $groups = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members - RSGMS</title>
    <link rel="stylesheet" href="assets/css/icons.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <style>
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    <?php include 'config/shared_navbar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <h2>Member Management</h2>
        </div>
        
        <div id="flash-data" data-flash='<?php echo json_encode(flashMessages()); ?>' style="display:none"></div>

        <?php if ($role == 'group_admin' && $invitation_code): ?>
        <div class="section">
            <div class="invitation-card" role="region" aria-label="Group invitation code">
                <div class="invite-meta">
                    <div style="font-size:0.85rem;opacity:0.9;margin-bottom:4px;"><i class="fa-solid fa-key"></i> Group Invitation Code</div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="invite-code" id="group-invite-code" style="font-family:var(--font-mono);font-weight:700;font-size:1.4rem;letter-spacing:4px;"><?php echo htmlspecialchars($invitation_code); ?></div>
                        <button class="copy-btn" data-copy="<?php echo htmlspecialchars($invitation_code); ?>" aria-label="Copy invitation code"><i class="fa-regular fa-copy"></i></button>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:0.9rem;opacity:0.9;">Share this code with new members</div>
                    <div style="font-size:0.78rem;opacity:0.75;margin-top:6px;">They enter it at sign in to request membership</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (($role == 'admin' || $role == 'group_admin') && count($pending_members) > 0): ?>
        <div class="section">
            <div class="section-title">
                <span><i class="fa-solid fa-hourglass-half section-icon"></i> Pending Approvals (<?php echo count($pending_members); ?>)</span>
            </div>
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Group</th>
                            <th>Requested</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_members as $index => $m): ?>
                        <tr style="background:#fffbeb;">
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($m['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($m['username']); ?></td>
                            <td><?php echo htmlspecialchars($m['email'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($m['phone'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($m['group_name'] ?? 'N/A'); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($m['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="approve_member">
                                    <input type="hidden" name="approve_id" value="<?php echo $m['id']; ?>">
                                    <button type="submit" class="btn-sm" style="background:#16a34a;color:white;">Approve</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="section">
            <div class="section-title">
                <span><i class="fa-solid fa-users section-icon"></i> All Members</span>
                <?php if ($role == 'admin' || $role == 'group_admin'): ?>
                <button class="btn-add" onclick="openModal('addModal')">+ Add New Member</button>
                <?php endif; ?>
            </div>
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Group</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($members) > 0): ?>
                            <?php foreach ($members as $index => $member): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($member['username']); ?></td>
                                <td><?php echo htmlspecialchars($member['email'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($member['phone'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($member['group_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($member['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-view" onclick="viewMember(<?php echo $member['id']; ?>)">View</button>
                                    <?php if ($role == 'admin' || $role == 'group_admin'): ?>
                                    <button class="btn btn-sm btn-edit" onclick="editMember(<?php echo $member['id']; ?>)">Edit</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this member permanently?');">
                                        <input type="hidden" name="action" value="delete_member">
                                        <input type="hidden" name="delete_id" value="<?php echo $member['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px 12px;"><i class="fa-regular fa-users" style="font-size:1.3rem;margin-right:6px;"></i> No members yet — invite someone to join your group!</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php if ($role == 'admin' || $role == 'group_admin'): ?>
    <!-- Add Member Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Member</h3>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_member">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" required>
                </div>
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone">
                    </div>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" required>
                </div>
                <?php if ($role == 'admin'): ?>
                <div class="form-group">
                    <label>Assign to Group</label>
                    <select name="group_id">
                        <option value="">Select Group</option>
                        <?php foreach ($groups as $group): ?>
                        <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['group_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <button type="submit" class="btn-add" style="width: 100%; margin-top: 10px;">Register Member</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- View Member Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Member Details</h3>
                <button class="close" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div id="memberDetailContent">
                <div class="detail-row"><span class="detail-label">Full Name</span><span class="detail-value" id="det_name">—</span></div>
                <div class="detail-row"><span class="detail-label">Username</span><span class="detail-value" id="det_username">—</span></div>
                <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value" id="det_email">—</span></div>
                <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value" id="det_phone">—</span></div>
                <div class="detail-row"><span class="detail-label">Group</span><span class="detail-value" id="det_group">—</span></div>
                <div class="detail-row"><span class="detail-label">Role</span><span class="detail-value" id="det_role">Member</span></div>
                <div class="detail-row"><span class="detail-label">Joined</span><span class="detail-value" id="det_joined">—</span></div>
            </div>
        </div>
    </div>
    
    <?php if ($role == 'admin' || $role == 'group_admin'): ?>
    <!-- Edit Member Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Member</h3>
                <button class="close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_member">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" id="edit_full_name" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="edit_email">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" id="edit_phone">
                    </div>
                </div>
                <?php if ($role == 'admin' || $role == 'group_admin'): ?>
                <hr style="margin:16px 0;border:none;border-top:1px solid #e5e7eb;">
                <p style="font-size:0.85rem;color:#6b7280;margin-bottom:12px;">Leave blank to keep current password.</p>
                <div class="form-row">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" id="edit_password" minlength="8" maxlength="128" placeholder="Min 8 chars, upper + digit">
                    </div>
                    <div class="form-group">
                        <label>Verify Password</label>
                        <input type="password" name="confirm_password" id="edit_confirm" placeholder="Re-enter new password">
                    </div>
                </div>
                <?php endif; ?>
                <button type="submit" class="btn-add" style="width:100%;margin-top:10px;">Update Member</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <style>
        .detail-row {
            display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0;
        }
        .detail-label { color: #64748b; font-size: 0.9rem; }
        .detail-value { font-weight: 600; color: #1f2937; }
    </style>
    <script>
        const membersData = <?php echo json_encode($members); ?>;
        
        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function viewMember(id) {
            const m = membersData.find(u => u.id == id);
            if (!m) return;
            document.getElementById('det_name').textContent = m.full_name || '—';
            document.getElementById('det_username').textContent = m.username || '—';
            document.getElementById('det_email').textContent = m.email || '—';
            document.getElementById('det_phone').textContent = m.phone || '—';
            document.getElementById('det_group').textContent = m.group_name || 'N/A';
            document.getElementById('det_role').textContent = 'Member';
            document.getElementById('det_joined').textContent = m.created_at ? new Date(m.created_at).toLocaleDateString('en-GB') : '—';
            openModal('viewModal');
        }

        <?php if ($role == 'admin' || $role == 'group_admin'): ?>
        function editMember(id) {
            const m = membersData.find(u => u.id == id);
            if (!m) return;
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_full_name').value = m.full_name || '';
            document.getElementById('edit_email').value = m.email || '';
            document.getElementById('edit_phone').value = m.phone || '';
            openModal('editModal');
        }
        <?php endif; ?>

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
    <link rel="stylesheet" href="assets/css/toast.css">
    <script src="assets/js/toast.js"></script>
</body>
</html>
