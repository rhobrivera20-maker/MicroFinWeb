<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { exit; }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/api_utils.php';

/** @var mysqli $conn */

$tenantId = trim((string) ($_GET['tenant_id'] ?? ''));

if ($tenantId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing tenant_id.']);
    exit;
}

try {
    require_once __DIR__ . '/../../microfin_web/admin_panel/includes/policy_console_system_defaults.php';
    require_once __DIR__ . '/../../microfin_web/admin_panel/includes/policy_console_limit_assignment.php';
    require_once __DIR__ . '/../../microfin_web/admin_panel/includes/policy_console_compliance_documents.php';
    require_once __DIR__ . '/../../microfin_web/admin_panel/includes/policy_console_credit_limits.php';
    require_once __DIR__ . '/../../microfin_web/admin_panel/includes/credit_policy_workspace.php';
    require_once __DIR__ . '/../engines/credit_policy.php';

    $scoreCeiling = 850;

    $fetchSetting = function(string $key) use ($conn, $tenantId) {
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = ?");
        /** @var mysqli_stmt $stmt */
        $stmt->bind_param('ss', $tenantId, $key);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $value = $result['setting_value'] ?? '{}';
        return json_decode($value, true) ?: [];
    };

    // Legacy Policies (Needed for normalization fallbacks)
    $creditPolicyRaw = $fetchSetting('credit_policy_settings');
    $creditPolicyOrig = mf_credit_policy_normalize($creditPolicyRaw);

    $limitRulesRaw = $fetchSetting('credit_limit_rules');
    $creditLimitRulesOrig = normalize_credit_limit_rules($limitRulesRaw);

    // Compliance Docs - skip catalog for now, return raw data
    $complianceRaw = $fetchSetting('policy_console_compliance_documents');
    $complianceDocs = $complianceRaw;

    // Credit Limits
    $creditLimitsRaw = $fetchSetting('policy_console_credit_limits');
    $creditLimits = policy_console_credit_limits_normalize($creditLimitsRaw, $creditPolicyOrig, $creditLimitRulesOrig, $scoreCeiling);

    // Combine them into a single response
    $eligibilityRules = $creditLimits['eligibility_rules'] ?? [];
    echo json_encode([
        'success' => true,
        'policy' => [
            'credit_limits' => $creditLimits,
            'eligibility_rules' => $eligibilityRules,
            'compliance_documents' => $complianceDocs,
        ],
        // Backwards-compatible top-level alias
        'allowed_employment_statuses' => $eligibilityRules['employment_status']['eligible_statuses'] ?? ['Employed', 'Self-Employed']
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
