<?php require_once 'includes/init.php'; $user = requireRole(['admin', 'group_admin', 'loan_officer']);
$user_id = $user['id'];
$role = $user['role'];
$group_id = $user['group_id'];

// Get report data based on role
$reports = [];

if ($role == 'admin') {
    // System-wide reports
    $stmt = $pdo->query("SELECT COUNT(*) as total_groups FROM savings_groups");
    $reports['total_groups'] = $stmt->fetch()['total_groups'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total_members FROM users WHERE role = 'member'");
    $reports['total_members'] = $stmt->fetch()['total_members'];
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total_savings FROM savings_contributions");
    $reports['total_savings'] = $stmt->fetch()['total_savings'];
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(principal_amount), 0) as active_loans FROM loans WHERE status != 'repaid'");
    $reports['active_loans'] = $stmt->fetch()['active_loans'];
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) as total_repaid FROM loans WHERE status = 'repaid'");
    $reports['total_repaid'] = $stmt->fetch()['total_repaid'];
} elseif (($role == 'group_admin' || $role == 'loan_officer') && $group_id) {
    // Group-specific reports
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_members FROM users WHERE group_id = ? AND role = 'member'");
    $stmt->execute([$group_id]);
    $reports['total_members'] = $stmt->fetch()['total_members'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_savings FROM savings_contributions WHERE group_id = ?");
    $stmt->execute([$group_id]);
    $reports['total_savings'] = $stmt->fetch()['total_savings'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(principal_amount), 0) as active_loans FROM loans WHERE group_id = ? AND status != 'repaid'");
    $stmt->execute([$group_id]);
    $reports['active_loans'] = $stmt->fetch()['active_loans'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) as total_repaid FROM loans WHERE group_id = ? AND status = 'repaid'");
    $stmt->execute([$group_id]);
    $reports['total_repaid'] = $stmt->fetch()['total_repaid'];
    
    // Get group info
    $stmt = $pdo->prepare("SELECT * FROM savings_groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();
}

// Fetch report rows for export and display
if ($role == 'admin') {
    $stmt = $pdo->prepare("SELECT u.full_name, COALESCE(SUM(sc.amount), 0) as total_savings, MAX(sc.contribution_date) as last_contribution, COUNT(sc.id) as contribution_count FROM users u LEFT JOIN savings_contributions sc ON u.id = sc.member_id WHERE u.role = 'member' GROUP BY u.id ORDER BY total_savings DESC");
    $stmt->execute();
    $savings_report = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT l.*, u.full_name FROM loans l JOIN users u ON l.member_id = u.id ORDER BY l.created_at DESC");
    $stmt->execute();
    $loans_report = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT u.full_name, COALESCE(SUM(sc.amount), 0) as total_savings, MAX(sc.contribution_date) as last_contribution, COUNT(sc.id) as contribution_count FROM users u LEFT JOIN savings_contributions sc ON u.id = sc.member_id AND sc.group_id = ? WHERE u.group_id = ? AND u.role = 'member' GROUP BY u.id ORDER BY total_savings DESC");
    $stmt->execute([$group_id, $group_id]);
    $savings_report = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT l.*, u.full_name FROM loans l JOIN users u ON l.member_id = u.id WHERE l.group_id = ? ORDER BY l.created_at DESC");
    $stmt->execute([$group_id]);
    $loans_report = $stmt->fetchAll();
}

if (isset($_GET['export'])) {
    $exportType = $_GET['export'] === 'excel' ? 'excel' : 'csv';
    $filename = 'rsgms_reports_' . date('Ymd_His') . ($exportType === 'excel' ? '.xls' : '.csv');
    $contentType = $exportType === 'excel' ? 'application/vnd.ms-excel' : 'text/csv';
    header('Content-Type: ' . $contentType . '; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    $summary = [
        ['Metric', 'Value'],
        ['Total Groups', $role == 'admin' ? $reports['total_groups'] : '1'],
        ['Total Members', $reports['total_members']],
        ['Total Savings', number_format($reports['total_savings'], 2)],
        ['Active Loans', number_format($reports['active_loans'], 2)],
        ['Total Repaid', number_format($reports['total_repaid'], 2)],
        ['Repayment Rate', $reports['total_savings'] > 0 ? number_format(($reports['total_repaid'] / $reports['total_savings']) * 100, 1) . '%' : '0%'],
    ];

    foreach ($summary as $row) {
        fputcsv($output, $row);
    }

    fputcsv($output, []);
    fputcsv($output, ['Member Savings Report']);
    fputcsv($output, ['Member Name', 'Total Savings', 'Last Contribution', 'Contribution Count']);
    foreach ($savings_report as $reportRow) {
        fputcsv($output, [
            $reportRow['full_name'],
            number_format($reportRow['total_savings'], 2),
            $reportRow['last_contribution'] ? date('d/m/Y', strtotime($reportRow['last_contribution'])) : 'Never',
            $reportRow['contribution_count'],
        ]);
    }

    fputcsv($output, []);
    fputcsv($output, ['Loan Portfolio Report']);
    fputcsv($output, ['Member Name', 'Loan Amount', 'Status', 'Application Date', 'Disbursement Date']);
    foreach ($loans_report as $loanRow) {
        fputcsv($output, [
            $loanRow['full_name'],
            number_format($loanRow['principal_amount'], 2),
            ucfirst($loanRow['status']),
            date('d/m/Y', strtotime($loanRow['application_date'])),
            $loanRow['disbursement_date'] ? date('d/m/Y', strtotime($loanRow['disbursement_date'])) : '-',
        ]);
    }

    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - RSGMS</title>
    <link rel="stylesheet" href="assets/css/icons.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <style>
        .btn-export {
            background: #374151;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            display: inline-block;
            margin-right: 10px;
        }
        .section p {
            margin-bottom: 12px;
            line-height: 1.4;
        }
        .export-actions {
            margin-top: 12px;
        }
        @media (max-width: 480px) {
            .export-actions { display: flex; flex-direction: column; }
            .btn-export { display: block; width: 100%; margin-right: 0; margin-bottom: 8px; }
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    <?php include 'config/shared_navbar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <h2>Financial Reports</h2>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card" style="--accent: #1d4ed8;">
                <div class="stat-card-head">
                    <div class="stat-info">
                        <div class="stat-kicker">System scope</div>
                        <h3><?php echo $role == 'admin' ? 'Total Groups' : 'Group'; ?></h3>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-building"></i></div>
                </div>
                <div class="stat-number"><?php echo $role == 'admin' ? number_format($reports['total_groups']) : '1'; ?></div>
                <div class="stat-footnote"><?php echo $role == 'admin' ? 'Registered savings groups currently active in the system.' : 'The group currently selected for this report view.'; ?></div>
            </div>
            <div class="stat-card" style="--accent: #2563eb;">
                <div class="stat-card-head">
                    <div class="stat-info">
                        <div class="stat-kicker">People tracked</div>
                        <h3>Total Members</h3>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($reports['total_members']); ?></div>
                <div class="stat-footnote">Members with profiles linked to the current scope.</div>
            </div>
            <div class="stat-card" style="--accent: #0f766e;">
                <div class="stat-card-head">
                    <div class="stat-info">
                        <div class="stat-kicker">Cash position</div>
                        <h3>Total Savings</h3>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                </div>
                <div class="stat-number">K <?php echo number_format($reports['total_savings'], 2); ?></div>
                <div class="stat-footnote">Cumulative savings recorded across the report scope.</div>
            </div>
            <div class="stat-card" style="--accent: #7c3aed;">
                <div class="stat-card-head">
                    <div class="stat-info">
                        <div class="stat-kicker">Credit exposure</div>
                        <h3>Active Loans</h3>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-chart-column"></i></div>
                </div>
                <div class="stat-number">K <?php echo number_format($reports['active_loans'], 2); ?></div>
                <div class="stat-footnote">Outstanding principal on loans not yet repaid.</div>
            </div>
            <div class="stat-card" style="--accent: #16a34a;">
                <div class="stat-card-head">
                    <div class="stat-info">
                        <div class="stat-kicker">Repayment progress</div>
                        <h3>Total Repaid</h3>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                </div>
                <div class="stat-number">K <?php echo number_format($reports['total_repaid'], 2); ?></div>
                <div class="stat-footnote">Principal and repayments already settled in the period.</div>
            </div>
            <div class="stat-card" style="--accent: #d97706;">
                <div class="stat-card-head">
                    <div class="stat-info">
                        <div class="stat-kicker">Portfolio health</div>
                        <h3>Repayment Rate</h3>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-percent"></i></div>
                </div>
                <div class="stat-number"><?php echo $reports['total_savings'] > 0 ? number_format(($reports['total_repaid'] / $reports['total_savings']) * 100, 1) : '0'; ?>%</div>
                <div class="stat-footnote">Recovered value compared with total savings tracked.</div>
            </div>
        </div>
        
        <!-- Member Savings Report -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-sack-dollar section-icon"></i> Member Savings Report</div>
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Member Name</th>
                            <th>Total Savings</th>
                            <th>Last Contribution</th>
                            <th>Contribution Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($role == 'admin') {
                            $stmt = $pdo->prepare("SELECT u.full_name, COALESCE(SUM(sc.amount), 0) as total_savings, MAX(sc.contribution_date) as last_contribution, COUNT(sc.id) as contribution_count FROM users u LEFT JOIN savings_contributions sc ON u.id = sc.member_id WHERE u.role = 'member' GROUP BY u.id ORDER BY total_savings DESC");
                            $stmt->execute();
                        } else {
                            $stmt = $pdo->prepare("SELECT u.full_name, COALESCE(SUM(sc.amount), 0) as total_savings, MAX(sc.contribution_date) as last_contribution, COUNT(sc.id) as contribution_count FROM users u LEFT JOIN savings_contributions sc ON u.id = sc.member_id AND sc.group_id = ? WHERE u.group_id = ? AND u.role = 'member' GROUP BY u.id ORDER BY total_savings DESC");
                            $stmt->execute([$group_id, $group_id]);
                        }
                        $savings_report = $stmt->fetchAll();
                        
                        if (count($savings_report) > 0):
                            foreach ($savings_report as $report):
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($report['full_name']); ?></td>
                            <td>K <?php echo number_format($report['total_savings'], 2); ?></td>
                            <td><?php echo $report['last_contribution'] ? date('d/m/Y', strtotime($report['last_contribution'])) : 'Never'; ?></td>
                            <td><?php echo $report['contribution_count']; ?></td>
                        </tr>
                        <?php
                            endforeach;
                        else:
                        ?>
                        <tr><td colspan="4"><div class="empty-state"><div class="empty-state-icon"><i class="fa-solid fa-sack-dollar"></i></div><div class="empty-state-title">No savings data</div><div class="empty-state-text">Members haven't recorded any savings contributions yet.</div></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Loan Portfolio Report -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-chart-line section-icon"></i> Loan Portfolio Report</div>
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Member Name</th>
                            <th>Loan Amount</th>
                            <th>Status</th>
                            <th>Application Date</th>
                            <th>Disbursement Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($role == 'admin') {
                            $stmt = $pdo->prepare("SELECT l.*, u.full_name FROM loans l JOIN users u ON l.member_id = u.id ORDER BY l.created_at DESC");
                            $stmt->execute();
                        } else {
                            $stmt = $pdo->prepare("SELECT l.*, u.full_name FROM loans l JOIN users u ON l.member_id = u.id WHERE l.group_id = ? ORDER BY l.created_at DESC");
                            $stmt->execute([$group_id]);
                        }
                        $loans_report = $stmt->fetchAll();
                        
                        if (count($loans_report) > 0):
                            foreach ($loans_report as $loan):
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($loan['full_name']); ?></td>
                            <td>K <?php echo number_format($loan['principal_amount'], 2); ?></td>
                            <td><?php echo ucfirst($loan['status']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($loan['application_date'])); ?></td>
                            <td><?php echo $loan['disbursement_date'] ? date('d/m/Y', strtotime($loan['disbursement_date'])) : '-'; ?></td>
                        </tr>
                        <?php
                            endforeach;
                        else:
                        ?>
                        <tr><td colspan="5"><div class="empty-state"><div class="empty-state-icon"><i class="fa-regular fa-chart-line"></i></div><div class="empty-state-title">No loan data</div><div class="empty-state-text">No loans have been issued yet in this scope.</div></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Export Options -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-file-export section-icon"></i> Export Reports</div>
            <p>You can export the current reports as CSV or Excel-compatible file.</p>
            <div class="export-actions">
                <a href="reports.php?export=csv" class="btn-export">Export to CSV</a>
                <a href="reports.php?export=excel" class="btn-export">Export to Excel</a>
            </div>
        </div>
    </div>
    <script src="assets/js/loading.js"></script>
</body>
</html>
