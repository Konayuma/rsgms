<?php
require_once 'includes/init.php';

$user = requireLogin();
$user_id = $user['id'];
$role = $user['role'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: login.php');
    exit();
}

$user_display_name = $user['full_name'] ?: ($_SESSION['username'] ?? 'User');
$user_role_label = ucfirst(str_replace('_', ' ', $role));

// Handle notification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_read') {
        $notification_id = $_POST['notification_id'];
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
        setFlash('success', 'Notification marked as read.');
    } elseif ($_POST['action'] === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
        $stmt->execute([$user_id]);
        setFlash('success', 'All notifications marked as read.');
    } elseif ($_POST['action'] === 'send_notification' && ($role == 'admin' || $role == 'group_admin')) {
        $title = trim($_POST['title']);
        $notification_message = trim($_POST['message']);
        $target_users = $_POST['target_users'] ?? [];
        
        if (empty($target_users)) {
            if ($role == 'admin') {
                $stmt = $pdo->query("SELECT id FROM users WHERE role IN ('group_admin', 'member')");
            } else {
                $group_id = $_SESSION['group_id'];
                $stmt = $pdo->prepare("SELECT id FROM users WHERE group_id = ? AND role IN ('group_admin', 'member')");
                $stmt->execute([$group_id]);
            }
            $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $users = $target_users;
        }
        
        foreach ($users as $target_user_id) {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            $stmt->execute([$target_user_id, $title, $notification_message]);
        }
        setFlash('success', "Notification sent successfully to " . count($users) . " users.");
    }
}

// Get notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();
$recent_notifications = array_slice($notifications, 0, 5);

// Get unread count
$stmt = $pdo->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = FALSE");
$stmt->execute([$user_id]);
$unread_count = $stmt->fetch()['unread'];

