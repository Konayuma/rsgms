<?php require_once 'includes/init.php'; $user = requireRole(['admin', 'group_admin']);
$user_id = $user['id'];
$role = $user['role'];
$group_id = intval($user['group_id'] ?? 0);
$message = '';
$error = '';

// Get savings history
if ($role == 'admin') {
    $stmt = $pdo->prepare("SELECT sc.*, u.full_name as member_name, sg.group_name FROM savings_contributions sc JOIN users u ON sc.member_id = u.id LEFT JOIN savings_groups sg ON sc.group_id = sg.id ORDER BY sc.created_at DESC LIMIT 50");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT sc.*, u.full_name as member_name FROM savings_contributions sc JOIN users u ON sc.member_id = u.id WHERE sc.group_id = ? ORDER BY sc.created_at DESC LIMIT 50");
    $stmt->execute([$group_id]);
}
$savings = $stmt->fetchAll();

// Get savings summary by member
if ($role == 'admin') {
    $stmt = $pdo->prepare("SELECT u.id, u.full_name, COALESCE(SUM(sc.amount), 0) as total_savings FROM users u LEFT JOIN savings_contributions sc ON u.id = sc.member_id WHERE u.role = 'member' GROUP BY u.id ORDER BY total_savings DESC");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT u.id, u.full_name, COALESCE(SUM(sc.amount), 0) as total_savings FROM users u LEFT JOIN savings_contributions sc ON u.id = sc.member_id AND sc.group_id = ? WHERE u.group_id = ? AND u.role = 'member' GROUP BY u.id ORDER BY total_savings DESC");
    $stmt->execute([$group_id, $group_id]);
}
$savings_summary = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savings Management - RSGMS</title>
    <link rel="stylesheet" href="assets/css/icons.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <style>
        .total-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #27ae60;
        }
        .chart-container {
            position: relative;
            margin: 20px 0;
            min-height: 300px;
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    <?php include 'config/shared_navbar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <h2>Savings Management</h2>
        </div>
        
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Savings Summary by Member -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-chart-column section-icon"></i> Savings Summary by Member</div>
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Member Name</th>
                            <th>Total Savings (K)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($savings_summary) > 0): ?>
                            <?php foreach ($savings_summary as $index => $summary): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($summary['full_name']); ?></td>
                                <td class="total-amount">K <?php echo number_format($summary['total_savings'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center;">No savings records found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Member Savings Chart -->
        <?php if (count($savings_summary) > 0): ?>
        <?php
        $chart_labels = array_map(fn($s) => htmlspecialchars($s['full_name']), $savings_summary);
        $chart_values = array_map(fn($s) => (float)$s['total_savings'], $savings_summary);
        ?>
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-chart-bar section-icon"></i> Member Savings Overview</div>
            <div class="chart-container">
                <canvas id="memberSavingsChart"></canvas>
                <script>
                var ctx2 = document.getElementById('memberSavingsChart').getContext('2d');
                new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($chart_labels); ?>,
                        datasets: [{
                            label: 'Total Savings (K)',
                            data: <?php echo json_encode($chart_values); ?>,
                            backgroundColor: 'rgba(52, 152, 219, 0.7)',
                            borderColor: 'rgba(52, 152, 219, 1)',
                            borderWidth: 1,
                            borderRadius: 4,
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        return 'K ' + ctx.parsed.x.toFixed(2);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return 'K ' + value.toFixed(2);
                                    }
                                },
                                grid: { color: 'rgba(0,0,0,0.06)' }
                            },
                            y: {
                                grid: { display: false }
                            }
                        }
                    }
                });
                </script>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Savings History -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-file-lines section-icon"></i> Recent Savings History</div>
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Member</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($savings) > 0): ?>
                            <?php foreach ($savings as $saving): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($saving['contribution_date'])); ?></td>
                                <td><?php echo htmlspecialchars($saving['member_name']); ?></td>
                                <td class="total-amount">K <?php echo number_format($saving['amount'], 2); ?></td>
                                <td><?php echo ucfirst($saving['payment_method'] ?? 'Cash'); ?></td>
                                <td><?php echo $saving['recorded_by'] == $user_id ? 'Me' : 'System'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No savings history found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="assets/js/chart.umd.min.js"></script>
</body>
</html>
