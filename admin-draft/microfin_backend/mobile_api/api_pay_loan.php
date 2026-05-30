<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/email_service.php';

/** @var mysqli $conn */
global $dbConfig;

function microfin_fetch_payment_receipt_context(mysqli $conn, string $tenantId, int $clientId, int $loanId): array
{
    $clientEmail = '';
    $clientName = '';
    $tenantName = 'MicroFin';
    $loanNumber = '';

    try {
        $clientStmt = $conn->prepare("
            SELECT email_address, first_name, last_name
            FROM clients
            WHERE client_id = ?
            LIMIT 1
        ");
        $clientStmt->bind_param('i', $clientId);
        $clientStmt->execute();
        $clientRow = $clientStmt->get_result()->fetch_assoc() ?: [];
        $clientStmt->close();

        $clientEmail = (string) ($clientRow['email_address'] ?? '');
        $clientName = trim((string) (($clientRow['first_name'] ?? '') . ' ' . ($clientRow['last_name'] ?? '')));
    } catch (Throwable $ignore) {
    }

    try {
        $tenantStmt = $conn->prepare("
            SELECT tenant_name
            FROM tenants
            WHERE tenant_id = ?
            LIMIT 1
        ");
        $tenantStmt->bind_param('s', $tenantId);
        $tenantStmt->execute();
        $tenantRow = $tenantStmt->get_result()->fetch_assoc() ?: [];
        $tenantStmt->close();

        $tenantName = trim((string) ($tenantRow['tenant_name'] ?? '')) ?: 'MicroFin';
    } catch (Throwable $ignore) {
    }

    try {
        $loanStmt = $conn->prepare("
            SELECT loan_number
            FROM loans
            WHERE loan_id = ?
            LIMIT 1
        ");
        $loanStmt->bind_param('i', $loanId);
        $loanStmt->execute();
        $loanRow = $loanStmt->get_result()->fetch_assoc() ?: [];
        $loanStmt->close();

        $loanNumber = (string) ($loanRow['loan_number'] ?? '');
    } catch (Throwable $ignore) {
    }

    return [
        'client_email' => $clientEmail,
        'client_name' => $clientName,
        'tenant_name' => $tenantName,
        'loan_number' => $loanNumber,
    ];
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$userId = (int) ($data['user_id'] ?? 0);
$tenantId = trim((string) ($data['tenant_id'] ?? ''));
$loanId = (int) ($data['loan_id'] ?? 0);
$amount = (float) ($data['amount'] ?? 0);
$method = trim((string) ($data['payment_method'] ?? 'Online'));
$refNum = trim((string) ($data['reference_number'] ?? ''));
$paymentType = trim((string) ($data['payment_type'] ?? 'regular')); // 'regular' or 'early_settlement'
if ($refNum === '') {
    $refNum = 'REF-' . time();
}

if ($userId <= 0 || $tenantId === '' || $loanId <= 0 || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment details.']);
    exit;
}

try {
    $existingStmt = $conn->prepare("
        SELECT transaction_id, client_id, source_id, payment_date
        FROM payment_transactions
        WHERE tenant_id = ?
          AND loan_id = ?
          AND source_id = ?
          AND LOWER(status) = 'completed'
        ORDER BY transaction_id DESC
        LIMIT 1
    ");
    $existingStmt->bind_param('sis', $tenantId, $loanId, $refNum);
    $existingStmt->execute();
    $existingPayment = $existingStmt->get_result()->fetch_assoc();
    $existingStmt->close();

    if (is_array($existingPayment)) {
        $receiptContext = microfin_fetch_payment_receipt_context(
            $conn,
            $tenantId,
            (int) ($existingPayment['client_id'] ?? 0),
            $loanId
        );

        echo json_encode([
            'success' => true,
            'message' => 'Payment was already posted successfully.',
            'payment_reference' => $refNum,
            'client_email' => $receiptContext['client_email'],
            'client_name' => $receiptContext['client_name'],
            'tenant_name' => $receiptContext['tenant_name'],
            'loan_number' => $receiptContext['loan_number'],
            'payment_date' => (string) ($existingPayment['payment_date'] ?? date('Y-m-d H:i:s')),
            'already_recorded' => true,
        ]);
        exit;
    }

    $conn->begin_transaction();

    $lStmt = $conn->prepare("
        SELECT l.*, lp.interest_rate
        FROM loans l
        LEFT JOIN loan_products lp ON l.product_id = lp.product_id
        WHERE l.loan_id = ?
          AND l.tenant_id = ?
        FOR UPDATE
    ");
    $lStmt->bind_param('is', $loanId, $tenantId);
    $lStmt->execute();
    $lRes = $lStmt->get_result();
    $loan = $lRes->fetch_assoc();
    $lStmt->close();

    if (!$loan) {
        throw new Exception('Loan not found.');
    }

    // Find the earliest pending/overdue amortization entry
    $schedStmt = $conn->prepare("
        SELECT * FROM amortization_schedule
        WHERE loan_id = ? AND payment_status IN ('Pending', 'Overdue', 'Partially Paid')
        ORDER BY payment_number ASC
        LIMIT 1
    ");
    $schedStmt->bind_param('i', $loanId);
    $schedStmt->execute();
    $schedEntry = $schedStmt->get_result()->fetch_assoc();
    $schedStmt->close();

    // Calculate termination fee if early settlement
    $terminationFee = 0;
    if ($paymentType === 'early_settlement') {
        $feeType = $loan['early_settlement_fee_type'] ?? 'no_early_settlement_changes';
        if ($feeType === 'Percentage') {
            $feeType = 'remaining_balance_pct';
        }
        $feeValue = (float) ($loan['early_settlement_fee_value'] ?? 0);
        $remainingBalance = (float) ($loan['remaining_balance'] ?? 0);
        $remainingPrincipal = (float) ($loan['outstanding_principal'] ?? $remainingBalance);
        $totalInterest = (float) ($loan['interest_amount'] ?? 0);
        $totalTerm = (int) ($loan['loan_term_months'] ?? 12);

        // Calculate paid installments
        $paidInstallments = 0;
        $paidSchedStmt = $conn->prepare("
            SELECT COUNT(*) as paid_count
            FROM amortization_schedule
            WHERE loan_id = ? AND payment_status = 'Paid'
        ");
        $paidSchedStmt->bind_param('i', $loanId);
        $paidSchedStmt->execute();
        $paidResult = $paidSchedStmt->get_result()->fetch_assoc();
        $paidInstallments = (int) ($paidResult['paid_count'] ?? 0);
        $paidSchedStmt->close();

        // Calculate fee based on type
        switch ($feeType) {
            case 'remaining_balance_pct':
                $terminationFee = $remainingBalance * ($feeValue / 100);
                break;
            case 'remaining_principal_pct':
                $terminationFee = $remainingPrincipal * ($feeValue / 100);
                break;
            case 'fixed':
                $terminationFee = $feeValue;
                break;
            case 'rebate_only':
                $rebate = calculateRuleOf78sRebate($totalInterest, $totalTerm, $paidInstallments);
                $terminationFee = -$rebate;
                break;
            case 'rebate_plus_pct':
                $rebate = calculateRuleOf78sRebate($totalInterest, $totalTerm, $paidInstallments);
                $rebatedAmount = $remainingBalance - $rebate;
                $terminationFee = $rebatedAmount * ($feeValue / 100);
                break;
            case 'rebate_plus_fixed':
                $rebate = calculateRuleOf78sRebate($totalInterest, $totalTerm, $paidInstallments);
                $terminationFee = $feeValue;
                break;
            default:
                $terminationFee = 0;
        }
    }

    // Allocate payment: termination fee (if early settlement) -> penalty -> interest -> principal
    $penalty = (float) ($loan['outstanding_penalty'] ?? 0);
    $paymentLeft = $amount;

    // Termination fee first (if early settlement)
    $terminationFeePaid = 0;
    if ($paymentType === 'early_settlement' && $terminationFee > 0) {
        $terminationFeePaid = min($paymentLeft, $terminationFee);
        $paymentLeft -= $terminationFeePaid;
    }

    // Penalty
    $penaltyPaid = min($paymentLeft, $penalty);
    $paymentLeft -= $penaltyPaid;

    // Interest
    $monthlyRate = (float) ($loan['interest_rate'] ?? 0) / 100;
    $outstandingPrincipal = (float) ($loan['outstanding_principal'] ?? $loan['remaining_balance'] ?? 0);
    $outstandingInterest = (float) ($loan['outstanding_interest'] ?? 0);
    $interestPortion = $schedEntry
        ? (float) $schedEntry['interest_amount']
        : ($outstandingInterest > 0 ? $outstandingInterest : ($outstandingPrincipal * $monthlyRate));

    $interestPaid = min($paymentLeft, $interestPortion);
    $paymentLeft -= $interestPaid;

    // Principal - for early settlement, all remaining goes to principal, not advance
    $principalPaid = min($paymentLeft, $outstandingPrincipal);
    $paymentLeft -= $principalPaid;

    // Advance payment (only for regular payments)
    $advancePayment = ($paymentType === 'early_settlement') ? 0 : max(0, $paymentLeft);

    $newBalance = max(0, (float) $loan['remaining_balance'] - $amount);
    $newPaid = (float) ($loan['total_paid'] ?? 0) + $amount;
    $newOutstandingPrincipal = max(0, $outstandingPrincipal - $principalPaid);
    $newOutstandingInterest = max(0, $outstandingInterest - $interestPaid);
    $newOutstandingPenalty = max(0, $penalty - $penaltyPaid);
    $newPrincipalPaidTotal = (float) ($loan['principal_paid'] ?? 0) + $principalPaid;
    $newInterestPaidTotal = (float) ($loan['interest_paid'] ?? 0) + $interestPaid;
    $newPenaltyPaidTotal = (float) ($loan['penalty_paid'] ?? 0) + $penaltyPaid;
    $newStatus = ($newBalance <= 0.01) ? 'Fully Paid' : 'Active';

    // Next payment due - for early settlement, clear it
    $nextPaymentDue = $loan['next_payment_due'];
    if ($paymentType === 'early_settlement') {
        $nextPaymentDue = null;
    } elseif ($schedEntry && (float) $schedEntry['total_payment'] <= ($amount + 0.01)) {
        try {
            $nextDt = new DateTime($loan['next_payment_due'] ?? date('Y-m-d'));
            $nextDt->modify('+1 month');
            $nextPaymentDue = $nextDt->format('Y-m-d');
        } catch (Throwable $ignore) {
        }
    }

    $uStmt = $conn->prepare("
        UPDATE loans
        SET remaining_balance = ?, total_paid = ?, loan_status = ?,
            outstanding_principal = ?, outstanding_interest = ?, outstanding_penalty = ?,
            principal_paid = ?, interest_paid = ?, penalty_paid = ?,
            next_payment_due = ?, last_payment_date = CURDATE()
        WHERE loan_id = ?
    ");
    $uStmt->bind_param(
        'ddsddddddsi',
        $newBalance,
        $newPaid,
        $newStatus,
        $newOutstandingPrincipal,
        $newOutstandingInterest,
        $newOutstandingPenalty,
        $newPrincipalPaidTotal,
        $newInterestPaidTotal,
        $newPenaltyPaidTotal,
        $nextPaymentDue,
        $loanId
    );
    $uStmt->execute();
    $uStmt->close();

    // Map mobile payment method to payments table enum
    $paymentMethodMap = [
        'gcash' => 'GCash',
        'paymaya' => 'Online Payment',
        'online' => 'Online Payment',
        'cash' => 'Cash',
        'check' => 'Check',
        'bank_transfer' => 'Bank Transfer',
        'bank transfer' => 'Bank Transfer',
    ];
    $methodKey = strtolower(trim($method));
    $paymentMethodEnum = $paymentMethodMap[$methodKey] ?? 'Online Payment';

    // Generate payment reference for payments table
    $paymentRef = 'PAY-' . date('YmdHis') . '-' . rand(1000, 9999);
    $paymentDateOnly = date('Y-m-d');
    $clientIdInt = (int) $loan['client_id'];

    // Insert into payments table (accounting record with breakdown)
    $payStmt = $conn->prepare("
        INSERT INTO payments (
            payment_reference, loan_id, client_id, tenant_id,
            payment_date, payment_amount, principal_paid, interest_paid,
            penalty_paid, advance_payment, payment_method, payment_reference_number,
            payment_status, posted_date, remarks
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Posted', NOW(), ?)
    ");
    $remarks = 'Mobile payment via ' . $method . ($paymentType === 'early_settlement' ? ' (Early Settlement)' : '');
    $payStmt->bind_param(
        'siisssddddsss',
        $paymentRef,
        $loanId,
        $clientIdInt,
        $tenantId,
        $paymentDateOnly,
        $amount,
        $principalPaid,
        $interestPaid,
        $penaltyPaid,
        $advancePayment,
        $paymentMethodEnum,
        $refNum,
        $remarks
    );
    $payStmt->execute();
    $payStmt->close();

    // Insert into payment_transactions (gateway tracking record)
    $txRef = 'TXN-' . strtoupper(substr(md5(uniqid('', true)), 0, 8)) . '-' . time();
    $pStmt = $conn->prepare("
        INSERT INTO payment_transactions (
            transaction_ref,
            client_id,
            loan_id,
            tenant_id,
            source_id,
            amount,
            payment_method,
            payment_type,
            status,
            payment_date,
            created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW(), NOW()
        )
    ");
    $pStmt->bind_param('siissdss', $txRef, $clientIdInt, $loanId, $tenantId, $refNum, $amount, $method, $paymentType);
    $pStmt->execute();
    $pStmt->close();

    // Update tenant capital with principal replenishment
    if ($principalPaid > 0) {
        try {
            $capStmt = $conn->prepare("
                UPDATE tenant_capital
                SET available_balance = available_balance + ?,
                    total_replenished = total_replenished + ?,
                    last_transaction_at = NOW()
                WHERE tenant_id = ?
            ");
            $capStmt->bind_param('dds', $principalPaid, $principalPaid, $tenantId);
            $capStmt->execute();
            $capStmt->close();
        } catch (Throwable $capErr) {
            error_log('Capital replenishment failed: ' . $capErr->getMessage());
        }
    }

    $sStmt = $conn->prepare("
        SELECT schedule_id, total_payment AS total_due, amount_paid
        FROM amortization_schedule
        WHERE loan_id = ?
          AND payment_status != 'Paid'
        ORDER BY due_date ASC
    ");
    $sStmt->bind_param('i', $loanId);
    $sStmt->execute();
    $sRes = $sStmt->get_result();

    $remainingPayment = $amount;
    $schedUpdates = [];
    while ($row = $sRes->fetch_assoc()) {
        if ($remainingPayment <= 0) {
            break;
        }

        $due = (float) ($row['total_due'] ?? 0);
        $paid = (float) ($row['amount_paid'] ?? 0);
        $unpaid = $due - $paid;

        if ($unpaid > 0) {
            $allocate = min($remainingPayment, $unpaid);
            $newSchedPaid = $paid + $allocate;
            $remainingPayment -= $allocate;
            $schedStatus = ($newSchedPaid >= $due) ? 'Paid' : 'Partially Paid';

            $schedUpdates[] = [
                'id' => (int) ($row['schedule_id'] ?? 0),
                'paid' => $newSchedPaid,
                'status' => $schedStatus,
            ];
        }
    }
    $sStmt->close();

    $suStmt = $conn->prepare("
        UPDATE amortization_schedule
        SET amount_paid = ?, payment_status = ?
        WHERE schedule_id = ?
    ");
    foreach ($schedUpdates as $schedule) {
        $suStmt->bind_param('dsi', $schedule['paid'], $schedule['status'], $schedule['id']);
        $suStmt->execute();
    }
    $suStmt->close();

    $conn->commit();

    // A notification problem should not turn a committed payment into a client-facing failure.
    try {
        $notifTitle = 'Payment Received';
        $notifMessage = 'Your payment of PHP ' . number_format($amount, 2) . ' has been posted successfully.';
        $nStmt = $conn->prepare("
            INSERT INTO notifications (user_id, tenant_id, notification_type, title, message)
            VALUES (?, ?, 'Payment Received', ?, ?)
        ");
        if ($nStmt) {
            $nStmt->bind_param('isss', $userId, $tenantId, $notifTitle, $notifMessage);
            $nStmt->execute();
            $nStmt->close();
        }
    } catch (Throwable $notificationError) {
        error_log('Payment notification insert failed: ' . $notificationError->getMessage());
    }

    $receiptContext = microfin_fetch_payment_receipt_context($conn, $tenantId, (int) $loan['client_id'], $loanId);

    $paymentDateFormatted = date('Y-m-d H:i:s');

    // Automatically send receipt email to client after every successful payment
    try {
        if ($receiptContext['client_email'] !== '') {
            microfin_send_receipt_email($conn, [
                'tenant_id'         => $tenantId,
                'tenant_name'       => $receiptContext['tenant_name'],
                'user_id'           => $userId > 0 ? $userId : null,
                'client_email'      => $receiptContext['client_email'],
                'client_name'       => $receiptContext['client_name'],
                'payment_reference' => $refNum,
                'payment_method'    => $method,
                'loan_number'       => $receiptContext['loan_number'],
                'payment_date'      => $paymentDateFormatted,
                'amount'            => $amount,
            ]);
        }
    } catch (Throwable $emailError) {
        error_log('Receipt email send failed: ' . $emailError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Payment posted successfully.',
        'payment_reference' => $refNum,
        'client_email' => $receiptContext['client_email'],
        'client_name' => $receiptContext['client_name'],
        'tenant_name' => $receiptContext['tenant_name'],
        'loan_number' => $receiptContext['loan_number'],
        'payment_date' => $paymentDateFormatted,
    ]);
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignore) {
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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
?>
