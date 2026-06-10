<?php
require_once 'includes/init.php';

$user = requireRole(['admin', 'group_admin', 'loan_officer']);
$user_id = $user['id'];
$role = $user['role'];
$group_id = $user['group_id'];

// Get group settings
if ($group_id) {
    $stmt = $pdo->prepare("SELECT * FROM savings_groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();
} else {
    $group = ['interest_rate' => 10, 'penalty_rate' => 5];
}

// Get members for dropdown
if ($role == 'admin') {
    $stmt = $pdo->prepare("SELECT u.id, u.full_name, sg.group_name FROM users u LEFT JOIN savings_groups sg ON u.group_id = sg.id WHERE u.role = 'member' ORDER BY u.full_name");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE group_id = ? AND role = 'member' ORDER BY full_name");
    $stmt->execute([$group_id]);
}
$members = $stmt->fetchAll();

// Handle loan actions (loan_officer only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'approve_loan' && $role == 'loan_officer') {
        $loan_id = intval($_POST['loan_id']);
        $stmt = $pdo->prepare("SELECT status, member_id FROM loans WHERE id = ?");
        $stmt->execute([$loan_id]);
        $loan = $stmt->fetch();
        if (!$loan) {
            setFlash('error', 'Loan not found.');
        } elseif ($loan['status'] !== 'pending') {
            setFlash('error', 'Only pending loans can be approved.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE loans SET status = 'approved', approval_date = ?, approved_by = ? WHERE id = ?");
                $stmt->execute([date('Y-m-d'), $user_id, $loan_id]);
                notifyUser($loan['member_id'], 'Loan Approved', 'Your loan application has been approved.');
                setFlash('success', 'Loan approved successfully!', ['celebrate' => true]);
            } catch (PDOException $e) {
                setFlash('error', "Error approving loan: " . $e->getMessage());
            }
        }
    } elseif ($_POST['action'] === 'reject_loan' && $role == 'loan_officer') {
        $loan_id = intval($_POST['loan_id']);
        $stmt = $pdo->prepare("SELECT status, member_id FROM loans WHERE id = ?");
        $stmt->execute([$loan_id]);
        $loan = $stmt->fetch();
        if (!$loan) {
            setFlash('error', 'Loan not found.');
        } elseif ($loan['status'] !== 'pending') {
            setFlash('error', 'Only pending loans can be rejected.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE loans SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$loan_id]);
                notifyUser($loan['member_id'], 'Loan Rejected', 'Your loan application has been rejected.');
                setFlash('success', 'Loan rejected successfully.');
            } catch (PDOException $e) {
                setFlash('error', "Error rejecting loan: " . $e->getMessage());
            }
        }
    } elseif ($_POST['action'] === 'disburse_loan' && $role == 'loan_officer') {
        $loan_id = $_POST['loan_id'];
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE loans SET status = 'disbursed', disbursement_date = ? WHERE id = ?");
            $stmt->execute([date('Y-m-d'), $loan_id]);
            $stmt = $pdo->prepare("SELECT principal_amount, member_id, group_id FROM loans WHERE id = ?");
            $stmt->execute([$loan_id]);
            $loan = $stmt->fetch();
            $stmt = $pdo->prepare("UPDATE savings_groups SET total_loans = total_loans + ? WHERE id = ?");
            $stmt->execute([$loan['principal_amount'], $loan['group_id']]);
            $pdo->commit();
            notifyUser($loan['member_id'], 'Loan Disbursed', 'Your loan of K ' . number_format($loan['principal_amount'], 2) . ' has been disbursed.');
            setFlash('success', 'Loan of K ' . number_format($loan['principal_amount'], 2) . ' disbursed!', ['celebrate' => true]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('error', "Error disbursing loan: " . $e->getMessage());
        }
    } elseif ($_POST['action'] === 'record_repayment' && in_array($role, ['admin', 'group_admin', 'loan_officer'])) {
        $loan_id = intval($_POST['loan_id']);
        $amount = floatval($_POST['amount']);
        $payment_date = $_POST['payment_date'];
        $payment_method = $_POST['payment_method'];
        $transaction_ref = trim($_POST['transaction_ref'] ?? '');

        $stmt = $pdo->prepare("SELECT l.*, u.full_name as member_name FROM loans l JOIN users u ON l.member_id = u.id WHERE l.id = ?");
        $stmt->execute([$loan_id]);
        $loan = $stmt->fetch();

        if (!$loan || $amount <= 0) {
            setFlash('error', "Invalid loan or amount.");
        } elseif ($amount > $loan['balance']) {
            setFlash('error', "Repayment amount (K " . number_format($amount, 2) . ") exceeds outstanding balance (K " . number_format($loan['balance'], 2) . ").");
        } else {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_savings FROM savings_contributions WHERE member_id = ? AND group_id = ?");
            $stmt->execute([$loan['member_id'], $loan['group_id']]);
            $total_savings = $stmt->fetch()['total_savings'];

            if ($payment_method === 'savings_wallet' && $amount > $total_savings) {
                setFlash('error', "Insufficient savings balance. Member has K " . number_format($total_savings, 2) . " in savings but attempted to deduct K " . number_format($amount, 2) . ".");
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
                    $stmt->execute([$loan['group_id'], $amount, $loan['member_id'], $loan_id, "Loan repayment of K{$amount} (recorded by officer)", $user_id]);

                    if ($payment_method === 'savings_wallet') {
                        $stmt = $pdo->prepare("INSERT INTO savings_contributions (member_id, group_id, amount, contribution_date, payment_method, transaction_ref, recorded_by, is_self_service) VALUES (?, ?, ?, ?, 'wallet_deduction', ?, ?, 0)");
                        $txref = 'loan-repay-' . $loan_id . '-' . time();
                        $stmt->execute([$loan['member_id'], $loan['group_id'], -$amount, $payment_date, $txref, $user_id]);

                        $stmt = $pdo->prepare("INSERT INTO transactions (group_id, transaction_type, amount, member_id, loan_id, description, reference, created_by) VALUES (?, 'withdrawal', ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$loan['group_id'], $amount, $loan['member_id'], $loan_id, "Savings withdrawal for loan repayment K{$amount}", $txref, $user_id]);

                        $stmt = $pdo->prepare("UPDATE savings_groups SET total_savings = total_savings - ? WHERE id = ?");
                        $stmt->execute([$amount, $loan['group_id']]);
                    }

                    $pdo->commit();
                    notifyUser($loan['member_id'], 'Loan Repayment Recorded', "A repayment of K " . number_format($amount, 2) . " has been recorded on your loan by an officer." . ($payment_method === 'savings_wallet' ? " This was deducted from your savings wallet." : ""));
                    setFlash('success', 'Repayment of K ' . number_format($amount, 2) . ' recorded for ' . htmlspecialchars($loan['member_name']) . '!', $new_balance <= 0 ? ['celebrate' => true] : []);
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    setFlash('error', "Error recording repayment: " . $e->getMessage());
                }
            }
        }
    } elseif ($_POST['action'] === 'delete_loan' && ($role == 'admin' || $role == 'group_admin')) {
        $loan_id = $_POST['loan_id'];
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE loan_id = ?");
            $stmt->execute([$loan_id]);
            $stmt = $pdo->prepare("DELETE FROM loan_repayments WHERE loan_id = ?");
            $stmt->execute([$loan_id]);
            $stmt = $pdo->prepare("DELETE FROM loans WHERE id = ?");
            $stmt->execute([$loan_id]);
            $pdo->commit();
            setFlash('success', 'Loan deleted successfully!');
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('error', "Error deleting loan: " . $e->getMessage());
        }
    }
}

