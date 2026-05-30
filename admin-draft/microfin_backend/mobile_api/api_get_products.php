<?php
require_once __DIR__ . '/api_utils.php';
microfin_api_bootstrap();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/loan_application_rules.php';

/** @var mysqli $conn */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    microfin_json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$tenantFilter = microfin_clean_string($_GET['tenant_id'] ?? $_GET['tenant'] ?? '');
if ($tenantFilter === '') {
    microfin_json_response(['success' => false, 'message' => 'tenant_id is required.'], 422);
}

$tenantSql = "
    SELECT tenant_id
    FROM tenants
    WHERE deleted_at IS NULL
      AND (
            LOWER(tenant_id) = LOWER(?)
            OR LOWER(COALESCE(tenant_slug, '')) = LOWER(?)
      )
    LIMIT 1
";

$tenantStmt = $conn->prepare($tenantSql);
if (!$tenantStmt) {
    microfin_json_response([
        'success' => false,
        'message' => 'Failed to prepare tenant lookup: ' . $conn->error,
    ], 500);
}

$tenantStmt->bind_param('ss', $tenantFilter, $tenantFilter);
$tenantStmt->execute();
$tenantRow = $tenantStmt->get_result()->fetch_assoc() ?: null;
$tenantStmt->close();

if (!$tenantRow || trim((string) ($tenantRow['tenant_id'] ?? '')) === '') {
    microfin_json_response(['success' => false, 'message' => 'Tenant not found.'], 404);
}

$tenantId = trim((string) $tenantRow['tenant_id']);
$userId = (int) ($_GET['user_id'] ?? 0);

$productSql = "
    SELECT
        product_id,
        product_id AS id,
        product_name,
        product_name AS name,
        'Loan Product' AS product_type,
        'Loan Product' AS type,
        '' AS description,
        min_amount,
        min_amount AS min,
        max_amount,
        max_amount AS max,
        interest_rate,
        interest_rate AS rate,
        COALESCE(interest_type, '') AS interest_type,
        min_term_months,
        min_term_months AS min_term,
        max_term_months,
        max_term_months AS max_term,
        COALESCE(processing_fee_percentage, 0) AS processing_fee_percentage,
        COALESCE(service_charge, 0) AS service_charge,
        COALESCE(documentary_stamp, 0) AS documentary_stamp,
        COALESCE(insurance_fee_percentage, 0) AS insurance_fee_percentage,
        COALESCE(early_settlement_fee_type, 'no_early_settlement_changes') AS early_settlement_fee_type,
        COALESCE(early_settlement_fee_value, 0) AS early_settlement_fee_value,
        COALESCE(billing_cycle, 'Monthly') AS billing_cycle,
        COALESCE(grace_period_days, 0) AS grace_period_days,
        CAST(COALESCE(is_active, 1) AS CHAR) AS is_active
    FROM loan_products
    WHERE tenant_id = ?
      AND COALESCE(is_active, 1) = 1
    ORDER BY product_name ASC, product_id DESC
";

$productStmt = $conn->prepare($productSql);
if (!$productStmt) {
    microfin_json_response([
        'success' => false,
        'message' => 'Failed to prepare product lookup: ' . $conn->error,
    ], 500);
}

$productStmt->bind_param('s', $tenantId);
$productStmt->execute();
$result = $productStmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $row['product_id'] = (int) ($row['product_id'] ?? 0);
    $row['id'] = (int) ($row['id'] ?? 0);
    $row['min_amount'] = (float) ($row['min_amount'] ?? 0);
    $row['min'] = (float) ($row['min'] ?? 0);
    $row['max_amount'] = (float) ($row['max_amount'] ?? 0);
    $row['max'] = (float) ($row['max'] ?? 0);
    $row['interest_rate'] = (float) ($row['interest_rate'] ?? 0);
    $row['rate'] = (float) ($row['rate'] ?? 0);
    $row['min_term_months'] = (int) ($row['min_term_months'] ?? 0);
    $row['min_term'] = (int) ($row['min_term'] ?? 0);
    $row['max_term_months'] = (int) ($row['max_term_months'] ?? 0);
    $row['max_term'] = (int) ($row['max_term'] ?? 0);
    $row['processing_fee_percentage'] = (float) ($row['processing_fee_percentage'] ?? 0);
    $row['service_charge'] = (float) ($row['service_charge'] ?? 0);
    $row['documentary_stamp'] = (float) ($row['documentary_stamp'] ?? 0);
    $row['insurance_fee_percentage'] = (float) ($row['insurance_fee_percentage'] ?? 0);
    $row['early_settlement_fee_type'] = (string) ($row['early_settlement_fee_type'] ?? 'no_early_settlement_changes');
    $row['early_settlement_fee_value'] = (float) ($row['early_settlement_fee_value'] ?? 0);
    $row['billing_cycle'] = (string) ($row['billing_cycle'] ?? 'Monthly');
    $row['grace_period_days'] = (int) ($row['grace_period_days'] ?? 0);
    $products[] = $row;
}

$productStmt->close();

$creditSummary = null;
$loanAccessState = null;

if ($userId > 0) {
    $clientProfile = microfin_find_client_loan_profile($conn, $userId, $tenantId);

    if ($clientProfile && $clientProfile['client_id'] > 0) {
        // Use credit_limit directly from clients table only
        // No fallback to policy_metadata
    }

    $creditSummary = $clientProfile
        ? microfin_build_client_loan_application_summary($conn, $clientProfile)
        : microfin_loan_rules_default_summary($tenantId);

    // Eligibility rules now live under policy_console_credit_limits.eligibility_rules.
    // DTI/PTI/score_thresholds/multiple_active_loans have been deprecated.
    $rulesStmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = 'policy_console_credit_limits'");
    $rulesStmt->bind_param('s', $tenantId);
    $rulesStmt->execute();
    $rulesRaw = json_decode($rulesStmt->get_result()->fetch_assoc()['setting_value'] ?? '{}', true) ?: [];
    $rulesStmt->close();

    $eligibilityRules = $rulesRaw['eligibility_rules'] ?? [];

    $creditSummary['rules'] = [
        'multiple_active_loans_enabled' => true, // deprecated rule; default-on for now
        'auto_reject_floor' => 0, // deprecated rule
        'dti_enabled' => false, // deprecated rule
        'max_dti_percentage' => 45.0,
        'pti_enabled' => false, // deprecated rule
        'max_pti_percentage' => 30.0,
        'guarantor_required_enabled' => !empty($eligibilityRules['guarantor_required']['enabled']),
        'guarantor_required_above_amount' => (float)($eligibilityRules['guarantor_required']['required_above_amount'] ?? 0),
        'collateral_required_enabled' => !empty($eligibilityRules['collateral_required']['enabled']),
        'collateral_required_above_amount' => (float)($eligibilityRules['collateral_required']['required_above_amount'] ?? 0),
    ];

    $products = microfin_annotate_loan_products($products, $creditSummary);
    $loanAccessState = microfin_build_loan_access_state($products, $creditSummary);
}

$payload = [
    'success' => true,
    'tenant_id' => $tenantId,
    'products' => $products,
];

if ($creditSummary !== null) {
    $payload['credit_summary'] = $creditSummary;
    $payload['loan_access_state'] = $loanAccessState;
}

microfin_json_response($payload);

