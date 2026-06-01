<?php require_once 'includes/init.php'; $user = requireRole(['member']);
$user_id = $user['id'];
$group_id = $user['group_id'];

// Get savings data
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_savings FROM savings_contributions WHERE member_id = ? AND group_id = ?");
$stmt->execute([$user_id, $group_id]);
$total_savings = $stmt->fetch()['total_savings'];

// Get savings history
$stmt = $pdo->prepare("SELECT * FROM savings_contributions WHERE member_id = ? AND group_id = ? ORDER BY contribution_date DESC");
$stmt->execute([$user_id, $group_id]);
$savings_history = $stmt->fetchAll();

// Get monthly savings summary
$stmt = $pdo->prepare("SELECT DATE_FORMAT(contribution_date, '%Y-%m') as month, SUM(amount) as monthly_total FROM savings_contributions WHERE member_id = ? AND group_id = ? GROUP BY DATE_FORMAT(contribution_date, '%Y-%m') ORDER BY month DESC LIMIT 12");
$stmt->execute([$user_id, $group_id]);
$monthly_summary = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT contribution_amount FROM savings_groups WHERE id = ?");
$stmt->execute([$group_id]);
$group_contribution = $stmt->fetch()['contribution_amount'] ?? 0;

// Savings collection window functions
require_once 'config/savings_helpers.php';

