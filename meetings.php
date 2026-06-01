<?php require_once 'includes/init.php'; $user = requireRole(['admin', 'group_admin']);
$user_id = $user['id'];
$role = $user['role'];
$group_id = $user['group_id'];
$message = '';
$error = '';

// Handle meeting recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'record_meeting') {
        $meeting_date = $_POST['meeting_date'];
        $meeting_type = $_POST['meeting_type'];
        $attendance_count = intval($_POST['attendance_count']);
        $savings_collected = floatval($_POST['savings_collected']);
        $loans_disbursed = floatval($_POST['loans_disbursed']);
        $loans_repaid = floatval($_POST['loans_repaid']);
        $minutes = trim($_POST['minutes']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO meetings (group_id, meeting_date, meeting_type, attendance_count, savings_collected, loans_disbursed, loans_repaid, minutes, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$group_id, $meeting_date, $meeting_type, $attendance_count, $savings_collected, $loans_disbursed, $loans_repaid, $minutes, $user_id]);
            $message = "Meeting recorded successfully!";
        } catch (PDOException $e) {
            $error = "Error recording meeting: " . $e->getMessage();
        }
    }
}

// Get meetings list
if ($role == 'admin') {
    $stmt = $pdo->prepare("SELECT m.*, sg.group_name FROM meetings m LEFT JOIN savings_groups sg ON m.group_id = sg.id ORDER BY m.meeting_date DESC");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT * FROM meetings WHERE group_id = ? ORDER BY meeting_date DESC");
    $stmt->execute([$group_id]);
}
$meetings = $stmt->fetchAll();

// Get upcoming meeting info
$upcoming_meeting = null;
if ($group_id) {
    $stmt = $pdo->prepare("SELECT * FROM savings_groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();
    
    if ($group && $group['meeting_day']) {
        // Calculate next meeting date
        $today = date('Y-m-d');
        $meeting_day = $group['meeting_day'];
        $days = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7];
        $target_day = $days[$meeting_day];
        $current_day = date('N'); // 1 = Monday, 7 = Sunday
        
        $days_until = ($target_day - $current_day + 7) % 7;
        if ($days_until == 0) $days_until = 7; // If today is meeting day, get next week
        
        $next_meeting = date('Y-m-d', strtotime("+$days_until days"));
        $upcoming_meeting = ['date' => $next_meeting, 'day' => $meeting_day];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meetings - RSGMS</title>
    <link rel="stylesheet" href="assets/css/icons.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <style>
        .upcoming-meeting {
            background: #f8fafc;
            color: #1f2937;
            border: 1px solid #dbe3ee;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 25px;
        }
        
        .meeting-date {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    <?php include 'config/shared_navbar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <h2><i class="fa-solid fa-calendar-days section-icon"></i> Meeting Management</h2>
        </div>
        
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Upcoming Meeting -->
        <?php if ($upcoming_meeting): ?>
        <div class="upcoming-meeting">
            <div class="meeting-date"><i class="fa-solid fa-calendar-days section-icon"></i> Next Meeting: <?php echo date('l, F j, Y', strtotime($upcoming_meeting['date'])); ?></div>
            <p>Regular <?php echo $upcoming_meeting['day']; ?> meeting</p>
        </div>
        <?php endif; ?>
        
        <!-- Record Meeting Form -->
        <div class="section">
            <div class="section-title">
                <span><i class="fa-solid fa-pen-to-square section-icon"></i> Record Meeting</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="record_meeting">
                <div class="form-row">
                    <div class="form-group">
                        <label>Meeting Date *</label>
                        <input type="date" name="meeting_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Meeting Type</label>
                        <select name="meeting_type">
                            <option value="regular">Regular Meeting</option>
                            <option value="special">Special Meeting</option>
                            <option value="annual">Annual General Meeting</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Attendance Count</label>
                        <input type="number" name="attendance_count" min="0">
                    </div>
                    <div class="form-group">
                        <label>Savings Collected (K)</label>
                        <input type="number" name="savings_collected" step="0.01" min="0">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Loans Disbursed (K)</label>
                        <input type="number" name="loans_disbursed" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Loans Repaid (K)</label>
                        <input type="number" name="loans_repaid" step="0.01" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label>Meeting Minutes</label>
                    <textarea name="minutes" rows="4" placeholder="Record key discussions, decisions, and outcomes..."></textarea>
                </div>
                <button type="submit" class="btn-submit">Record Meeting</button>
            </form>
        </div>
        
        <!-- Meeting History -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-file-lines section-icon"></i> Meeting History</div>
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Attendance</th>
                            <th>Savings (K)</th>
                            <th>Loans Disbursed (K)</th>
                            <th>Loans Repaid (K)</th>
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
                                <td><?php echo $meeting['savings_collected'] ? 'K ' . number_format($meeting['savings_collected'], 2) : '-'; ?></td>
                                <td><?php echo $meeting['loans_disbursed'] ? 'K ' . number_format($meeting['loans_disbursed'], 2) : '-'; ?></td>
                                <td><?php echo $meeting['loans_repaid'] ? 'K ' . number_format($meeting['loans_repaid'], 2) : '-'; ?></td>
                                <td>
                                    <button onclick="viewMinutes('<?php echo htmlspecialchars($meeting['minutes'] ?? 'No minutes recorded'); ?>')" style="padding: 5px 10px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer;">View Minutes</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No meetings recorded yet</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        function viewMinutes(minutes) {
            alert('Meeting Minutes:\n\n' + minutes);
        }
    </script>
</body>
</html>
