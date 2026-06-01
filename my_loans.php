<?php
// File: my_loans.php
// Member's personal loans view
require_once 'includes/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'member') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$group_id = $_SESSION['group_id'];

$message = ''; $error = '';

// Handle repayment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'make_repayment') {
    $loan_id = intval($_POST['loan_id']);
    $amount = floatval($_POST['amount']);
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'];
    $transaction_ref = trim($_POST['transaction_ref'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ? AND member_id = ?");
    $stmt->execute([$loan_id, $user_id]);
    $loan = $stmt->fetch();

    if (!$loan || $amount <= 0) {
        $error = "Invalid loan or amount.";
    } elseif ($amount > $loan['balance']) {
        $error = "Repayment amount (K " . number_format($amount, 2) . ") exceeds outstanding balance (K " . number_format($loan['balance'], 2) . ").";
    } else {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_savings FROM savings_contributions WHERE member_id = ? AND group_id = ?");
        $stmt->execute([$user_id, $group_id]);
        $total_savings = $stmt->fetch()['total_savings'];

        if ($payment_method === 'savings_wallet' && $amount > $total_savings) {
            $error = "Insufficient savings balance. You have K " . number_format($total_savings, 2) . " in savings but attempted to deduct K " . number_format($amount, 2) . ".";
        } else {
            try {
                $pdo->beginTransaction();

                $interest_paid = $amount * 0.3;
                $principal_paid = $amount - $interest_paid;
                $new_balance = $loan['balance'] - $principal_paid;
                if ($new_balance < 0) $new_balance = 0;

                $due_date = $loan['disbursement_date']
                    ? date('Y-m-d', strtotime("+{$loan['repayment_period']} months", strtotime($loan['disbursement_date'])))
                    : date('Y-m-d');
                $is_late = strtotime($payment_date) > strtotime($due_date);

                $stmt = $pdo->prepare("INSERT INTO loan_repayments (loan_id, amount, principal_paid, interest_paid, penalty_amount, payment_date, due_date, is_late, recorded_by) VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?)");
                $stmt->execute([$loan_id, $amount, $principal_paid, $interest_paid, $payment_date, $due_date, $is_late ? 1 : 0, $user_id]);

                $status = $new_balance <= 0 ? 'repaid' : 'disbursed';
                $stmt = $pdo->prepare("UPDATE loans SET balance = ?, amount_paid = amount_paid + ?, status = ? WHERE id = ?");
                $stmt->execute([$new_balance, $amount, $status, $loan_id]);

                $stmt = $pdo->prepare("INSERT INTO transactions (group_id, transaction_type, amount, member_id, loan_id, description, created_by) VALUES (?, 'loan_repayment', ?, ?, ?, ?, ?)");
                $stmt->execute([$group_id, $amount, $user_id, $loan_id, "Loan repayment of K{$amount}", $user_id]);

                if ($payment_method === 'savings_wallet') {
                    $stmt = $pdo->prepare("INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, transaction_ref, recorded_by, is_self_service) VALUES (?, ?, ?, ?, 'wallet_deduction', ?, ?, 1)");
                    $txref = 'loan-repay-' . $loan_id . '-' . time();
                    $stmt->execute([$user_id, $group_id, -$amount, $payment_date, $txref, $user_id]);

                    $stmt = $pdo->prepare("INSERT INTO transactions (group_id, transaction_type, amount, member_id, loan_id, description, reference, created_by) VALUES (?, 'withdrawal', ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$group_id, $amount, $user_id, $loan_id, "Savings withdrawal for loan repayment K{$amount}", $txref, $user_id]);

                    $stmt = $pdo->prepare("UPDATE savings_groups SET total_savings = total_savings - ? WHERE id = ?");
                    $stmt->execute([$amount, $group_id]);
                }

                $pdo->commit();
                $message = "Repayment of K " . number_format($amount, 2) . " recorded successfully!";
                if ($payment_method === 'savings_wallet') {
                    $message .= " Deducted from your savings wallet.";
                }
                if ($new_balance <= 0) {
                    setFlash('success', 'Loan fully repaid! You are debt-free!', ['celebrate' => true]);
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error recording repayment: " . $e->getMessage();
            }
        }
    }
}

// Get savings balance for wallet display
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_savings FROM savings_contributions WHERE member_id = ? AND group_id = ?");
$stmt->execute([$user_id, $group_id]);
$wallet_balance = $stmt->fetch()['total_savings'];

// Get loan data
$stmt = $pdo->prepare("SELECT * FROM loans WHERE member_id = ? AND group_id = ? ORDER BY application_date DESC");
$stmt->execute([$user_id, $group_id]);
$loans = $stmt->fetchAll();

// Get loan statistics
$total_loans = 0;
$total_paid = 0;
$outstanding_balance = 0;
$active_loans = 0;

foreach ($loans as $loan) {
    $total_loans += $loan['principal_amount'];
    $total_paid += $loan['amount_paid'];
    if ($loan['status'] != 'repaid') {
        $outstanding_balance += $loan['balance'];
        $active_loans++;
    }
}

// Get upcoming payments (simplified - next payment due)
$upcoming_payment = null;
$stmt = $pdo->prepare("SELECT l.*, DATE_ADD(l.application_date, INTERVAL l.repayment_period MONTH) as due_date FROM loans l WHERE l.member_id = ? AND l.group_id = ? AND l.status = 'disbursed' ORDER BY due_date ASC LIMIT 1");
$stmt->execute([$user_id, $group_id]);
$next_payment = $stmt->fetch();

if ($next_payment) {
    $upcoming_payment = [
        'amount' => $next_payment['balance'] > 0 ? min($next_payment['balance'], 100) : 0, // Simplified monthly payment
        'due_date' => $next_payment['due_date']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Loans - RSGMS</title>
    <link rel="stylesheet" href="assets/css/icons.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/toast.css">
    <style>
        .upcoming-payment {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            color: #1f2937;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 25px;
        }
        
        .payment-amount {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-approved {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-disbursed {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-repaid {
            background: #28a745;
            color: white;
        }
        
        .loan-amount {
            font-weight: bold;
            color: #e74c3c;
        }
        
        .paid-amount {
            font-weight: bold;
            color: #27ae60;
        }
        
        .balance-amount {
            font-weight: bold;
            color: #f39c12;
        }
        
        .btn-repay { background: #27ae60; color: white; }
        .btn-apply { background: #27ae60; color: white; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-size: 0.95rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-apply:hover { background: #219a52; }
        .detail-row {
            display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0;
        }
        .detail-label { color: #64748b; font-size: 0.9rem; }
        .detail-value { font-weight: 600; color: #1f2937; }
        .schedule-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.85rem; }
        .schedule-table th { background: #f8f9fa; padding: 8px; text-align: left; font-weight: 600; }
        .schedule-table td { padding: 8px; border-bottom: 1px solid #ecf0f1; }
        .btn-submit-repay { width: 100%; padding: 12px; background: #27ae60; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .btn-submit-repay:hover { background: #219a52; }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    <?php include 'config/shared_navbar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <h2><i class="fa-solid fa-hand-holding-dollar" style="margin-right:8px;"></i> My Loans</h2>
            <div style="display:flex; gap:10px; align-items:center;">
                <a href="new_loan.php" class="btn-apply"><i class="fa-solid fa-plus"></i> Apply for Loan</a>
            </div>
        </div>
        
        <div id="flash-data" data-flash='<?php echo json_encode(flashMessages()); ?>' style="display:none"></div>
        
        <?php if ($message): ?>
            <div class="message" style="background:#d4edda;color:#155724;padding:12px;border-radius:8px;margin-bottom:20px;"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error" style="background:#f8d7da;color:#721c24;padding:12px;border-radius:8px;margin-bottom:20px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Loans Summary -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">K <?php echo number_format($total_loans, 2); ?></div>
                <div class="stat-label">Total Loans</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">K <?php echo number_format($total_paid, 2); ?></div>
                <div class="stat-label">Total Paid</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">K <?php echo number_format($outstanding_balance, 2); ?></div>
                <div class="stat-label">Outstanding Balance</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $active_loans; ?></div>
                <div class="stat-label">Active Loans</div>
            </div>
        </div>
        
        <!-- Upcoming Payment -->
        <?php if ($upcoming_payment && $upcoming_payment['amount'] > 0): ?>
        <div class="upcoming-payment">
            <div class="payment-amount"><i class="fa-solid fa-credit-card section-icon"></i> Next Payment: K <?php echo number_format($upcoming_payment['amount'], 2); ?></div>
            <div>Due Date: <?php echo date('l, F j, Y', strtotime($upcoming_payment['due_date'])); ?></div>
        </div>
        <?php endif; ?>
        
        <!-- Loan History -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-file-lines section-icon"></i> My Loan History</div>
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Application Date</th>
                            <th>Principal</th>
                            <th>Total Payable</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($loans) > 0): ?>
                            <?php foreach ($loans as $loan): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($loan['application_date'])); ?></td>
                                <td class="loan-amount">K <?php echo number_format($loan['principal_amount'], 2); ?></td>
                                <td>K <?php echo number_format($loan['total_payable'], 2); ?></td>
                                <td class="paid-amount">K <?php echo number_format($loan['amount_paid'], 2); ?></td>
                                <td class="balance-amount">K <?php echo number_format($loan['balance'], 2); ?></td>
                                <td>
                                    <?php
                                    $badgeClass = '';
                                    switch($loan['status']) {
                                        case 'pending': $badgeClass = 'badge-pending'; break;
                                        case 'approved': $badgeClass = 'badge-approved'; break;
                                        case 'disbursed': $badgeClass = 'badge-disbursed'; break;
                                        case 'repaid': $badgeClass = 'badge-repaid'; break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($loan['status']); ?></span>
                                </td>
                                <td>
                                    <button class="btn-sm btn-view" onclick="viewLoanDetails(<?php echo $loan['id']; ?>)">View</button>
                                    <?php if ($loan['status'] == 'disbursed' && $loan['balance'] > 0): ?>
                                    <button class="btn-sm btn-repay" onclick="openRepayModal(<?php echo $loan['id']; ?>)">Repay</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px 12px;"><i class="fa-regular fa-seedling" style="font-size:1.3rem;margin-right:6px;"></i> No loans yet — start by applying for one above!</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Repayment History -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-sack-dollar section-icon"></i> Repayment History</div>
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Payment Date</th>
                            <th>Amount Paid</th>
                            <th>Principal</th>
                            <th>Interest</th>
                            <th>Penalty</th>
                            <th>Balance After</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->prepare("SELECT lr.*, l.principal_amount FROM loan_repayments lr JOIN loans l ON lr.loan_id = l.id WHERE l.member_id = ? AND l.group_id = ? ORDER BY lr.payment_date DESC");
                        $stmt->execute([$user_id, $group_id]);
                        $repayments = $stmt->fetchAll();
                        
                        if (count($repayments) > 0):
                            foreach ($repayments as $repayment):
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($repayment['payment_date'])); ?></td>
                            <td class="paid-amount">K <?php echo number_format($repayment['amount'], 2); ?></td>
                            <td>K <?php echo number_format($repayment['principal_paid'], 2); ?></td>
                            <td>K <?php echo number_format($repayment['interest_paid'], 2); ?></td>
                            <td>K <?php echo number_format($repayment['penalty_amount'], 2); ?></td>
                            <td><?php 
                                // Calculate balance after payment (simplified)
                                $loan_balance = 0;
                                foreach ($loans as $loan) {
                                    if ($loan['id'] == $repayment['loan_id']) {
                                        $loan_balance = $loan['balance'];
                                        break;
                                    }
                                }
                                echo 'K ' . number_format($loan_balance, 2);
                            ?></td>
                        </tr>
                        <?php
                            endforeach;
                        else:
                        ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No repayment history found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Repayment Schedule for Active Loans -->
        <?php
        $active_loans = array_filter($loans, fn($l) => $l['status'] == 'disbursed' || $l['status'] == 'approved');
        if (count($active_loans) > 0):
        ?>
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-calendar-days section-icon"></i> Repayment Schedules</div>
            <?php foreach ($active_loans as $loan):
                $installments = $loan['repayment_period'];
                $monthly_payment = $loan['total_payable'] / max($installments, 1);
                $start_date = $loan['disbursement_date'] ?: $loan['approval_date'] ?: $loan['application_date'];
            ?>
            <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 10px; border: 1px solid #e5e7eb;">
                <h4 style="color: #2c3e50; margin-bottom: 10px;">Loan #<?php echo $loan['id']; ?> — K <?php echo number_format($loan['principal_amount'], 2); ?></h4>
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Due Date</th>
                            <th>Amount</th>
                            <th>Paid</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 1; $i <= $installments; $i++):
                            $due = date('Y-m-d', strtotime("+{$i} months", strtotime($start_date)));
                            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as p FROM loan_repayments WHERE loan_id = ? AND due_date <= ?");
                            $stmt->execute([$loan['id'], $due]);
                            $paid_to_date = $stmt->fetch()['p'];
                            $is_fully_paid = $paid_to_date >= $loan['total_payable'] * ($i / $installments);
                        ?>
                        <tr>
                            <td><?php echo $i; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($due)); ?></td>
                            <td>K <?php echo number_format($monthly_payment, 2); ?></td>
                            <td>K <?php echo number_format(min($paid_to_date, $monthly_payment), 2); ?></td>
                            <td><span class="badge <?php echo $is_fully_paid ? 'badge-repaid' : 'badge-pending'; ?>"><?php echo $is_fully_paid ? 'Paid' : 'Pending'; ?></span></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- View Details Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fa-solid fa-file-lines section-icon"></i> Loan Details</h3>
                <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div id="loanDetailsContent">
                <div class="detail-row"><span class="detail-label">Loan ID</span><span class="detail-value" id="det_id">—</span></div>
                <div class="detail-row"><span class="detail-label">Principal</span><span class="detail-value" id="det_principal">—</span></div>
                <div class="detail-row"><span class="detail-label">Interest Rate</span><span class="detail-value" id="det_rate">—</span></div>
                <div class="detail-row"><span class="detail-label">Total Payable</span><span class="detail-value" id="det_total">—</span></div>
                <div class="detail-row"><span class="detail-label">Amount Paid</span><span class="detail-value" id="det_paid">—</span></div>
                <div class="detail-row"><span class="detail-label">Outstanding Balance</span><span class="detail-value" id="det_balance">—</span></div>
                <div class="detail-row"><span class="detail-label">Applied</span><span class="detail-value" id="det_applied">—</span></div>
                <div class="detail-row"><span class="detail-label">Approved</span><span class="detail-value" id="det_approved">—</span></div>
                <div class="detail-row"><span class="detail-label">Disbursed</span><span class="detail-value" id="det_disbursed">—</span></div>
                <div class="detail-row"><span class="detail-label">Repayment Period</span><span class="detail-value" id="det_period">—</span></div>
                <div class="detail-row"><span class="detail-label">Frequency</span><span class="detail-value" id="det_freq">—</span></div>
                <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value" id="det_status">—</span></div>
            </div>
        </div>
    </div>

    <!-- Repayment Modal -->
    <div id="repayModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fa-solid fa-hand-holding-dollar section-icon"></i> Make Repayment</h3>
                <button class="modal-close" onclick="closeModal('repayModal')">&times;</button>
            </div>
            <form method="POST" action="" class="repay-form">
                <input type="hidden" name="action" value="make_repayment">
                <input type="hidden" name="loan_id" id="repay_loan_id">
                <div style="padding:10px;background:#f8f9fa;border-radius:8px;margin-bottom:15px;">
                    <span style="color:#64748b;">Repaying loan: </span>
                    <strong id="repay_loan_ref" style="color:#2c3e50;">Loan #—</strong>
                    <span style="float:right;color:#64748b;">Balance: <strong id="repay_balance" style="color:#f39c12;">K 0.00</strong></span>
                </div>
                <div style="padding:10px;background:#e8f5e9;border-radius:8px;margin-bottom:15px;display:flex;justify-content:space-between;">
                    <span style="color:#2e7d32;">Savings Wallet:</span>
                    <strong id="repay_wallet" style="color:#2e7d32;">K 0.00</strong>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Amount (K) *</label>
                        <input type="number" name="amount" id="repay_amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Payment Date *</label>
                        <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Payment Source *</label>
                        <select name="payment_method" id="repay_method" required onchange="toggleWalletFields()">
                            <option value="cash">Cash</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="savings_wallet">Savings Wallet</option>
                        </select>
                    </div>
                    <div class="form-group" id="ref_group">
                        <label>Reference</label>
                        <input type="text" name="transaction_ref" id="repay_ref" placeholder="Optional">
                    </div>
                </div>
                <div id="wallet_notice" style="display:none;padding:10px;background:#fff3cd;border-radius:8px;margin-bottom:15px;font-size:0.85rem;color:#856404;">
                    <i class="fa-solid fa-info-circle"></i> This amount will be deducted directly from your savings wallet balance.
                </div>
                <button type="submit" class="btn-submit-repay" id="repay_submit">Submit Repayment</button>
            </form>
        </div>
    </div>

    <script>
        // Loan data from PHP
        const loansData = <?php echo json_encode($loans); ?>;

        function viewLoanDetails(loanId) {
            const loan = loansData.find(l => l.id == loanId);
            if (!loan) return;
            document.getElementById('det_id').textContent = '#' + loan.id;
            document.getElementById('det_principal').textContent = 'K ' + parseFloat(loan.principal_amount).toFixed(2);
            document.getElementById('det_rate').textContent = loan.interest_rate + '%';
            document.getElementById('det_total').textContent = 'K ' + parseFloat(loan.total_payable).toFixed(2);
            document.getElementById('det_paid').textContent = 'K ' + parseFloat(loan.amount_paid).toFixed(2);
            document.getElementById('det_balance').textContent = 'K ' + parseFloat(loan.balance).toFixed(2);
            document.getElementById('det_applied').textContent = loan.application_date ? new Date(loan.application_date).toLocaleDateString('en-GB') : '—';
            document.getElementById('det_approved').textContent = loan.approval_date ? new Date(loan.approval_date).toLocaleDateString('en-GB') : '—';
            document.getElementById('det_disbursed').textContent = loan.disbursement_date ? new Date(loan.disbursement_date).toLocaleDateString('en-GB') : '—';
            document.getElementById('det_period').textContent = loan.repayment_period + ' month(s)';
            document.getElementById('det_freq').textContent = loan.repayment_frequency || 'Monthly';
            document.getElementById('det_status').textContent = loan.status.charAt(0).toUpperCase() + loan.status.slice(1);
            document.getElementById('viewModal').style.display = 'flex';
        }

        const walletBalance = <?php echo $wallet_balance; ?>;

        function openRepayModal(loanId) {
            const loan = loansData.find(l => l.id == loanId);
            if (!loan) return;
            document.getElementById('repay_loan_id').value = loanId;
            document.getElementById('repay_loan_ref').textContent = 'Loan #' + loan.id + ' — K' + parseFloat(loan.principal_amount).toFixed(2);
            document.getElementById('repay_balance').textContent = 'K ' + parseFloat(loan.balance).toFixed(2);
            document.getElementById('repay_wallet').textContent = 'K ' + walletBalance.toFixed(2);
            document.getElementById('repay_amount').max = loan.balance;
            document.getElementById('repay_method').value = 'cash';
            document.getElementById('repay_ref').value = '';
            toggleWalletFields();
            document.getElementById('repayModal').style.display = 'flex';
        }

        function toggleWalletFields() {
            const method = document.getElementById('repay_method').value;
            const walletNotice = document.getElementById('wallet_notice');
            const refGroup = document.getElementById('ref_group');
            const amountInput = document.getElementById('repay_amount');
            if (method === 'savings_wallet') {
                walletNotice.style.display = 'block';
                refGroup.style.display = 'none';
                amountInput.max = Math.min(
                    parseFloat(document.getElementById('repay_balance').textContent.replace(/[^0-9.]/g, '')),
                    walletBalance
                );
            } else {
                walletNotice.style.display = 'none';
                refGroup.style.display = 'block';
                amountInput.max = parseFloat(document.getElementById('repay_balance').textContent.replace(/[^0-9.]/g, ''));
            }
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
    <link rel="stylesheet" href="assets/css/toast.css">
    <script src="assets/js/toast.js"></script>
</body>
</html>