$message = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount']);
    $contribution_date = $_POST['contribution_date'];
    $payment_method = $_POST['payment_method'];
    $transaction_ref = trim($_POST['transaction_ref'] ?? '');
    try {
        $pdo->beginTransaction();
        $windowCheck = blockSavingsOutsideWindow($pdo, $user_id, $user_id, $group_id, $contribution_date);
        if (empty($windowCheck['allowed'])) {
            $pdo->rollBack(); $error = $windowCheck['message'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, transaction_ref, recorded_by, is_self_service) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$user_id, $group_id, $amount, $contribution_date, $payment_method, $transaction_ref, $user_id]);
            $stmt = $pdo->prepare("INSERT INTO transactions (group_id, transaction_type, amount, member_id, description, reference, created_by) VALUES (?, 'savings', ?, ?, ?, ?, ?)");
            $stmt->execute([$group_id, $amount, $user_id, "Self-service savings of K{$amount}", $transaction_ref, $user_id]);
            $stmt = $pdo->prepare("UPDATE savings_groups SET total_savings = total_savings + ? WHERE id = ?");
            $stmt->execute([$amount, $group_id]);
            $pdo->commit();
            $message = "Savings recorded successfully!";
            $new_total = $total_savings + $amount;
            $milestones = [1000, 5000, 10000, 25000, 50000, 100000];
            foreach ($milestones as $m) {
                if ($total_savings < $m && $new_total >= $m) {
                    setFlash('success', 'Congratulations! You\'ve reached K ' . number_format($m) . ' in total savings!', ['celebrate' => true]);
                    break;
                }
            }
            $_POST = [];
        }
    } catch (PDOException $e) {
        $pdo->rollBack(); $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Savings - RSGMS</title>
    <link rel="stylesheet" href="assets/css/icons.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/toast.css">
    <style>
        .form-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
        }
        .form-card h3 {
            font-size: 1.1rem;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }
        .btn-save {
            background: #27ae60; color: white; padding: 12px 25px; border: none;
            border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; width: 100%;
        }
        .btn-save:hover { background: #219a52; }
        .contribution-amount {
            font-weight: bold;
            color: #27ae60;
        }
        
        .chart-container {
            position: relative;
            margin-top: 20px;
            min-height: 300px;
        }
        .chart-empty {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        .chart-empty i {
            font-size: 2rem;
            color: #ccc;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    <?php include 'config/shared_navbar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <h2><i class="fa-solid fa-piggy-bank" style="margin-right:8px;"></i> My Savings</h2>
        </div>
        
        <div id="flash-data" data-flash='<?php echo json_encode(flashMessages()); ?>' style="display:none"></div>
        
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Record Contribution -->
        <div class="form-card">
            <h3><i class="fa-solid fa-sack-dollar section-icon"></i> Record Savings Contribution</h3>
            <form method="POST" action="">
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
                            <option value="">-- Select --</option>
                            <option value="cash" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'cash') ? 'selected' : ''; ?>>Cash</option>
                            <option value="mobile_money" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'mobile_money') ? 'selected' : ''; ?>>Mobile Money</option>
                            <option value="bank_transfer" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'bank_transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="cheque" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'cheque') ? 'selected' : ''; ?>>Cheque</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reference</label>
                        <input type="text" name="transaction_ref" placeholder="Optional" value="<?php echo htmlspecialchars($_POST['transaction_ref'] ?? ''); ?>">
                    </div>
                </div>
                <button type="submit" class="btn-save">Record Savings</button>
            </form>
        </div>
        
        <!-- Savings Summary -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">K <?php echo number_format($total_savings, 2); ?></div>
                <div class="stat-label">Total Savings</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($savings_history); ?></div>
                <div class="stat-label">Total Contributions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">K <?php echo number_format($group_contribution, 2); ?></div>
                <div class="stat-label">Regular Contribution</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($monthly_summary); ?></div>
                <div class="stat-label">Active Months</div>
            </div>
        </div>
        
        <!-- Monthly Savings Chart -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-chart-column section-icon"></i> Monthly Savings Summary</div>
            <div class="chart-container">
                <?php if (count($monthly_summary) > 0): ?>
                    <?php
                    // Reverse to show chronological order (oldest first)
                    $chart_data = array_reverse($monthly_summary);
                    $labels = array_map(fn($m) => date('M Y', strtotime($m['month'] . '-01')), $chart_data);
                    $values = array_map(fn($m) => (float)$m['monthly_total'], $chart_data);
                    ?>
                    <canvas id="monthlySavingsChart"></canvas>
                    <script src="assets/js/chart.umd.min.js"></script>
                    <script>
                    var ctx = document.getElementById('monthlySavingsChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode($labels); ?>,
                            datasets: [{
                                label: 'Savings (K)',
                                data: <?php echo json_encode($values); ?>,
                                backgroundColor: 'rgba(39, 174, 96, 0.7)',
                                borderColor: 'rgba(39, 174, 96, 1)',
                                borderWidth: 1,
                                borderRadius: 4,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function(ctx) {
                                            return 'K ' + ctx.parsed.y.toFixed(2);
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return 'K ' + value.toFixed(2);
                                        }
                                    },
                                    grid: {
                                        color: 'rgba(0,0,0,0.06)'
                                    }
                                },
                                x: {
                                    grid: { display: false }
                                }
                            }
                        }
                    });
                    </script>
                <?php else: ?>
                    <div class="chart-empty">
                        <i class="fa-solid fa-chart-column"></i>
                        <p>No savings data yet. Start contributing to see your monthly summary!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Savings History -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-file-lines section-icon"></i> Savings Contribution History</div>
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($savings_history) > 0): ?>
                            <?php foreach ($savings_history as $contribution): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($contribution['contribution_date'])); ?></td>
                                <td class="contribution-amount">K <?php echo number_format($contribution['amount'], 2); ?></td>
                                <td><?php echo ucfirst($contribution['payment_method'] ?? 'Cash'); ?></td>
                                <td><?php echo htmlspecialchars($contribution['transaction_ref'] ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 30px 12px;"><i class="fa-solid fa-piggy-bank" style="font-size:1.3rem;margin-right:6px;"></i> Start your savings journey — every contribution counts!</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Savings Goals/Targets -->
        <div class="section">
            <div class="section-title">🎯 Savings Goals</div>
            <p>Track your progress towards savings goals. This feature can be enhanced with goal setting functionality.</p>
            <div style="margin-top: 20px;">
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
                    <h3 style="color: #2c3e50; margin-bottom: 10px;">Current Balance</h3>
                    <div style="font-size: 2rem; color: #27ae60; font-weight: bold;">K <?php echo number_format($total_savings, 2); ?></div>
                    <p style="color: #666; margin-top: 10px;">Keep up the good work! Regular savings build financial security.</p>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/toast.js"></script>
</body>
</html>
