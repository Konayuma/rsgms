<?php
require_once 'includes/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'member') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$group_id = $_SESSION['group_id'] ?? 0;

if (!$group_id) {
    header('Location: dashboard.php');
    exit();
}

// Fetch all meetings for the member's group
$stmt = $pdo->prepare("SELECT * FROM meetings WHERE group_id = ? ORDER BY meeting_date DESC");
$stmt->execute([$group_id]);
$meetings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting Minutes - RSGMS</title>
    <link rel="stylesheet" href="assets/css/icons.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    <?php include 'config/shared_navbar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h2><i class="fa-solid fa-file-lines section-icon"></i> Meeting Minutes</h2>
        </div>

        <div class="section">
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Attendance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($meetings) > 0): ?>
                            <?php foreach ($meetings as $meeting): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($meeting['meeting_date'])); ?></td>
                                <td><?php echo ucfirst($meeting['meeting_type'] ?? 'Regular'); ?></td>
                                <td><?php echo $meeting['attendance_count'] ?? '-'; ?></td>
                                <td>
                                    <button class="btn-minutes" onclick="viewMinutes(<?php echo $meeting['id']; ?>)">View Minutes</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fa-regular fa-calendar"></i></div>
                                        <div class="empty-state-title">No meetings recorded</div>
                                        <div class="empty-state-text">Meeting minutes will appear here once the group admin records them.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Minutes View Modal -->
    <div id="minutesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fa-solid fa-file-lines section-icon"></i> Meeting Minutes</h3>
                <button class="modal-close" onclick="closeModal('minutesModal')">&times;</button>
            </div>
            <div id="minutesContent" style="white-space: pre-wrap; line-height: 1.7; font-size: 0.92rem; color: var(--ink); max-height: 60vh; overflow-y: auto;"></div>
        </div>
    </div>

    <script>
        const meetingsData = <?php echo json_encode($meetings); ?>;

        function viewMinutes(meetingId) {
            const meeting = meetingsData.find(m => m.id == meetingId);
            if (!meeting) return;
            const content = document.getElementById('minutesContent');
            content.textContent = meeting.minutes && meeting.minutes.trim()
                ? meeting.minutes
                : 'No minutes recorded for this meeting.';
            document.getElementById('minutesModal').style.display = 'flex';
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
    <script src="assets/js/loading.js"></script>
</body>
</html>
