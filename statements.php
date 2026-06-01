<?php require_once 'includes/init.php'; $user = requireRole(['member']);
$user_id = $user['id'];
$group_id = $user['group_id'];

// Get statement data
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Get savings transactions
$stmt = $pdo->prepare("SELECT sc.*, 'savings' as type FROM savings_contributions sc WHERE sc.member_id = ? AND sc.group_id = ? AND sc.contribution_date BETWEEN ? AND ? ORDER BY sc.contribution_date");
$stmt->execute([$user_id, $group_id, $start_date, $end_date]);
$savings_transactions = $stmt->fetchAll();

// Get loan transactions
$stmt = $pdo->prepare("SELECT lr.*, l.principal_amount, 'loan_repayment' as type FROM loan_repayments lr JOIN loans l ON lr.loan_id = l.id WHERE l.member_id = ? AND l.group_id = ? AND lr.payment_date BETWEEN ? AND ? ORDER BY lr.payment_date");
$stmt->execute([$user_id, $group_id, $start_date, $end_date]);
$loan_transactions = $stmt->fetchAll();

// Combine all transactions
$all_transactions = [];
foreach ($savings_transactions as $trans) {
    $all_transactions[] = [
        'date' => $trans['contribution_date'],
        'type' => 'Savings Contribution',
        'description' => 'Regular savings contribution',
        'amount' => $trans['amount'],
        'balance' => 0 // Will calculate running balance
    ];
}

foreach ($loan_transactions as $trans) {
    $all_transactions[] = [
        'date' => $trans['payment_date'],
        'type' => 'Loan Repayment',
        'description' => 'Loan repayment - Principal: K' . number_format($trans['principal_paid'], 2) . ', Interest: K' . number_format($trans['interest_paid'], 2),
        'amount' => -$trans['amount'], // Negative for repayments
        'balance' => 0
    ];
}

// Sort transactions by date
usort($all_transactions, function($a, $b) {
    return strtotime($a['date']) - strtotime($b['date']);
});

// Calculate running balance
$running_balance = 0;
foreach ($all_transactions as &$trans) {
    $running_balance += $trans['amount'];
    $trans['balance'] = $running_balance;
}

// Get summary statistics
$total_savings = 0;
$total_repayments = 0;
foreach ($all_transactions as $trans) {
    if ($trans['amount'] > 0) {
        $total_savings += $trans['amount'];
    } else {
        $total_repayments += abs($trans['amount']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Statements - RSGMS</title>
    <link rel="stylesheet" href="assets/css/icons.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <style>
        .statement-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: #ffffff;
            color: #1f2937;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
        }
        
        .date-filter {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .date-filter label {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .date-filter input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .btn-filter {
            background: #3498db;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .btn-print {
            background: #27ae60;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-left: 10px;
        }
        
        .transaction-amount {
            font-weight: bold;
        }
        
        .credit {
            color: #27ae60;
        }
        
        .debit {
            color: #e74c3c;
        }
        
        .balance {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .no-print {
            display: block;
        }
        
        @media print {
            .sidebar, .top-bar, .date-filter, .no-print {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
            }
            .section {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
        }
        
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    <?php include 'config/shared_navbar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <h2><i class="fa-solid fa-file-invoice-dollar" style="margin-right:8px;"></i> Financial Statements</h2>
            <div>
                <button onclick="window.print()" class="btn-print"><i class="fa-solid fa-print"></i> Print Statement</button>
            </div>
        </div>
        
        <!-- Statement Header -->
        <div class="statement-header">
            <h1>Rural Savings Group Management System</h1>
            <h2>Financial Statement</h2>
            <p>Member: <?php echo htmlspecialchars($user['full_name']); ?> | Period: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></p>
        </div>
        
        <!-- Date Filter -->
        <div class="date-filter no-print">
            <form method="GET" action="">
                <label>From:</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                
                <label>To:</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                
                <button type="submit" class="btn-filter">Filter</button>
            </form>
        </div>
        
        <!-- Statement Summary -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">K <?php echo number_format($total_savings, 2); ?></div>
                <div class="stat-label">Total Savings</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">K <?php echo number_format($total_repayments, 2); ?></div>
                <div class="stat-label">Total Repayments</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">K <?php echo number_format($total_repayments + $total_savings, 2); ?></div>
                <div class="stat-label">Total Volume</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">K <?php echo number_format($running_balance, 2); ?></div>
                <div class="stat-label">Net Position</div>
            </div>
        </div>
        
        <!-- Transaction History -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-file-lines section-icon"></i> Transaction History</div>
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($all_transactions) > 0): ?>
                            <?php foreach ($all_transactions as $transaction): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($transaction['date'])); ?></td>
                                <td><?php echo htmlspecialchars($transaction['type']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                <td class="transaction-amount <?php echo $transaction['amount'] > 0 ? 'credit' : 'debit'; ?>">
                                    <?php echo $transaction['amount'] > 0 ? '+' : ''; ?>K <?php echo number_format(abs($transaction['amount']), 2); ?>
                                </td>
                                <td class="balance">K <?php echo number_format($transaction['balance'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No transactions found for the selected period</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Statement Footer -->
        <div class="section">
            <div style="text-align: center; color: #666; padding: 20px;">
                <p>This statement was generated on <?php echo date('l, F j, Y \a\t g:i A'); ?></p>
                <p>Rural Savings Group Management System - Zambia University College of Technology</p>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh on date change for better UX
        document.querySelectorAll('input[type="date"]').forEach(input => {
            input.addEventListener('change', function() {
                // Optional: auto-submit form on date change
                // this.closest('form').submit();
            });
        });
    </script>
</body>
</html>
