<?php require_once 'includes/init.php'; $user = requireLogin();
$user_id = $user['id'];
$role = $user['role'];
$group_id = intval($user['group_id'] ?? 0);
$user_status = $user['status'] ?? 'active';

// Get dashboard statistics based on role
$stats = [];

if ($role == 'admin') {
    // System admin stats
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM savings_groups");
    $stats['total_groups'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'member'");
    $stats['total_members'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM savings_contributions");
    $stats['total_savings'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(principal_amount), 0) as total FROM loans WHERE status != 'repaid'");
    $stats['active_loans'] = $stmt->fetch()['total'];

    // Get PAR (Portfolio at Risk)
    $stmt = $pdo->query("SELECT COALESCE(SUM(balance), 0) as total_bal FROM loans WHERE status = 'disbursed'");
    $total_balance = $stmt->fetch()['total_bal'];
    $stmt = $pdo->query("SELECT COALESCE(SUM(balance), 0) as overdue_bal FROM loans WHERE status = 'disbursed' AND CURRENT_DATE > " . sqlDateAdd($pdo, 'disbursement_date', 'repayment_period', 'MONTH'));
    $overdue_balance = $stmt->fetch()['overdue_bal'];
    $stats['par_rate'] = $total_balance > 0 ? ($overdue_balance / $total_balance) * 100 : 0;

} elseif ($role == 'group_admin' && $group_id) {
    // Group admin stats
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE group_id = ? AND role = 'member'");
    $stmt->execute([$group_id]);
    $stats['total_members'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM savings_contributions WHERE group_id = ?");
    $stmt->execute([$group_id]);
    $stats['total_savings'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM loans WHERE group_id = ? AND status != 'repaid'");
    $stmt->execute([$group_id]);
    $stats['active_loans'] = $stmt->fetch()['total'];
    
    // Get group info
    $stmt = $pdo->prepare("SELECT id, group_name, invitation_code, interest_rate FROM savings_groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();

    // Auto-backfill invitation_code if missing
    if ($group && empty($group['invitation_code'])) {
        do {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM savings_groups WHERE invitation_code = ?");
            $stmt->execute([$code]);
        } while ($stmt->fetch()['cnt'] > 0);
        $stmt = $pdo->prepare("UPDATE savings_groups SET invitation_code = ? WHERE id = ?");
        $stmt->execute([$code, $group_id]);
        $group['invitation_code'] = $code;
    }

    // Get PAR for group
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(balance), 0) as total_bal FROM loans WHERE group_id = ? AND status = 'disbursed'");
    $stmt->execute([$group_id]);
    $total_balance = $stmt->fetch()['total_bal'];
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(balance), 0) as overdue_bal FROM loans WHERE group_id = ? AND status = 'disbursed' AND CURRENT_DATE > " . sqlDateAdd($pdo, 'disbursement_date', 'repayment_period', 'MONTH'));
    $stmt->execute([$group_id]);
    $overdue_balance = $stmt->fetch()['overdue_bal'];
    $stats['par_rate'] = $total_balance > 0 ? ($overdue_balance / $total_balance) * 100 : 0;
} elseif ($role == 'loan_officer' && $group_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM loans WHERE group_id = ? AND status = 'pending'");
    $stmt->execute([$group_id]);
    $stats['pending_loans'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(balance), 0) as total FROM loans WHERE group_id = ? AND status != 'repaid'");
    $stmt->execute([$group_id]);
    $stats['active_loans'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM loan_repayments lr JOIN loans l ON lr.loan_id = l.id WHERE l.group_id = ?");
    $stmt->execute([$group_id]);
    $stats['total_repaid'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM loans WHERE group_id = ? AND status = 'disbursed' AND CURRENT_DATE > " . sqlDateAdd($pdo, 'disbursement_date', 'repayment_period', 'MONTH'));
    $stmt->execute([$group_id]);
    $stats['overdue_loans'] = $stmt->fetch()['total'];
} elseif ($role == 'member' && $group_id) {
    // Member stats
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM savings_contributions WHERE member_id = ? AND group_id = ?");
    $stmt->execute([$user_id, $group_id]);
    $stats['my_savings'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(principal_amount), 0) as total FROM loans WHERE member_id = ? AND group_id = ? AND status != 'repaid'");
    $stmt->execute([$user_id, $group_id]);
    $stats['my_loans'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(balance), 0) as total FROM loans WHERE member_id = ? AND group_id = ? AND status != 'repaid'");
    $stmt->execute([$user_id, $group_id]);
    $stats['loan_balance'] = $stmt->fetch()['total'];
    
    // Get group info
    $stmt = $pdo->prepare("SELECT group_name, invitation_code FROM savings_groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $member_group = $stmt->fetch();

    // Auto-backfill invitation_code if missing
    if ($member_group && empty($member_group['invitation_code'])) {
        do {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM savings_groups WHERE invitation_code = ?");
            $stmt->execute([$code]);
        } while ($stmt->fetch()['cnt'] > 0);
        $stmt = $pdo->prepare("UPDATE savings_groups SET invitation_code = ? WHERE id = ?");
        $stmt->execute([$code, $group_id]);
        $member_group['invitation_code'] = $code;
    }
} elseif ($role == 'member') {
    $stats['my_savings'] = 0;
    $stats['my_loans'] = 0;
    $stats['loan_balance'] = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - RSGMS</title>
    <link rel="stylesheet" href="assets/css/icons.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <style>
        .welcome h2 {
            color: #2c3e50;
            font-size: 1.3rem;
        }
        
        .welcome p {
            color: #7f8c8d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    <?php include 'config/shared_navbar.php'; ?>
    
    <div class="main-content">
        <div class="latency-banner">
            <div class="latency-chip"><span></span>LIVE</div>
            <div class="latency-ticker">
                <div class="latency-track">
                    <span>Welcome to the Rural Savings Group Management System • Empowering rural communities with savings tracking, loan management, reporting and member support • Thank you for using RSGMS.</span>
                    <span>Welcome to the Rural Savings Group Management System • Empowering rural communities with savings tracking, loan management, reporting and member support • Thank you for using RSGMS.</span>
                </div>
            </div>
        </div>
        <div class="top-bar">
            <div class="welcome">
                <h2><?php
                    $hour = (int)date('G');
                    if ($hour < 12) echo 'Good morning';
                    elseif ($hour < 17) echo 'Good afternoon';
                    else echo 'Good evening';
                ?>, <?php echo htmlspecialchars($user['full_name']); ?>!</h2>
                <p><?php
                    $greetings = [
                        'Keep saving, keep thriving.',
                        'Every contribution brings you closer.',
                        'Small steps lead to big goals.',
                        'Your future self will thank you.',
                        'Community grows when everyone saves.',
                        'Financial freedom starts here.',
                    ];
                    echo $greetings[array_rand($greetings)];
                ?></p>
            </div>
            <div class="user-info">
                <span><?php echo htmlspecialchars($user['username']); ?></span>
                <span style="font-size:0.8rem;color:#64748b;"><?php echo ucfirst(str_replace('_', ' ', $role)); ?></span>
            </div>
        </div>
        
        <div class="stats-grid">
            <?php if ($user_status === 'pending'): ?>
                <?php if (empty($member_group) && $group_id): ?>
                    <?php $stmt = $pdo->prepare("SELECT group_name FROM savings_groups WHERE id = ?"); $stmt->execute([$group_id]); $member_group = $stmt->fetch(); ?>
                <?php endif; ?>
                <div class="stat-card" style="--accent: #d97706; grid-column: 1 / -1;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker"><?php echo htmlspecialchars($member_group['group_name'] ?? 'Membership'); ?></div>
                            <h3>Pending Approval</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                    </div>
                    <div class="stat-number" style="font-size: 1rem; color: #92400e;">Your request to join <strong><?php echo htmlspecialchars($member_group['group_name'] ?? 'the group'); ?></strong> is awaiting admin approval.</div>
                    <div class="stat-footnote">Once approved, you'll have access to savings, loans, and group features.</div>
                </div>
            <?php elseif ($role == 'admin'): ?>
                <div class="stat-card" style="--accent: #1d4ed8;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">System scope</div>
                            <h3>Total Groups</h3>
                        </div>
                        <div class="stat-icon"><span class="emoji-icon">🏢</span></div>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['total_groups']); ?></div>
                    <div class="stat-footnote">Registered savings groups currently active in the system.</div>
                </div>
                <div class="stat-card" style="--accent: #2563eb;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">People tracked</div>
                            <h3>Total Members</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['total_members']); ?></div>
                    <div class="stat-footnote">Members with profiles linked to any group.</div>
                </div>
                <div class="stat-card" style="--accent: #0f766e;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">Cash position</div>
                            <h3>Total Savings</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                    </div>
                    <div class="stat-number">K <?php echo number_format($stats['total_savings'], 2); ?></div>
                    <div class="stat-footnote">Cumulative savings recorded across the platform.</div>
                </div>
                <div class="stat-card" style="--accent: #7c3aed;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">Credit exposure</div>
                            <h3>Active Loans</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-chart-column"></i></div>
                    </div>
                    <div class="stat-number">K <?php echo number_format($stats['active_loans'], 2); ?></div>
                    <div class="stat-footnote">Outstanding principal on loans not yet repaid.</div>
                </div>
                <div class="stat-card" style="--accent: <?php echo $stats['par_rate'] > 10 ? '#dc2626' : ($stats['par_rate'] > 0 ? '#d97706' : '#16a34a'); ?>;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">Risk signal</div>
                            <h3>Portfolio at Risk (PAR)</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    </div>
                    <div class="stat-number" style="color: <?php echo $stats['par_rate'] > 10 ? '#dc2626' : ($stats['par_rate'] > 0 ? '#d97706' : '#16a34a'); ?>;"><?php echo number_format($stats['par_rate'], 1); ?>%</div>
                    <div class="stat-footnote">Lower is better; this highlights overdue balances against current loan balance.</div>
                </div>
            <?php elseif ($role == 'group_admin'): ?>
                <div class="stat-card" style="--accent: #2563eb;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">Current group</div>
                            <h3><?php echo htmlspecialchars($group['group_name'] ?? 'N/A'); ?></h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['total_members']); ?> Members</div>
                    <div class="stat-footnote">Members registered under this group.</div>
                </div>
                <div class="stat-card" style="--accent: #0f766e;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">Cash position</div>
                            <h3>Total Savings</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                    </div>
                    <div class="stat-number">K <?php echo number_format($stats['total_savings'], 2); ?></div>
                    <div class="stat-footnote">Savings contributed by members in this group.</div>
                </div>
                <div class="stat-card" style="--accent: #7c3aed;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">Credit exposure</div>
                            <h3>Active Loans</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-chart-column"></i></div>
                    </div>
                    <div class="stat-number">K <?php echo number_format($stats['active_loans'], 2); ?></div>
                    <div class="stat-footnote">Outstanding principal still open for repayment.</div>
                </div>
                <div class="stat-card" style="--accent: #ca8a04;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">Loan settings</div>
                            <h3>Interest Rate</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-chart-line"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $group['interest_rate'] ?? '10'; ?>%</div>
                    <div class="stat-footnote">Configured interest applied to new loans.</div>
                </div>
                <div class="stat-card" style="--accent: <?php echo $stats['par_rate'] > 10 ? '#dc2626' : ($stats['par_rate'] > 0 ? '#d97706' : '#16a34a'); ?>;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">Risk signal</div>
                            <h3>Portfolio at Risk (PAR)</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    </div>
                    <div class="stat-number" style="color: <?php echo $stats['par_rate'] > 10 ? '#dc2626' : ($stats['par_rate'] > 0 ? '#d97706' : '#16a34a'); ?>;"><?php echo number_format($stats['par_rate'], 1); ?>%</div>
                    <div class="stat-footnote">Portfolio balance at risk from overdue loans.</div>
                </div>
                <div class="stat-card" style="--accent: #1d4ed8;grid-column:1/-1;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">Share with members</div>
                            <h3>Invitation Code</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-key"></i></div>
                    </div>
                    <div class="stat-number">
                        <span class="invite-code"><?php echo htmlspecialchars($group['invitation_code'] ?? '—'); ?></span>
                        <button class="copy-btn" data-copy="<?php echo htmlspecialchars($group['invitation_code'] ?? ''); ?>" style="margin-left:12px;"> <i class="fa-regular fa-copy"></i> Copy</button>
                    </div>
                    <div class="stat-footnote">New members enter this code after signing up to request membership in your group.</div>
                </div>
            <?php elseif ($role == 'loan_officer'): ?>
                <div class="stat-card" style="--accent: #7c3aed;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">Awaiting review</div>
                            <h3>Pending Loans</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['pending_loans']); ?></div>
                    <div class="stat-footnote">Loan applications awaiting approval decision.</div>
                </div>
                <div class="stat-card" style="--accent: #0f766e;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">Credit exposure</div>
                            <h3>Active Loans</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                    </div>
                    <div class="stat-number">K <?php echo number_format($stats['active_loans'], 2); ?></div>
                    <div class="stat-footnote">Outstanding principal across all active loans.</div>
                </div>
                <div class="stat-card" style="--accent: #2563eb;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">Repayments</div>
                            <h3>Total Repaid</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                    </div>
                    <div class="stat-number">K <?php echo number_format($stats['total_repaid'], 2); ?></div>
                    <div class="stat-footnote">Cumulative repayments received across all loans.</div>
                </div>
                <div class="stat-card" style="--accent: #dc2626;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">At risk</div>
                            <h3>Overdue Loans</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['overdue_loans']); ?></div>
                    <div class="stat-footnote">Loans past their scheduled repayment date.</div>
                </div>
            <?php elseif ($role == 'member' && empty($group_id)): ?>
                <div class="stat-card" style="--accent: #2563eb; grid-column: 1 / -1;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">Welcome</div>
                            <h3>You're not in a savings group yet</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-hand"></i></div>
                    </div>
                    <div class="stat-number" style="font-size: 1rem;">
                        Join an existing group with an invitation code, or create a new group and become its administrator.
                    </div>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:4px;">
                        <a href="join_group.php" class="btn btn-primary" style="text-decoration:none;padding:12px 24px;display:inline-flex;align-items:center;gap:8px;"><i class="fa-solid fa-key"></i> Join a savings group</a>
                        <a href="register.php" class="btn btn-primary" style="text-decoration:none;padding:12px 24px;display:inline-flex;align-items:center;gap:8px;background:var(--clay-light);"><i class="fa-solid fa-plus"></i> Register a new group</a>
                    </div>
                </div>
            <?php elseif ($role == 'member' && $group_id): ?>
                <div class="stat-card" style="--accent: #2563eb;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">My group</div>
                            <h3><?php echo htmlspecialchars($member_group['group_name'] ?? 'N/A'); ?></h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                    </div>
                    <div class="stat-number" style="font-size: 1rem;">Group savings and loans summary</div>
                    <div class="stat-footnote">You are a member of this savings group.</div>
                </div>
                <div class="stat-card" style="--accent: #0f766e;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">Personal savings</div>
                            <h3>My Savings</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                    </div>
                    <div class="stat-number">K <?php echo number_format($stats['my_savings'], 2); ?></div>
                    <div class="stat-footnote">Your total savings contributions in the group.</div>
                </div>
                <div class="stat-card" style="--accent: #7c3aed;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">Borrowing</div>
                            <h3>My Loans</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-chart-column"></i></div>
                    </div>
                    <div class="stat-number">K <?php echo number_format($stats['my_loans'], 2); ?></div>
                    <div class="stat-footnote">Your current loan principal across active loans.</div>
                </div>
                <div class="stat-card" style="--accent: #dc2626;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">Outstanding</div>
                            <h3>Loan Balance</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-credit-card"></i></div>
                    </div>
                    <div class="stat-number">K <?php echo number_format($stats['loan_balance'], 2); ?></div>
                    <div class="stat-footnote">What remains to be repaid right now.</div>
                </div>
                <div class="stat-card" style="--accent: #1d4ed8;grid-column:1/-1;">
                    <div class="stat-card-head">
                        <div class="stat-info">
                            <div class="stat-kicker">Invite others</div>
                            <h3>Group Invitation Code</h3>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-key"></i></div>
                    </div>
                    <div class="stat-number">
                        <span class="invite-code"><?php echo htmlspecialchars($member_group['invitation_code'] ?? '—'); ?></span>
                        <button class="copy-btn" data-copy="<?php echo htmlspecialchars($member_group['invitation_code'] ?? ''); ?>" style="margin-left:12px;"> <i class="fa-regular fa-copy"></i> Copy</button>
                    </div>
                    <div class="stat-footnote">Share this code with friends and family so they can join your group. An admin will need to approve them after they sign up.</div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($role == 'member' && $group_id && $user_status === 'active'): ?>
        <?php
        $stmt = $pdo->prepare("SELECT * FROM meetings WHERE group_id = ? ORDER BY meeting_date DESC LIMIT 5");
        $stmt->execute([$group_id]);
        $recent_meetings = $stmt->fetchAll();
        ?>
        <?php if (count($recent_meetings) > 0): ?>
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-calendar-days section-icon"></i> Recent Meetings</div>
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
                        <?php foreach ($recent_meetings as $meeting): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($meeting['meeting_date'])); ?></td>
                            <td><?php echo ucfirst($meeting['meeting_type'] ?? 'Regular'); ?></td>
                            <td><?php echo $meeting['attendance_count'] ?? '-'; ?></td>
                            <td>
                                <button class="btn-minutes" onclick="viewMinutes(<?php echo $meeting['id']; ?>)">View Minutes</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 16px; text-align: right;">
                <a href="meeting_minutes.php" style="color: var(--clay); text-decoration: none; font-size: 0.88rem;">
                    View All Minutes <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($role == 'admin'): ?>
        <?php
        // Auto-backfill invitation_code for any groups missing it
        $stmt = $pdo->query("SELECT id, invitation_code FROM savings_groups WHERE invitation_code IS NULL OR invitation_code = ''");
        while ($row = $stmt->fetch()) {
            do {
                $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $chk = $pdo->prepare("SELECT COUNT(*) as cnt FROM savings_groups WHERE invitation_code = ?");
                $chk->execute([$code]);
            } while ($chk->fetch()['cnt'] > 0);
            $upd = $pdo->prepare("UPDATE savings_groups SET invitation_code = ? WHERE id = ?");
            $upd->execute([$code, $row['id']]);
        }
        $stmt = $pdo->query("SELECT id, group_name, invitation_code, group_code FROM savings_groups ORDER BY group_name");
        $all_groups = $stmt->fetchAll();
        ?>
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-key section-icon"></i> Groups &amp; Invitation Codes</div>
            <div class="data-table">
                <table>
                    <thead>
                        <tr><th>Group</th><th>Invitation Code</th><th>Share</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_groups as $g): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($g['group_name']); ?></td>
                            <td>
                                <span class="group-code" style="font-family:var(--font-mono);letter-spacing:0.02em"><?php echo htmlspecialchars($g['invitation_code']); ?></span>
                                <button class="copy-btn" data-copy="<?php echo htmlspecialchars($g['invitation_code']); ?>" style="margin-left:8px;"> <i class="fa-regular fa-copy"></i></button>
                            </td>
                            <td style="font-size:0.8rem;color:#6b7280;">Give this 6-digit code to new members to join</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($role == 'admin' || $role == 'group_admin' || $role == 'loan_officer'): ?>
        <div class="section">
            <div class="section-title">🧭 Dashboard Modes</div>
            <div class="mode-grid">
                <?php if ($role != 'loan_officer'): ?>
                <div class="mode-card">
                    <div class="mode-icon"><i class="fa-solid fa-users"></i></div>
                    <div class="mode-title">Member Management</div>
                    <div class="mode-desc">Track attendance, group membership, and member participation from one place.</div>
                </div>
                <?php endif; ?>
                <?php if ($role != 'loan_officer'): ?>
                <div class="mode-card">
                    <div class="mode-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                    <div class="mode-title">Savings Tracking</div>
                    <div class="mode-desc">Monitor group savings growth, contributions, and balance trends in real time.</div>
                </div>
                <?php endif; ?>
                <div class="mode-card">
                    <div class="mode-icon"><i class="fa-solid fa-chart-line"></i></div>
                    <div class="mode-title">Loan Monitoring</div>
                    <div class="mode-desc">Review active loans, repayment performance, and loan portfolio health quickly.</div>
                </div>
                <div class="mode-card">
                    <div class="mode-icon"><i class="fa-solid fa-file-lines"></i></div>
                    <div class="mode-title">Reports & Alerts</div>
                    <div class="mode-desc">Generate reports, send notifications, and schedule meetings with one click.</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Transactions -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-file-lines section-icon"></i> Recent Transactions</div>
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $limit = 10;
                        if ($role == 'admin') {
                            $stmt = $pdo->prepare("SELECT t.*, u.full_name as member_name FROM transactions t LEFT JOIN users u ON t.member_id = u.id ORDER BY t.created_at DESC LIMIT " . intval($limit));
                            $stmt->execute();
                        } elseif (($role == 'group_admin' || $role == 'loan_officer') && $group_id) {
                            $stmt = $pdo->prepare("SELECT t.*, u.full_name as member_name FROM transactions t LEFT JOIN users u ON t.member_id = u.id WHERE t.group_id = ? ORDER BY t.created_at DESC LIMIT " . intval($limit));
                            $stmt->execute([$group_id]);
                        } else {
                            $stmt = $pdo->prepare("SELECT * FROM transactions WHERE member_id = ? ORDER BY created_at DESC LIMIT " . intval($limit));
                            $stmt->execute([$user_id]);
                        }
                        $transactions = $stmt->fetchAll();
                        
                        if (count($transactions) > 0):
                            foreach ($transactions as $trans):
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($trans['created_at'])); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $trans['transaction_type'])); ?></td>
                            <td>K <?php echo number_format($trans['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($trans['description'] ?? '-'); ?></td>
                            <td><span class="badge badge-success">Completed</span></td>
                        </tr>
                        <?php
                            endforeach;
                        else:
                        ?>
                        <tr>
                            <td colspan="5"><div class="empty-state"><div class="empty-state-icon"><i class="fa-regular fa-receipt"></i></div><div class="empty-state-title">No transactions yet</div><div class="empty-state-text">Transactions will appear here once you start saving or taking loans.</div></div></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <?php if ($role == 'group_admin'): ?>
        <div class="section">
            <div class="section-title">⚡ Quick Actions</div>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="reports.php" class="btn-small btn-primary" style="padding: 10px 20px; text-decoration: none; border-radius: 8px;"><i class="fa-solid fa-file-lines nav-icon"></i> View Reports</a>
                <a href="meetings.php" class="btn-small btn-primary" style="padding: 10px 20px; text-decoration: none; border-radius: 8px;"><i class="fa-solid fa-calendar-days nav-icon"></i> Manage Meetings</a>
            </div>
        </div>
        <?php elseif ($role == 'loan_officer'): ?>
        <div class="section">
            <div class="section-title">⚡ Quick Actions</div>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="loans.php" class="btn-small btn-primary" style="padding: 10px 20px; text-decoration: none; border-radius: 8px;"><i class="fa-solid fa-chart-line nav-icon"></i> Manage Loans</a>
                <a href="reports.php" class="btn-small btn-primary" style="padding: 10px 20px; text-decoration: none; border-radius: 8px;"><i class="fa-solid fa-file-lines nav-icon"></i> View Reports</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
<script>
const recentMeetings = <?php echo isset($recent_meetings) ? json_encode($recent_meetings) : '[]'; ?>;

function viewMinutes(meetingId) {
    const meeting = recentMeetings.find(m => m.id == meetingId);
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

document.querySelectorAll('.copy-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var code = this.getAttribute('data-copy');
        if (!code) return;
        navigator.clipboard.writeText(code).then(function() {
            var orig = btn.innerHTML;
            btn.innerHTML = ' <i class="fa-regular fa-check"></i> Copied!';
            setTimeout(function() { btn.innerHTML = orig; }, 1800);
        }).catch(function() {
            var orig = btn.innerHTML;
            btn.innerHTML = ' <i class="fa-regular fa-xmark"></i> Failed';
            setTimeout(function() { btn.innerHTML = orig; }, 1800);
        });
    });
});
</script>

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

    <script src="assets/js/loading.js"></script>
</body>
</html>