// Get all loans
if ($role == 'admin') {
    $stmt = $pdo->prepare("SELECT l.*, u.full_name as member_name, sg.group_name FROM loans l JOIN users u ON l.member_id = u.id LEFT JOIN savings_groups sg ON l.group_id = sg.id ORDER BY l.created_at DESC");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT l.*, u.full_name as member_name, sg.group_name FROM loans l JOIN users u ON l.member_id = u.id LEFT JOIN savings_groups sg ON l.group_id = sg.id WHERE l.group_id = ? ORDER BY l.created_at DESC");
    $stmt->execute([$group_id]);
}
$loans = $stmt->fetchAll();

// Get pending loans for approval
if (in_array($role, ['admin', 'group_admin', 'loan_officer'])) {
    if ($role == 'admin') {
        $stmt = $pdo->prepare("SELECT l.*, u.full_name as member_name, sg.group_name FROM loans l JOIN users u ON l.member_id = u.id LEFT JOIN savings_groups sg ON l.group_id = sg.id WHERE l.status = 'pending' ORDER BY l.created_at");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("SELECT l.*, u.full_name as member_name, sg.group_name FROM loans l JOIN users u ON l.member_id = u.id LEFT JOIN savings_groups sg ON l.group_id = sg.id WHERE l.group_id = ? AND l.status = 'pending' ORDER BY l.created_at");
        $stmt->execute([$group_id]);
    }
    $pending_loans = $stmt->fetchAll();
} else {
    $pending_loans = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Management - RSGMS</title>
    <link rel="stylesheet" href="assets/css/icons.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <style>
        .btn-approve, .btn-disburse {
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-approve {
            background: #3498db;
        }
        
        .btn-reject {
            background: #e74c3c;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-disburse {
            background: #e67e22;
        }
        
        .detail-row {
            display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0;
        }
        .detail-label { color: #64748b; font-size: 0.9rem; }
        .detail-value { font-weight: 600; color: #1f2937; }
        
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
        
        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        .btn-submit-repay { width: 100%; padding: 12px; background: #27ae60; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .btn-submit-repay:hover { background: #219a52; }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    <?php include 'config/shared_navbar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h2>Loan Management</h2>
        </div>
        
        <div id="flash-data" data-flash='<?php echo json_encode(flashMessages()); ?>' style="display:none"></div>
        
        <!-- Pending Approvals -->
        <?php if (count($pending_loans) > 0): ?>
        <div class="section">
            <div class="section-title">⏳ Pending Loan Approvals</div>
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Group</th>
                            <th>Principal</th>
                            <th>Interest Rate</th>
                            <th>Total Payable</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_loans as $loan): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($loan['member_name']); ?></td>
                            <td><?php echo htmlspecialchars($loan['group_name'] ?? 'N/A'); ?></td>
                            <td>K <?php echo number_format($loan['principal_amount'], 2); ?></td>
                            <td><?php echo $loan['interest_rate']; ?>%</td>
                            <td>K <?php echo number_format($loan['total_payable'], 2); ?></td>
                            <td>
                                <button class="btn-sm btn-view" onclick="viewLoanDetails(<?php echo $loan['id']; ?>)">View</button>
                                <?php if ($role == 'loan_officer'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="approve_loan">
                                    <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                    <button type="submit" class="btn-approve" style="padding: 5px 10px;">Approve</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="reject_loan">
                                    <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                    <button type="submit" class="btn-reject" style="padding: 5px 10px;" onclick="return confirm('Reject this loan application?')">Reject</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- All Loans List -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-file-lines section-icon"></i> All Loans</div>
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Member</th>
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
                                <td><?php echo htmlspecialchars($loan['member_name']); ?></td>
                                <td>K <?php echo number_format($loan['principal_amount'], 2); ?></td>
                                <td>K <?php echo number_format($loan['total_payable'], 2); ?></td>
                                <td>K <?php echo number_format($loan['amount_paid'], 2); ?></td>
                                <td>K <?php echo number_format($loan['balance'], 2); ?></td>
                                <td>
                                    <?php
                                    $badgeClass = '';
                                    switch($loan['status']) {
                                        case 'pending': $badgeClass = 'badge-pending'; break;
                                        case 'approved': $badgeClass = 'badge-approved'; break;
                                        case 'rejected': $badgeClass = 'badge-rejected'; break;
                                        case 'disbursed': $badgeClass = 'badge-disbursed'; break;
                                        case 'repaid': $badgeClass = 'badge-repaid'; break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($loan['status']); ?></span>
                                </td>
                                <td>
                                    <button class="btn-sm btn-view" onclick="viewLoanDetails(<?php echo $loan['id']; ?>)">View</button>
                                    <?php if ($loan['status'] == 'disbursed' && $loan['balance'] > 0): ?>
                                    <button class="btn-sm" style="background:#27ae60;color:white;padding:5px 10px;" onclick="openRepayModal(<?php echo $loan['id']; ?>)">Repay</button>
                                    <?php endif; ?>
                                    <?php if ($role == 'admin' || $role == 'group_admin'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this loan permanently?');">
                                        <input type="hidden" name="action" value="delete_loan">
                                        <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                        <button type="submit" class="btn-sm btn-view" style="background:#e74c3c;padding:5px 10px;">Delete</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7"><div class="empty-state"><div class="empty-state-icon"><i class="fa-regular fa-lightbulb"></i></div><div class="empty-state-title">No loans yet</div><div class="empty-state-text">Ready to help a member grow? Loans will appear here once members apply.</div></div></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- View Details Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Loan Details</h3>
                <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div id="loanDetailsContent">
                <div class="detail-row"><span class="detail-label">Loan ID</span><span class="detail-value" id="det_id">—</span></div>
                <div class="detail-row"><span class="detail-label">Member</span><span class="detail-value" id="det_member">—</span></div>
                <div class="detail-row"><span class="detail-label">Group</span><span class="detail-value" id="det_group">—</span></div>
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

    <!-- Repayment Modal (Officer) -->
    <div id="repayModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fa-solid fa-hand-holding-dollar section-icon"></i> Record Repayment</h3>
                <button class="modal-close" onclick="closeModal('repayModal')">&times;</button>
            </div>
            <form method="POST" action="" class="repay-form">
                <input type="hidden" name="action" value="record_repayment">
                <input type="hidden" name="loan_id" id="repay_loan_id">
                <div style="padding:10px;background:#f8f9fa;border-radius:8px;margin-bottom:15px;">
                    <span style="color:#64748b;">Member: </span>
                    <strong id="repay_member" style="color:#2c3e50;">—</strong>
                    <span style="float:right;color:#64748b;">Balance: <strong id="repay_balance" style="color:#f39c12;">K 0.00</strong></span>
                </div>
                <div style="padding:10px;background:#e8f5e9;border-radius:8px;margin-bottom:15px;display:flex;justify-content:space-between;">
                    <span style="color:#2e7d32;">Member Savings Wallet:</span>
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
                            <option value="savings_wallet">Member's Savings Wallet</option>
                        </select>
                    </div>
                    <div class="form-group" id="ref_group">
                        <label>Reference</label>
                        <input type="text" name="transaction_ref" id="repay_ref" placeholder="Optional">
                    </div>
                </div>
                <div id="wallet_notice" style="display:none;padding:10px;background:#fff3cd;border-radius:8px;margin-bottom:15px;font-size:0.85rem;color:#856404;">
                    <i class="fa-solid fa-info-circle"></i> This amount will be deducted directly from the member's savings wallet.
                </div>
                <button type="submit" class="btn-submit-repay" id="repay_submit">Record Repayment</button>
            </form>
        </div>
    </div>

    <script>
        const loansData = <?php echo json_encode($loans); ?>;
        const walletCache = {};

        function viewLoanDetails(loanId) {
            const loan = loansData.find(l => l.id == loanId);
            if (!loan) return;
            document.getElementById('det_id').textContent = '#' + loan.id;
            document.getElementById('det_member').textContent = loan.member_name || '—';
            document.getElementById('det_group').textContent = loan.group_name || 'N/A';
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

        function openRepayModal(loanId) {
            const loan = loansData.find(l => l.id == loanId);
            if (!loan) return;
            document.getElementById('repay_loan_id').value = loanId;
            document.getElementById('repay_member').textContent = (loan.member_name || 'Unknown') + ' — K' + parseFloat(loan.principal_amount).toFixed(2);
            document.getElementById('repay_balance').textContent = 'K ' + parseFloat(loan.balance).toFixed(2);
            document.getElementById('repay_amount').max = loan.balance;

            const memberId = loan.member_id;
            const walletEl = document.getElementById('repay_wallet');
            if (walletCache[memberId] !== undefined) {
                walletEl.innerHTML = 'K ' + walletCache[memberId].toFixed(2);
            } else {
                walletEl.innerHTML = '<span class="loading-spinner loading-spinner-sm" style="margin-right:6px"></span> <span style="font-size:0.82rem;color:#64748b;">Loading...</span>';
                fetch('get_member_savings.php?member_id=' + memberId + '&group_id=' + loan.group_id)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success === false) throw new Error(data.error || 'Failed to load');
                        const bal = parseFloat(data.balance) || 0;
                        walletCache[memberId] = bal;
                        walletEl.innerHTML = 'K ' + bal.toFixed(2);
                    })
                    .catch(() => {
                        walletEl.innerHTML = '<span style="font-size:0.82rem;color:#ef4444;">Could not load</span>';
                        if (Toast) Toast.show('Could not load savings balance', 'warning');
                    });
            }
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
                const balanceText = document.getElementById('repay_balance').textContent.replace(/[^0-9.]/g, '');
                const walletText = document.getElementById('repay_wallet').textContent.replace(/[^0-9.]/g, '');
                amountInput.max = Math.min(parseFloat(balanceText) || 0, parseFloat(walletText) || 0);
            } else {
                walletNotice.style.display = 'none';
                refGroup.style.display = 'block';
                amountInput.max = parseFloat(document.getElementById('repay_balance').textContent.replace(/[^0-9.]/g, '')) || 0;
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
    <script src="assets/js/loading.js"></script>
    <script src="assets/js/toast.js"></script>
</body>
</html>
