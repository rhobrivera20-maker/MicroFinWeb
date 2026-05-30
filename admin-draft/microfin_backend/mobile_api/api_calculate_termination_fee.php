<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

/** @var mysqli $conn */

$data = json_decode(file_get_contents('php://input'), true) ?: [];

$loanNumber = trim((string) ($data['loan_number'] ?? ''));
$tenantId = trim((string) ($data['tenant_id'] ?? ''));

if ($loanNumber === '' || $tenantId === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    // Fetch loan details with product information
    $stmt = $conn->prepare("
        SELECT 
            l.*,
            lp.early_settlement_fee_type,
            lp.early_settlement_fee_value,
            lp.interest_type
        FROM loans l
        LEFT JOIN loan_products lp ON l.product_id = lp.product_id
        WHERE l.loan_number = ? AND l.tenant_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('ss', $loanNumber, $tenantId);
    $stmt->execute();
    $loan = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$loan) {
        echo json_encode(['success' => false, 'message' => 'Loan not found', 'debug' => ['loan_number' => $loanNumber, 'tenant_id' => $tenantId]]);
        exit;
    }

    // Check if loan has a valid product
    if (empty($loan['product_id'])) {
        echo json_encode(['success' => false, 'message' => 'Loan has no associated product', 'debug' => ['loan_id' => $loan['loan_id'] ?? null]]);
        exit;
    }

    $feeType = $loan['early_settlement_fee_type'] ?? 'no_early_settlement_changes';
    // Backward compatibility: map old 'Percentage' to 'remaining_balance_pct'
    if ($feeType === 'Percentage') {
        $feeType = 'remaining_balance_pct';
    }
    $feeValue = (float) ($loan['early_settlement_fee_value'] ?? 0);
    $remainingBalance = (float) ($loan['remaining_balance'] ?? 0);
    $remainingPrincipal = (float) ($loan['outstanding_principal'] ?? $remainingBalance);
    $totalInterest = (float) ($loan['interest_amount'] ?? 0);
    $totalTerm = (int) ($loan['loan_term_months'] ?? 12);
    $interestType = $loan['interest_type'] ?? 'Declining Balance';

    // Validate required fields
    if ($remainingBalance <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid remaining balance']);
        exit;
    }

    // Calculate paid installments from amortization schedule
    $paidInstallments = 0;
    $schedStmt = $conn->prepare("
        SELECT COUNT(*) as paid_count
        FROM amortization_schedule
        WHERE loan_id = ? AND payment_status = 'Paid'
    ");
    $schedStmt->bind_param('i', $loan['loan_id']);
    $schedStmt->execute();
    $paidResult = $schedStmt->get_result()->fetch_assoc();
    $paidInstallments = (int) ($paidResult['paid_count'] ?? 0);
    $schedStmt->close();

    // Calculate fee based on type
    $fee = 0;
    $description = '';
    $rebate = 0;

    if ($feeType !== 'no_early_settlement_changes') {
        switch ($feeType) {
            case 'remaining_balance_pct':
                $fee = $remainingBalance * ($feeValue / 100);
                $description = number_format($feeValue, 2) . '% of remaining balance';
                break;

            case 'remaining_principal_pct':
                $fee = $remainingPrincipal * ($feeValue / 100);
                $description = number_format($feeValue, 2) . '% of remaining principal';
                break;

            case 'fixed':
                $fee = $feeValue;
                $description = 'Fixed fee of ₱' . number_format($feeValue, 2);
                break;

            case 'rebate_only':
                $rebate = calculateRuleOf78sRebate($totalInterest, $totalTerm, $paidInstallments);
                $fee = -$rebate; // Negative fee = discount
                $description = 'Rebate of ₱' . number_format($rebate, 2);
                break;

            case 'rebate_plus_pct':
                $rebate = calculateRuleOf78sRebate($totalInterest, $totalTerm, $paidInstallments);
                $rebatedAmount = $remainingBalance - $rebate;
                $fee = $rebatedAmount * ($feeValue / 100);
                $description = number_format($feeValue, 2) . '% of rebated amount (₱' . number_format($rebate, 2) . ' rebate)';
                break;

            case 'rebate_plus_fixed':
                $rebate = calculateRuleOf78sRebate($totalInterest, $totalTerm, $paidInstallments);
                $fee = $feeValue;
                $description = 'Fixed fee of ₱' . number_format($feeValue, 2) . ' (₱' . number_format($rebate, 2) . ' rebate)';
                break;

            default:
                $fee = 0;
                $description = 'Unknown fee type';
        }
    } else {
        $description = 'No early settlement changes';
    }

    $totalSettlement = $remainingBalance + $fee;

    echo json_encode([
        'success' => true,
        'data' => [
            'fee_type' => $feeType,
            'fee_value' => $feeValue,
            'remaining_balance' => $remainingBalance,
            'remaining_principal' => $remainingPrincipal,
            'rebate' => $rebate,
            'fee' => $fee,
            'description' => $description,
            'total_settlement' => $totalSettlement,
            'paid_installments' => $paidInstallments,
            'total_term' => $totalTerm,
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'debug' => [
        'loan_number' => $loanNumber,
        'tenant_id' => $tenantId,
        'error' => $e->getMessage()
    ]]);
}

/**
 * Calculate rebate using Rule of 78s method
 * 
 * @param float $totalInterest Total interest for the loan
 * @param int $totalTerm Total loan term in months
 * @param int $paidInstallments Number of installments already paid
 * @return float The rebate amount (unearned interest)
 */
function calculateRuleOf78sRebate($totalInterest, $totalTerm, $paidInstallments) {
    if ($totalTerm <= 0 || $paidInstallments >= $totalTerm) {
        return 0;
    }

    // Calculate sum of digits for the term
    $sumOfDigits = 0;
    for ($i = $totalTerm; $i >= 1; $i--) {
        $sumOfDigits += $i;
    }

    if ($sumOfDigits <= 0) {
        return 0;
    }

    // Calculate earned interest (months already paid)
    $earnedInterest = 0;
    for ($i = $totalTerm; $i > ($totalTerm - $paidInstallments); $i--) {
        $earnedInterest += ($i / $sumOfDigits) * $totalInterest;
    }

    // Unearned interest (rebate) = Total interest - Earned interest
    $rebate = $totalInterest - $earnedInterest;

    return max(0, $rebate);
}
