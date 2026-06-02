<?php
// File: new_loan.php
// Apply for new loan form page
require_once 'includes/init.php';
require_once 'config/risk_engine.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'member') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$group_id = $_SESSION['group_id'];
$message = '';
$error = '';

// Get group settings
if ($group_id) {
    $stmt = $pdo->prepare("SELECT * FROM savings_groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();
} else {
    $group = ['interest_rate' => 10, 'penalty_rate' => 5];
}

// Auto-select the current member
$stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$members = $stmt->fetchAll();

// Handle loan application
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $user_id;
    $principal = floatval($_POST['principal']);
    $interest_rate = floatval($_POST['interest_rate'] ?? $group['interest_rate']);
    $repayment_period = intval($_POST['repayment_period']);
    $repayment_frequency = $_POST['repayment_frequency'] ?? 'monthly';
    
    // Server-side capacity-driven risk validation
    $risk_profile = getMemberRiskProfile($pdo, $member_id);
    $eligible_amount = floatval($risk_profile['eligible_amount'] ?? ($risk_profile['safe_limit'] ?? 0));
    if (!$risk_profile) {
        $error = "Selected member profile not found.";
    } elseif ($principal > $eligible_amount) {
        $error = "Loan amount exceeds the member's capacity-driven credit limit. Requested: K " . number_format($principal, 2) . ", Eligible: K " . number_format($eligible_amount, 2) . ", Risk Score: " . number_format(floatval($risk_profile['risk_score'] ?? 0), 1) . " (" . $risk_profile['risk_grade'] . ").";
    } else {
        // Calculate total payable with interest
        $interest = $principal * ($interest_rate / 100);
        $total_payable = $principal + $interest;
        
        $target_group_id = $group_id;
        
        try {
            $stmt = $pdo->prepare("INSERT INTO loans (member_id, group_id, principal_amount, interest_rate, total_payable, balance, application_date, repayment_period, repayment_frequency, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$member_id, $target_group_id, $principal, $interest_rate, $total_payable, $total_payable, date('Y-m-d'), $repayment_period, $repayment_frequency]);
            
            // Log transaction
            $stmt = $pdo->prepare("INSERT INTO transactions (group_id, transaction_type, amount, member_id, description, created_by) VALUES (?, 'loan_disbursement', ?, ?, ?, ?)");
            $stmt->execute([$target_group_id, $principal, $member_id, "Loan application of K$principal", $user_id]);
            
            $message = "Loan application submitted successfully!";
            setFlash('success', 'Loan application submitted! Your request is now pending review.', ['celebrate' => true]);
            
            // Clear form
            $_POST = [];
        } catch (PDOException $e) {
            $error = "Error submitting loan: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Loan Application - RSGMS</title>
    <link rel="stylesheet" href="assets/css/icons.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/toast.css">
    <style>
        .loan-calculation {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .calc-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .calc-label {
            font-weight: 500;
        }
        
        .calc-value {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
        }
        
        .back-link:hover {
            color: #667eea;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    <?php include 'config/shared_navbar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <h2>New Loan Application</h2>
        </div>
        
        <div id="flash-data" data-flash='<?php echo json_encode(flashMessages()); ?>' style="display:none"></div>
        
        <div class="form-container">
            <div class="form-title"><i class="fa-solid fa-pen-to-square section-icon"></i> Apply for Loan</div>
            
            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loanForm">
                <input type="hidden" name="member_id" value="<?php echo $user_id; ?>">
                <div class="form-group">
                    <label>Member</label>
                    <div style="padding:12px; background:#f8f9fa; border:1px solid #ddd; border-radius:8px; font-weight:600;">
                        <?php echo htmlspecialchars($members[0]['full_name'] ?? 'You'); ?>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Loan Amount (K) *</label>
                        <input type="number" name="principal" id="principal" step="0.01" min="0" required value="<?php echo htmlspecialchars($_POST['principal'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Interest Rate (%)</label>
                        <input type="number" name="interest_rate" id="interestRate" step="0.01" value="<?php echo htmlspecialchars($_POST['interest_rate'] ?? $group['interest_rate']); ?>" readonly style="background: #f8f9fa;">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Repayment Period *</label>
                        <select name="repayment_period" id="repaymentPeriod" required>
                            <option value="">-- Select Period --</option>
                            <option value="1" <?php echo (isset($_POST['repayment_period']) && $_POST['repayment_period'] == '1') ? 'selected' : ''; ?>>1 month</option>
                            <option value="3" <?php echo (isset($_POST['repayment_period']) && $_POST['repayment_period'] == '3') ? 'selected' : ''; ?>>3 months</option>
                            <option value="6" <?php echo (isset($_POST['repayment_period']) && $_POST['repayment_period'] == '6') ? 'selected' : ''; ?>>6 months</option>
                            <option value="12" <?php echo (isset($_POST['repayment_period']) && $_POST['repayment_period'] == '12') ? 'selected' : ''; ?>>12 months</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Repayment Frequency</label>
                        <select name="repayment_frequency">
                            <option value="monthly">Monthly</option>
                            <option value="weekly">Weekly</option>
                        </select>
                    </div>
                </div>
                
                <!-- Loan Calculation Preview -->
                <div class="loan-calculation" id="calculationPreview" style="display: none;">
                    <h3 style="margin-bottom: 15px; color: #2c3e50;">Loan Calculation</h3>
                    <div class="calc-row">
                        <span class="calc-label">Principal Amount:</span>
                        <span class="calc-value" id="calcPrincipal">K 0.00</span>
                    </div>
                    <div class="calc-row">
                        <span class="calc-label">Interest Amount:</span>
                        <span class="calc-value" id="calcInterest">K 0.00</span>
                    </div>
                    <div class="calc-row">
                        <span class="calc-label">Total Payable:</span>
                        <span class="calc-value" id="calcTotal">K 0.00</span>
                    </div>
                    <div class="calc-row">
                        <span class="calc-label">Monthly Payment:</span>
                        <span class="calc-value" id="calcMonthly">K 0.00</span>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">Submit Loan Application</button>
            </form>
            
            <a href="my_loans.php" class="back-link">← Back to My Loans</a>
        </div>
    </div>
    
    <script>
        function calculateLoan() {
            const principal = parseFloat(document.getElementById('principal').value) || 0;
            const interestRate = parseFloat(document.getElementById('interestRate').value) || 0;
            const period = parseInt(document.getElementById('repaymentPeriod').value) || 1;
            
            if (principal > 0) {
                const interest = principal * (interestRate / 100);
                const total = principal + interest;
                const monthly = total / period;
                
                document.getElementById('calcPrincipal').textContent = 'K ' + principal.toFixed(2);
                document.getElementById('calcInterest').textContent = 'K ' + interest.toFixed(2);
                document.getElementById('calcTotal').textContent = 'K ' + total.toFixed(2);
                document.getElementById('calcMonthly').textContent = 'K ' + monthly.toFixed(2);
                document.getElementById('calculationPreview').style.display = 'block';
            } else {
                document.getElementById('calculationPreview').style.display = 'none';
            }
        }
        
        // Add event listeners
        document.getElementById('principal').addEventListener('input', calculateLoan);
        document.getElementById('repaymentPeriod').addEventListener('change', calculateLoan);
        
        // Dynamic Capacity Risk Profiling AJAX Integration
        const riskCard = document.getElementById('riskProfileCard');
        const riskCardInner = riskCard.querySelector('.risk-profile-inner');
        const submitBtn = document.querySelector('.btn-submit');
        let currentSafeLimit = 0;
        let currentMemberId = <?php echo $user_id; ?>;

        function fetchRiskProfile(memberId) {
            if (!memberId) {
                riskCard.style.display = 'none';
                currentSafeLimit = 0;
                document.getElementById('principal').removeAttribute('max');
                validateAmount();
                return;
            }

            // Show loading state
            riskCard.style.display = 'block';
            riskCardInner.style.display = 'none';
            Loading.showSpinner(riskCard, 'Loading risk profile\u2026');

            fetch(`get_member_risk_profile.php?member_id=${memberId}`)
                .then(response => {
                    if (!response.ok) throw new Error('Server returned ' + response.status);
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        Loading.hideSpinner(riskCard);
                        riskCardInner.style.display = '';

                        const p = data.profile;
                        const derivedLimit = parseFloat(p.eligible_amount ?? p.safe_limit ?? 0);
                        currentSafeLimit = isNaN(derivedLimit) ? 0 : derivedLimit;
                        
                        document.getElementById('riskMemberName').textContent = p.full_name;
                        document.getElementById('riskGroupName').textContent = p.group_name;
                        document.getElementById('riskHistSavings').textContent = 'K ' + parseFloat(p.historical_savings).toFixed(2);
                        document.getElementById('riskOutDebt').textContent = 'K ' + parseFloat(p.outstanding_debt).toFixed(2);
                        
                        const netEq = parseFloat(p.net_equity);
                        const netEqText = document.getElementById('riskNetEquity');
                        netEqText.textContent = 'K ' + netEq.toFixed(2);
                        netEqText.style.color = netEq >= 0 ? '#34d399' : '#f87171';
                        
                        document.getElementById('riskLateReps').textContent = `${p.late_repayments} / ${p.total_repayments} late`;
                        
                        const badge = document.getElementById('riskGradeBadge');
                        const score = parseFloat(p.risk_score || 0).toFixed(1);
                        badge.textContent = `GRADE ${p.risk_grade} | SCORE ${score}`;
                        badge.style.borderColor = p.risk_color;
                        badge.style.color = p.risk_color;
                        badge.style.background = `${p.risk_color}1a`;
                        
                        document.getElementById('riskSafeLimit').textContent = 'K ' + currentSafeLimit.toFixed(2);
                        document.getElementById('riskDescription').textContent = `${p.risk_label}. ${p.description}`;
                        
                        document.getElementById('principal').max = currentSafeLimit;
                        
                        validateAmount();
                    } else {
                        Loading.errorState(riskCard, {
                            message: 'Could not load risk profile',
                            detail: data.error || 'Unknown error',
                            onRetry: function () { fetchRiskProfile(memberId); }
                        });
                        currentSafeLimit = 0;
                        document.getElementById('principal').removeAttribute('max');
                        validateAmount();
                    }
                })
                .catch(err => {
                    Loading.errorState(riskCard, {
                        message: 'Failed to load risk profile',
                        detail: 'Network error. Please check your connection.',
                        onRetry: function () { fetchRiskProfile(memberId); }
                    });
                    currentSafeLimit = 0;
                    document.getElementById('principal').removeAttribute('max');
                    validateAmount();
                });
        }

        function validateAmount() {
            const amountInput = document.getElementById('principal');
            const amount = parseFloat(amountInput.value) || 0;
            let warningDiv = document.getElementById('limitWarning');
            
            if (!warningDiv) {
                warningDiv = document.createElement('div');
                warningDiv.id = 'limitWarning';
                warningDiv.style.marginTop = '8px';
                warningDiv.style.fontSize = '0.85rem';
                warningDiv.style.fontWeight = '600';
                amountInput.parentNode.appendChild(warningDiv);
            }

            if (currentMemberId && amount > currentSafeLimit) {
                warningDiv.textContent = `Warning: Requested amount exceeds the member's dynamic Safe Borrowing Limit of K ${currentSafeLimit.toFixed(2)}!`;
                warningDiv.style.color = '#ef4444';
                amountInput.style.borderColor = '#ef4444';
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
            } else {
                warningDiv.textContent = '';
                amountInput.style.borderColor = '';
                submitBtn.disabled = false;
                submitBtn.style.opacity = '';
                submitBtn.style.cursor = '';
            }
        }

        document.getElementById('principal').addEventListener('input', validateAmount);

        // Fetch risk profile on page load for current member
        fetchRiskProfile(currentMemberId);
        
        // Calculate on page load if values exist
        calculateLoan();
    </script>
    <script src="assets/js/loading.js"></script>
    <script src="assets/js/toast.js"></script>
</body>
</html>
