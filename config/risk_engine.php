<?php
// File: config/risk_engine.php
// Centralized dynamic credit capacity & risk profiling engine

function clampFloat($value, $min, $max) {
    return max($min, min($max, floatval($value)));
}

/**
 * Calculates a capacity-driven risk profile and credit ceiling for a member.
 *
 * Policy summary:
 * - Never use a flat rejection only because an outstanding loan exists.
 * - Compute a structural ceiling from historical savings capacity.
 * - Apply risk score bounds to cap borrowing capacity for weaker profiles.
 *
 * @param PDO $pdo The active PDO connection instance
 * @param int $member_id The unique user ID of the savings member
 * @return array|null The detailed risk profile associative array, or null if member not found
 */
function getMemberRiskProfile($pdo, $member_id) {
    $member_id = intval($member_id);
    if ($member_id <= 0) {
        return null;
    }

    try {
        // 1. Fetch member personal and group details
        $stmt = $pdo->prepare("SELECT u.*, sg.group_name, sg.interest_rate FROM users u LEFT JOIN savings_groups sg ON u.group_id = sg.id WHERE u.id = ? AND u.role = 'member'");
        $stmt->execute([$member_id]);
        $member = $stmt->fetch();
        if (!$member) {
            return null;
        }

        // 2. Fetch total historical savings capacity
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM savings_contributions WHERE member_id = ?");
        $stmt->execute([$member_id]);
        $historical_savings = floatval($stmt->fetch()['total']);

        // 3. Fetch active/outstanding loan obligations (Status: approved or disbursed)
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(balance), 0) as total, COUNT(*) as active_count FROM loans WHERE member_id = ? AND status IN ('disbursed', 'approved')");
        $stmt->execute([$member_id]);
        $loan_data = $stmt->fetch();
        $outstanding_debt = floatval($loan_data['total']);
        $active_loans_count = intval($loan_data['active_count']);

        // 4. Fetch overdue loan data (Disbursed status and current date exceeds the allowed repayment period)
        $stmt = $pdo->prepare("SELECT COUNT(*) as overdue_count, COALESCE(SUM(balance), 0) as overdue_balance FROM loans WHERE member_id = ? AND status = 'disbursed' AND CURRENT_DATE() > DATE_ADD(disbursement_date, INTERVAL repayment_period MONTH)");
        $stmt->execute([$member_id]);
        $overdue_data = $stmt->fetch();
        $overdue_count = intval($overdue_data['overdue_count']);
        $overdue_balance = floatval($overdue_data['overdue_balance']);

        // 5. Fetch historical repayment behavior (punctuality metrics)
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_rep, SUM(CASE WHEN is_late = 1 THEN 1 ELSE 0 END) as late_rep FROM loan_repayments lr JOIN loans l ON lr.loan_id = l.id WHERE l.member_id = ?");
        $stmt->execute([$member_id]);
        $repayment_data = $stmt->fetch();
        $total_repayments = intval($repayment_data['total_rep']);
        $late_repayments = intval($repayment_data['late_rep']);

        // 6. Calculate net collateral equity and structural ratios
        $net_equity = $historical_savings - $outstanding_debt;
        $savings_equity_ratio = $historical_savings > 0 ? clampFloat($net_equity / $historical_savings, 0.0, 1.0) : 0.0;
        $structural_risk_ratio = $historical_savings > 0 ? (($outstanding_debt + $overdue_balance) / $historical_savings) : ($outstanding_debt > 0 ? 2.0 : 0.0);
        $late_ratio = $total_repayments > 0 ? ($late_repayments / $total_repayments) : ($active_loans_count > 0 ? 0.20 : 0.0);

        // 7. Capacity-driven risk score bounds (0-100)
        // Weight 1: savings equity strength (primary signal)
        $equity_component = $savings_equity_ratio * 70.0;
        // Weight 2: repayment discipline signal
        $discipline_component = (1.0 - clampFloat($late_ratio, 0.0, 1.0)) * 20.0;
        // Weight 3: overdue stress and outstanding pressure deductions
        $overdue_penalty = min(30.0, ($overdue_count * 6.0) + ($structural_risk_ratio > 1.0 ? ($structural_risk_ratio - 1.0) * 10.0 : 0.0));
        $active_loan_penalty = min(10.0, $active_loans_count * 2.0);
        $risk_score = clampFloat(round($equity_component + $discipline_component + 10.0 - $overdue_penalty - $active_loan_penalty, 1), 0.0, 100.0);

        // 8. Grade and multiplier from bounded score
        $risk_grade = 'A';
        $risk_label = 'Low Risk (Premium Saver)';
        $risk_color = '#10b981';
        $description = 'Premium savings history, excellent repayment record, no active debt.';
        $multiplier = 2.8;

        if ($risk_score >= 80) {
            $risk_grade = 'A';
            $risk_label = 'Low Risk (High Capacity)';
            $risk_color = '#10b981';
            $description = 'Strong savings equity and healthy repayment profile.';
            $multiplier = 2.8;
        } elseif ($risk_score >= 65) {
            $risk_grade = 'B';
            $risk_label = 'Low-Medium Risk (Stable Capacity)';
            $risk_color = '#3b82f6';
            $description = 'Stable collateral base with moderate lending headroom.';
            $multiplier = 2.2;
        } elseif ($risk_score >= 50) {
            $risk_grade = 'C';
            $risk_label = 'Medium Risk (Managed Capacity)';
            $risk_color = '#f59e0b';
            $description = 'Capacity remains available but within tighter structural bounds.';
            $multiplier = 1.4;
        } elseif ($risk_score >= 35) {
            $risk_grade = 'D';
            $risk_label = 'High Risk (Restricted Capacity)';
            $risk_color = '#f97316';
            $description = 'Low baseline savings equity relative to risk bounds. Capacity is heavily capped.';
            $multiplier = 0.8;
        } else {
            $risk_grade = 'E';
            $risk_label = 'Severe Risk (Minimal Capacity)';
            $risk_color = '#ef4444';
            $description = 'Very low savings equity against risk exposure. Only minimal disbursement is allowed.';
            $multiplier = 0.35;
        }

        // 9. Capacity-driven credit ceiling
        $structural_ceiling = max(0.0, $historical_savings * $multiplier);
        $capacity_headroom = max(0.0, $structural_ceiling - $outstanding_debt);

        // If there are overdue balances, compress the headroom further.
        if ($overdue_count > 0 || $overdue_balance > 0) {
            $capacity_headroom *= 0.60;
        }

        // Guarantee small but controlled access for active borrowers in good standing.
        if ($capacity_headroom <= 0 && $historical_savings >= 150 && $overdue_count === 0) {
            $capacity_headroom = min(200.0, $historical_savings * 0.20);
        }

        // Severe risk floor remains constrained.
        if ($risk_grade === 'E') {
            $capacity_headroom = min($capacity_headroom, max(0.0, $historical_savings * 0.10));
        }

        $eligible_amount = round(max(0.0, $capacity_headroom), 2);

        return [
            'member_id' => $member_id,
            'full_name' => $member['full_name'],
            'group_name' => $member['group_name'] ?? 'No Group',
            'historical_savings' => $historical_savings,
            'outstanding_debt' => $outstanding_debt,
            'net_equity' => $net_equity,
            'savings_equity_ratio' => round($savings_equity_ratio, 4),
            'structural_risk_ratio' => round($structural_risk_ratio, 4),
            'active_loans_count' => $active_loans_count,
            'overdue_loans_count' => $overdue_count,
            'overdue_balance' => $overdue_balance,
            'total_repayments' => $total_repayments,
            'late_repayments' => $late_repayments,
            'risk_score' => $risk_score,
            'risk_grade' => $risk_grade,
            'risk_label' => $risk_label,
            'risk_color' => $risk_color,
            'description' => $description,
            'multiplier' => $multiplier,
            'safe_limit' => $eligible_amount,
            'eligible_amount' => $eligible_amount,
            'structural_ceiling' => round($structural_ceiling, 2)
        ];

    } catch (PDOException $e) {
        // Fail-safe default fallback in case of database error
        return [
            'member_id' => $member_id,
            'full_name' => 'Unknown Member',
            'group_name' => 'N/A',
            'historical_savings' => 0.00,
            'outstanding_debt' => 0.00,
            'net_equity' => 0.00,
            'active_loans_count' => 0,
            'overdue_loans_count' => 0,
            'overdue_balance' => 0.00,
            'total_repayments' => 0,
            'late_repayments' => 0,
            'risk_score' => 0.0,
            'risk_grade' => 'E',
            'risk_label' => 'Error / System Failure',
            'risk_color' => '#ef4444',
            'description' => 'System query failed: ' . $e->getMessage(),
            'multiplier' => 0.0,
            'safe_limit' => 0.00,
            'eligible_amount' => 0.00,
            'structural_ceiling' => 0.00,
            'savings_equity_ratio' => 0.0,
            'structural_risk_ratio' => 0.0
        ];
    }
}