// Get users for sending notifications (admins only)
$users_list = [];
if ($role == 'admin' || $role == 'group_admin') {
    if ($role == 'admin') {
        $stmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role IN ('group_admin', 'member') ORDER BY full_name");
    } else {
        $group_id = $_SESSION['group_id'];
        $stmt = $pdo->prepare("SELECT id, full_name, username FROM users WHERE group_id = ? AND role IN ('group_admin', 'member') ORDER BY full_name");
        $stmt->execute([$group_id]);
    }
    $users_list = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - RSGMS</title>
    <link rel="stylesheet" href="assets/css/icons.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <style>
        .page-head {
            margin-bottom: 28px;
        }

        .page-head h1 {
            font-family: var(--font-family);
            font-size: clamp(1.75rem, 3vw, 2.25rem);
            font-weight: 700;
            color: var(--text-title);
            letter-spacing: -0.01em;
            line-height: 1.15;
        }

        .page-head p {
            font-size: 0.95rem;
            color: var(--text-muted);
            margin-top: 6px;
            line-height: 1.5;
        }

        .page-head-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .alert-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 42px;
            padding: 0 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface);
            color: var(--text-body);
            cursor: pointer;
            font-family: var(--font-family);
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.15s ease;
            position: relative;
        }

        .alert-btn:hover {
            border-color: #ccc6be;
            box-shadow: var(--shadow-sm);
        }

        .alert-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 999px;
            background: var(--accent);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .alert-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 360px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 24px 48px rgba(36,31,26,0.12);
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-6px) scale(0.97);
            transition: opacity 0.18s ease, transform 0.18s ease, visibility 0.18s ease;
            z-index: 40;
        }

        .alert-dropdown.is-open {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        .alert-dropdown-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 18px 12px;
        }

        .alert-dropdown-title {
            font-family: var(--font-family);
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-title);
        }

        .alert-dropdown-sub {
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .mark-all-btn {
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text-body);
            font-family: var(--font-family);
            font-size: 0.78rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .mark-all-btn:hover {
            background: #f8f6f3;
            border-color: #ccc6be;
        }

        .alert-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 18px;
            text-decoration: none;
            color: var(--text-body);
            transition: background 0.12s ease;
        }

        .alert-item:hover {
            background: #f8f6f3;
        }

        .alert-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent);
            margin-top: 6px;
            flex: 0 0 auto;
        }

        .alert-dot.read {
            background: var(--border);
        }

        .alert-item-content strong {
            display: block;
            font-weight: 600;
            color: var(--text-title);
            margin-bottom: 2px;
        }

        .alert-item-content span {
            font-size: 0.82rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .alert-empty {
            padding: 24px 18px;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .compose-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 28px;
            overflow: hidden;
        }

        .compose-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border-light);
        }

        .compose-header h3 {
            font-family: var(--font-family);
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-title);
        }

        .compose-body {
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .compose-fields {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .compose-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .compose-field label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-body);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .compose-field input,
        .compose-field textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: var(--font-family);
            font-size: 0.92rem;
            color: var(--text-title);
            background: var(--page-bg);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .compose-field input:focus,
        .compose-field textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-light);
            background: var(--surface);
        }

        .compose-field textarea {
            resize: vertical;
            min-height: 100px;
        }

        .recipient-panel {
            border-top: 1px solid var(--border-light);
            padding-top: 20px;
        }

        .recipient-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .recipient-meta-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-body);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .recipient-count {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #f0ede8;
            color: var(--text-body);
            font-size: 0.78rem;
            font-weight: 600;
        }

        .recipient-list {
            max-height: 260px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            display: flex;
            flex-direction: column;
        }

        .recipient-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 12px;
            transition: background 0.12s ease;
            cursor: pointer;
            border-bottom: 1px solid var(--border-light);
        }

        .recipient-item:last-child {
            border-bottom: none;
        }

        .recipient-item:hover {
            background: #f8f6f3;
        }

        .recipient-item input[type="checkbox"] {
            width: 17px;
            height: 17px;
            margin-top: 2px;
            accent-color: var(--accent);
            flex: 0 0 auto;
        }

        .recipient-item-text strong {
            display: block;
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--text-title);
        }

        .recipient-subtext {
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .recipient-empty {
            padding: 16px;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.88rem;
            line-height: 1.5;
            border: 1px dashed var(--border);
            border-radius: var(--radius-sm);
            background: var(--page-bg);
        }

        .send-row {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 14px;
            padding-top: 16px;
            border-top: 1px solid var(--border-light);
        }

        .send-hint {
            font-size: 0.82rem;
            color: var(--text-muted);
            line-height: 1.4;
            margin-right: auto;
        }

        .btn-send {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 42px;
            padding: 0 22px;
            border: none;
            border-radius: var(--radius-sm);
            background: var(--text-title);
            color: #fff;
            font-family: var(--font-family);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-send:hover {
            background: #3d352f;
            transform: translateY(-1px);
        }

        .btn-send:active {
            transform: translateY(0);
        }

        .notifications-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .notifications-header h2 {
            font-family: var(--font-family);
            font-size: clamp(1.3rem, 2vw, 1.55rem);
            font-weight: 700;
            color: var(--text-title);
        }

        .notifications-count {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .notification-item {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 18px 20px;
            margin-bottom: 10px;
            transition: all 0.15s ease;
        }

        .notification-item:hover {
            border-color: #d6d0c8;
            box-shadow: var(--shadow-sm);
        }

        .notification-item.unread {
            background: var(--accent-light);
            border-color: var(--accent-border);
            border-left: 3px solid var(--accent);
        }

        .notification-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 6px;
        }

        .notification-title {
            font-family: var(--font-family);
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-title);
            line-height: 1.3;
        }

        .notification-date {
            font-size: 0.8rem;
            color: var(--text-muted);
            white-space: nowrap;
            margin-top: 3px;
            flex: 0 0 auto;
        }

        .notification-message {
            font-size: 0.9rem;
            color: var(--text-body);
            line-height: 1.55;
            margin-bottom: 12px;
        }

        .notification-foot {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-mark-read {
            padding: 5px 14px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text-body);
            font-family: var(--font-family);
            font-size: 0.78rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.12s ease;
        }

        .btn-mark-read:hover {
            background: #f8f6f3;
            border-color: #ccc6be;
        }

        .unread-tag {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 999px;
            background: var(--accent);
            color: #fff;
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .no-notifications {
            text-align: center;
            padding: 60px 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
        }

        .no-notifications h3 {
            font-family: var(--font-family);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-title);
            margin-bottom: 8px;
        }

        .no-notifications p {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.5;
            max-width: 360px;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .alert-dropdown {
                width: calc(100vw - 32px);
                right: auto;
                left: 0;
            }
            .compose-body {
                padding: 16px;
            }
            .send-row {
                flex-direction: column;
                align-items: stretch;
            }
            .send-hint {
                margin-right: 0;
                text-align: center;
            }
            .btn-send {
                justify-content: center;
            }
            .notification-head {
                flex-direction: column;
                gap: 4px;
            }
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    <?php include 'config/shared_navbar.php'; ?>
    
    <div class="main-content">
        <div class="page-head">
            <div class="page-head-top">
                <div>
                    <h1>Notifications</h1>
                    <p>Review updates, recent activity, and quick account actions.</p>
                </div>
                <div style="position:relative">
                    <button type="button" class="alert-btn" id="notificationToggle" aria-haspopup="true" aria-expanded="false" aria-controls="notificationMenu">
                        <i class="fa-regular fa-bell"></i>
                        <span>Alerts</span>
                        <?php if ($unread_count > 0): ?><span class="alert-badge"><?php echo $unread_count; ?></span><?php endif; ?>
                    </button>
                    <div class="alert-dropdown" id="notificationMenu" role="menu" aria-label="Notification preview">
                        <div class="alert-dropdown-head">
                            <div>
                                <div class="alert-dropdown-title">Recent notifications</div>
                                <div class="alert-dropdown-sub"><?php echo $unread_count; ?> unread</div>
                            </div>
                            <?php if ($unread_count > 0): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="mark_all_read">
                                <button type="submit" class="mark-all-btn">Mark all read</button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php if (count($recent_notifications) > 0): ?>
                            <?php foreach ($recent_notifications as $item): ?>
                            <?php $preview_message = function_exists('mb_strimwidth') ? mb_strimwidth($item['message'], 0, 82, '...') : (strlen($item['message']) > 82 ? substr($item['message'], 0, 82) . '...' : $item['message']); ?>
                            <a class="alert-item" href="#notifications-list">
                                <span class="alert-dot <?php echo $item['is_read'] ? 'read' : ''; ?>"></span>
                                <span class="alert-item-content">
                                    <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                    <span><?php echo htmlspecialchars($preview_message); ?></span>
                                </span>
                            </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state" style="padding:20px 16px"><div class="empty-state-icon" style="width:40px;height:40px;font-size:.95rem;margin-bottom:8px"><i class="fa-regular fa-bell"></i></div><div class="empty-state-title" style="font-size:.9rem">No notifications yet</div><div class="empty-state-text">New alerts will appear here first.</div></div>
                        <?php endif; ?>
                    </div>
            </div>
        </div>
        <div id="flash-data" data-flash='<?php echo json_encode(flashMessages()); ?>' style="display:none"></div>
        <?php if ($role == 'admin' || $role == 'group_admin'): ?>
        <div class="compose-section">
            <div class="compose-header">
                <h3>Send a notification</h3>
            </div>
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="send_notification">
                <div class="compose-body">
                    <div class="compose-fields">
                        <div class="compose-field">
                            <label for="title">Title</label>
                            <input type="text" name="title" id="title" placeholder="e.g. Meeting reminder" required>
                        </div>
                        <div class="compose-field">
                            <label for="message">Message</label>
                            <textarea name="message" id="message" placeholder="Write your notification message…" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="recipient-panel">
                        <div class="recipient-meta">
                            <span class="recipient-meta-label">Recipients</span>
                            <span class="recipient-count"><span id="selected_count">0</span> selected</span>
                        </div>
                        <?php if (count($users_list) > 0): ?>
                        <div class="recipient-list">
                            <label class="recipient-item" style="border-bottom: 1px solid #f0ede8;">
                                <input type="checkbox" id="select_all_users">
                                <div class="recipient-item-text">
                                    <strong>Select all recipients</strong>
                                    <span class="recipient-subtext">Target every member</span>
                                </div>
                            </label>
                            <?php foreach ($users_list as $user): ?>
                            <label class="recipient-item">
                                <input type="checkbox" name="target_users[]" value="<?php echo $user['id']; ?>">
                                <div class="recipient-item-text">
                                    <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                    <span class="recipient-subtext"><?php echo htmlspecialchars($user['username']); ?></span>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="recipient-empty">No eligible recipients are available for this account right now.</div>
                        <?php endif; ?>
                        <div class="send-row">
                            <span class="send-hint">Keep messages specific so members know what action is expected.</span>
                            <button type="submit" class="btn-send"><i class="fa-regular fa-paper-plane"></i> Send</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <div id="notifications-list">
            <div class="notifications-header">
                <h2>Your notifications</h2>
                <span class="notifications-count"><?php echo count($notifications); ?> total</span>
            </div>
            
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                    <div class="notification-head">
                        <span class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></span>
                        <span class="notification-date"><?php echo date('d M Y, H:i', strtotime($notification['created_at'])); ?></span>
                    </div>
                    <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                    <div class="notification-foot">
                        <?php if (!$notification['is_read']): ?>
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="mark_read">
                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                            <button type="submit" class="btn-mark-read">Mark as read</button>
                        </form>
                        <span class="unread-tag">New</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="padding:60px 20px"><div class="empty-state-icon empty-state-icon-lg"><i class="fa-regular fa-bell"></i></div><div class="empty-state-title">No notifications yet</div><div class="empty-state-text">You'll receive notifications about important updates, meeting reminders, and payment due dates.</div></div>
            <?php endif; ?>
        </div>
    </div>
</body>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var dropdownButtons = [
            { button: document.getElementById('notificationToggle'), menu: document.getElementById('notificationMenu') }
        ];

        function closeAllMenus() {
            dropdownButtons.forEach(function(entry) {
                if (!entry.button || !entry.menu) return;
                entry.button.setAttribute('aria-expanded', 'false');
                entry.menu.classList.remove('is-open');
            });
        }

        dropdownButtons.forEach(function(entry) {
            if (!entry.button || !entry.menu) return;
            entry.button.addEventListener('click', function(event) {
                event.stopPropagation();
                var isOpen = entry.menu.classList.contains('is-open');
                closeAllMenus();
                if (!isOpen) {
                    entry.menu.classList.add('is-open');
                    entry.button.setAttribute('aria-expanded', 'true');
                }
            });
        });

        document.addEventListener('click', function(event) {
            var target = event.target;
            var clickedInside = dropdownButtons.some(function(entry) {
                return entry.button && entry.menu && (entry.button.contains(target) || entry.menu.contains(target));
            });

            if (!clickedInside) {
                closeAllMenus();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAllMenus();
            }
        });

        var selectAll = document.getElementById('select_all_users');
        var recipientChecks = document.querySelectorAll('input[name="target_users[]"]');
        var selectedCount = document.getElementById('selected_count');

        function updateSelectedCount() {
            var checkedCount = document.querySelectorAll('input[name="target_users[]"]:checked').length;
            if (selectedCount) {
                selectedCount.textContent = checkedCount;
            }
            if (selectAll) {
                selectAll.checked = recipientChecks.length > 0 && checkedCount === recipientChecks.length;
            }
        }

        if (!selectAll) {
            updateSelectedCount();
            return;
        }

        selectAll.addEventListener('change', function() {
            recipientChecks.forEach(function(c) { c.checked = selectAll.checked; });
            updateSelectedCount();
        });

        recipientChecks.forEach(function(check) {
            check.addEventListener('change', updateSelectedCount);
        });

        updateSelectedCount();
    });
</script>
<link rel="stylesheet" href="assets/css/toast.css">
<script src="assets/js/loading.js"></script>
<script src="assets/js/toast.js"></script>
</html>
