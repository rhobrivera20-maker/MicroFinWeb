<?php
require_once "../../microfin_backend/auth/session_auth.php";
mf_start_backend_session();
require_once "../../microfin_backend/config/db_connect.php";
require_once "../../microfin_backend/billing/billing_access.php";
require_once "../../microfin_backend/billing/billing_notifications.php";
mf_require_tenant_session($pdo, [
    'response' => 'redirect',
    'redirect' => 'login.php',
    'append_tenant_slug' => true,
]);

function billing_add_30_days(string $dateString): string
{
    $source = DateTimeImmutable::createFromFormat('Y-m-d', $dateString) ?: new DateTimeImmutable($dateString);
    return $source->add(new DateInterval('P30D'))->format('Y-m-d');
}

function billing_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $safe_column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE '{$safe_column}'");
    $stmt->execute();
    $cache[$key] = (bool)$stmt->fetch();

    return $cache[$key];
}

$tenant_id = $_SESSION['tenant_id'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Check current setup step — this page is step 5 (billing - final)
$tenants_has_billing_cycle = billing_column_exists($pdo, 'tenants', 'billing_cycle');
$select_cols = 'setup_current_step, setup_completed, plan_tier, max_clients, max_users, mrr';
if ($tenants_has_billing_cycle) {
    $select_cols .= ', billing_cycle';
}
$step_stmt = $pdo->prepare("SELECT {$select_cols} FROM tenants WHERE tenant_id = ?");
$step_stmt->execute([$tenant_id]);
$step_data = $step_stmt->fetch(PDO::FETCH_ASSOC);
$current_step = (int)($step_data['setup_current_step'] ?? 0);

$plan_catalog = [
    'Starter' => [
        'price' => 4999,
        'max_clients' => 2000,
        'max_users' => 1000,
        'description' => 'Best for newly launched microfinance teams.',
        'inclusions' => [
            'Serve up to 2,000 clients',
            'Support up to 1,000 staff members',
            'Your own branded mobile app for clients',
            'Professional website with 2 design options',
            'Create custom loan products and approval rules',
            'Track lending capital and disbursements',
            'Staff dashboard for daily operations',
            'Financial reports (PAR, balance sheets, income statements)',
            'Accept payments via GCash and PayMaya',
            'Automatic payment reminders to clients',
            'Secure login and activity tracking'
        ]
    ],
    'Enterprise' => [
        'price' => 14999,
        'max_clients' => -1,
        'max_users' => -1,
        'description' => 'Unlimited capacity for established enterprises.',
        'inclusions' => [
            'Everything in Starter, plus:',
            'Unlimited clients and staff',
            'Fully white-labeled mobile app',
            'All premium website templates',
            'Priority technical support',
            'Custom integrations with your systems'
        ]
    ]
];

$plan_aliases = [
    'Professional' => 'Starter',
    'Pro' => 'Starter',
    'Elite' => 'Enterprise',
    'Unlimited' => 'Enterprise'
];

$legacy_plan_catalog = [
    'Growth' => [
        'price' => 9999,
        'max_clients' => 2500,
        'max_users' => 750,
        'description' => 'Legacy plan from an earlier application.'
    ]
];

$application_plan_tier = trim((string)($step_data['plan_tier'] ?? 'Starter'));
if (isset($plan_aliases[$application_plan_tier])) {
    $application_plan_tier = $plan_aliases[$application_plan_tier];
}

$application_plan_meta = $plan_catalog[$application_plan_tier] ?? ($legacy_plan_catalog[$application_plan_tier] ?? null);
$application_plan_is_available = isset($plan_catalog[$application_plan_tier]);
$current_plan_tier = $application_plan_is_available ? $application_plan_tier : 'Starter';
$selected_plan_tier = $current_plan_tier;
$monthly_price = (float)($plan_catalog[$selected_plan_tier]['price'] ?? ($step_data['mrr'] ?? 0));
$tenants_has_next_billing_date = billing_column_exists($pdo, 'tenants', 'next_billing_date');

$selected_billing_cycle = 'Monthly';
if ($tenants_has_billing_cycle && !empty($step_data['billing_cycle'])) {
    $selected_billing_cycle = trim((string)$step_data['billing_cycle']);
}

if ($step_data && (bool)$step_data['setup_completed']) {
    header('Location: ../admin_panel/admin.php');
    exit;
}

if ($current_step !== 5) {
    if ($current_step > 0 && $current_step < 5) {
        // Billing is now the first onboarding gate after password reset.
        $pdo->prepare('UPDATE tenants SET setup_current_step = 5 WHERE tenant_id = ?')->execute([$tenant_id]);
        $current_step = 5;
    } else {
        $setup_routes = [0 => 'force_change_password.php'];
        if (isset($setup_routes[$current_step])) {
            header('Location: ' . $setup_routes[$current_step]);
        } else {
            header('Location: ../admin_panel/admin.php');
        }
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billing_company_name = trim($_POST['billing_company_name'] ?? '');
    $cardholder_name = $billing_company_name; // Repurpose for DB compatibility as "Institution Card"

    $card_number = trim($_POST['card_number'] ?? '');
    $exp_month = (int) ($_POST['exp_month'] ?? 0);
    $exp_year = (int) ($_POST['exp_year'] ?? 0);
    $card_brand = trim($_POST['card_brand'] ?? '');
    $selected_plan_tier = trim((string)($_POST['subscription_plan'] ?? $current_plan_tier));

    if (isset($plan_aliases[$selected_plan_tier])) {
        $selected_plan_tier = $plan_aliases[$selected_plan_tier];
    }
    if (!isset($plan_catalog[$selected_plan_tier])) {
        $selected_plan_tier = $current_plan_tier;
    }
    $selected_plan = $plan_catalog[$selected_plan_tier];
    $monthly_price = (float)$selected_plan['price'];
    $billing_cycle = trim($_POST['billing_cycle'] ?? 'Monthly');
    if (!in_array($billing_cycle, ['Monthly', 'Quarterly', 'Yearly'])) {
        $billing_cycle = 'Monthly';
    }
    $charged_amount = mf_calculate_cycle_price($monthly_price, $billing_cycle);

    // Validate
    $card_clean = preg_replace('/\s+/', '', $card_number);
    if ($billing_company_name === '' || $card_clean === '') {
        $error = 'Company name and card number are required.';
    } elseif (strlen($card_clean) < 13 || strlen($card_clean) > 19 || !ctype_digit($card_clean)) {
        $error = 'Please enter a valid card number (13-19 digits).';
    } else {
        // Expiration check: must be at least one year from now
        $today = new DateTime('first day of this month');
        $one_year_from_now = (new DateTime('first day of this month'))->modify('+1 year');
        $exp_date = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $exp_year, $exp_month));
        
        if (!$exp_date || $exp_date < $one_year_from_now) {
            $error = 'For subscription security, your card expiration date must be at least one year from now.';
        } else {
            $last_four = substr($card_clean, -4);

        // Encrypt the full card number with AES-256
        $encryption_key = defined('ENCRYPTION_KEY') ? constant('ENCRYPTION_KEY') : 'microfin_default_encryption_key_32b';
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($card_clean, 'aes-256-cbc', $encryption_key, 0, $iv);
        $encrypted_with_iv = base64_encode($iv . '::' . base64_decode($encrypted));

        // Auto-detect card brand
        if ($card_brand === '') {
            $first_digit = $card_clean[0];
            $first_two = substr($card_clean, 0, 2);
            if ($first_digit === '4') $card_brand = 'Visa';
            elseif (in_array($first_two, ['51','52','53','54','55'])) $card_brand = 'Mastercard';
            elseif (in_array($first_two, ['34','37'])) $card_brand = 'Amex';
            else $card_brand = 'Other';
        }

        $receipt_email_details = null;

        try {
            $pdo->beginTransaction();

            $pdo->prepare('UPDATE tenant_billing_payment_methods SET is_default = FALSE WHERE tenant_id = ?')
                ->execute([$tenant_id]);
            $stmt = $pdo->prepare('INSERT INTO tenant_billing_payment_methods (tenant_id, last_four_digits, card_brand, cardholder_name, exp_month, exp_year, card_number_encrypted, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)');
            $stmt->execute([$tenant_id, $last_four, $card_brand, $cardholder_name, $exp_month, $exp_year, $encrypted_with_iv]);

            $charge_timestamp = date('Y-m-d H:i:s');
            $activation_date = date('Y-m-d', strtotime($charge_timestamp));
            $next_billing = mf_get_next_billing_date($billing_cycle, $activation_date);
            $payment_method_desc = $card_brand . ' ending in ' . $last_four;

            $tenant_update_parts = [
                'plan_tier = ?',
                'mrr = ?',
                'max_clients = ?',
                'max_users = ?',
                'setup_current_step = 6',
                'setup_completed = TRUE'
            ];
            $tenant_update_params = [
                $selected_plan_tier,
                $monthly_price,
                (int)$selected_plan['max_clients'],
                (int)$selected_plan['max_users']
            ];
            if ($tenants_has_billing_cycle) {
                $tenant_update_parts[] = 'billing_cycle = ?';
                $tenant_update_params[] = $billing_cycle;
            }
            if ($tenants_has_next_billing_date) {
                $tenant_update_parts[] = 'next_billing_date = ?';
                $tenant_update_params[] = $next_billing;
            }
            $tenant_update_params[] = $tenant_id;
            $upd = $pdo->prepare('UPDATE tenants SET ' . implode(', ', $tenant_update_parts) . ' WHERE tenant_id = ? AND setup_current_step = 5');
            $upd->execute($tenant_update_params);

            $upsert_setting = $pdo->prepare("
                INSERT INTO system_settings (tenant_id, setting_key, setting_value, setting_category, data_type)
                VALUES (?, ?, ?, 'Billing', 'String')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_category = VALUES(setting_category), data_type = VALUES(data_type), updated_at = CURRENT_TIMESTAMP
            ");
            $upsert_setting->execute([$tenant_id, 'next_billing_date', $next_billing]);
            $upsert_setting->execute([$tenant_id, 'billing_company_name', $billing_company_name]);

            $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'BILLING_SETUP', 'tenant', 'Payment method added during onboarding', ?)");
            $log->execute([$user_id, $tenant_id]);

            if ($selected_plan_tier !== $application_plan_tier) {
                $plan_log = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'SUBSCRIPTION_UPDATE', 'tenant', ?, ?)");
                $plan_log->execute([$user_id, "Subscription plan changed during onboarding from {$application_plan_tier} to {$selected_plan_tier}", $tenant_id]);
            }

            if ($monthly_price > 0) {
                $reference_suffix = strtoupper(substr(hash('sha256', $tenant_id . $charge_timestamp . random_int(1000, 9999)), 0, 10));
                $invoice_number = 'INV-' . date('Ymd') . '-' . substr($reference_suffix, 0, 6);
                $payment_reference = 'SUB-' . $reference_suffix;
                $period_start = $activation_date;
                $period_end = date('Y-m-d', strtotime($next_billing . ' -1 day'));

                $inv_stmt = $pdo->prepare("
                    INSERT INTO tenant_billing_invoices 
                    (tenant_id, invoice_number, amount, billing_period_start, billing_period_end, due_date, status, paid_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'Paid', NOW())
                ");
                $inv_stmt->execute([
                    $tenant_id,
                    $invoice_number,
                    $charged_amount,
                    $period_start,
                    $period_end,
                    $period_start
                ]);

                $log2 = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, description, tenant_id) VALUES (?, 'BILLING_ACTIVATION', 'invoice', ?, ?)");
                $log2->execute([$user_id, "Generated initial activation billing records {$invoice_number} / {$payment_reference}. Amount: {$charged_amount}. Next billing date: {$next_billing}.", $tenant_id]);

                $receipt_email_details = [
                    'plan_tier' => $selected_plan_tier,
                    'amount' => $charged_amount,
                    'payment_date' => $charge_timestamp,
                    'payment_reference' => $payment_reference,
                    'invoice_number' => $invoice_number,
                    'payment_method' => $payment_method_desc,
                    'period_start' => $period_start,
                    'period_end' => $period_end,
                    'next_billing_date' => $next_billing,
                ];
            }

            $pdo->commit();

            if (is_array($receipt_email_details)) {
                $email_result = mf_billing_send_receipt_email($pdo, (string)$tenant_id, $receipt_email_details);
                if ($email_result !== 'Email sent successfully.') {
                    error_log('setup_billing receipt email failed for tenant ' . $tenant_id . ': ' . $email_result);
                }
            }

            $_SESSION['admin_flash'] = "Subscription activated on the {$selected_plan_tier} plan. You can now use your dashboard and finish your website and branding from the setup checklist.";
            header('Location: ../admin_panel/admin.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('setup_billing activation error for tenant ' . $tenant_id . ': ' . $e->getMessage());
            $error = 'Unable to activate the subscription right now. Please try again.';
        }
      }
    }
}

// Fetch branding for styling
$brand_stmt = $pdo->prepare('SELECT theme_primary_color, theme_text_main, theme_text_muted, theme_bg_body, theme_bg_card, font_family FROM tenant_branding WHERE tenant_id = ?');
$brand_stmt->execute([$tenant_id]);
$brand = $brand_stmt->fetch(PDO::FETCH_ASSOC);
$accent = ($brand && $brand['theme_primary_color']) ? $brand['theme_primary_color'] : '#0284c7';
$t_text = ($brand && $brand['theme_text_main']) ? $brand['theme_text_main'] : '#0f172a';
$t_muted = ($brand && $brand['theme_text_muted']) ? $brand['theme_text_muted'] : '#64748b';
$t_bg = ($brand && $brand['theme_bg_body']) ? $brand['theme_bg_body'] : '#f1f5f9';
$t_card = ($brand && $brand['theme_bg_card']) ? $brand['theme_bg_card'] : '#ffffff';
$t_font = ($brand && $brand['font_family']) ? $brand['font_family'] : 'Inter';

$tenant_name = $_SESSION['tenant_name'] ?? 'Your Organization';
$current_year = (int) date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Billing - MicroFin</title>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($t_font); ?>:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        :root {
            --accent: <?php echo htmlspecialchars($accent); ?>;
            --accent-light: rgba(2, 132, 199, 0.08);
            --t-text: <?php echo htmlspecialchars($t_text); ?>;
            --t-muted: <?php echo htmlspecialchars($t_muted); ?>;
            --t-bg: <?php echo htmlspecialchars($t_bg); ?>;
            --t-card: <?php echo htmlspecialchars($t_card); ?>;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: '<?php echo htmlspecialchars($t_font); ?>', sans-serif;
            background: linear-gradient(135deg, var(--t-bg) 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            color: var(--t-text);
        }

        .checkout-container {
            width: 100%;
            max-width: 580px;
            background: var(--t-card);
            border-radius: 24px;
            box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.08), 0 0 1px 1px rgba(15, 23, 42, 0.04);
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .checkout-header {
            padding: 32px 32px 24px;
            text-align: center;
            border-bottom: 1px solid #f1f5f9;
        }

        .checkout-header h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--t-text);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .checkout-header p {
            color: var(--t-muted);
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .checkout-body {
            padding: 32px;
        }

        .error {
            color: #ef4444;
            background: #fef2f2;
            border: 1px solid #fecaca;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 0.85rem;
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .section-title {
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--t-muted);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Modern Stacked Plan Selection */
        .plan-stack {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 24px;
        }

        .plan-row {
            position: relative;
        }

        .plan-row input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .plan-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #ffffff;
        }

        .plan-label:hover {
            border-color: #cbd5e1;
            background: #fafafa;
        }

        .plan-row input:checked + .plan-label {
            border-color: var(--accent);
            background: var(--accent-light);
            box-shadow: 0 0 0 1px var(--accent);
        }

        .plan-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .plan-radio-bullet {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .plan-row input:checked + .plan-label .plan-radio-bullet {
            border-color: var(--accent);
        }

        .plan-row input:checked + .plan-label .plan-radio-bullet::after {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent);
            display: block;
        }

        .plan-text-wrapper {
            display: flex;
            flex-direction: column;
        }

        .plan-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--t-text);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .plan-sub {
            font-size: 0.78rem;
            color: var(--t-muted);
            margin-top: 2px;
        }

        .plan-price-label {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--t-text);
            text-align: right;
        }

        .plan-price-label span {
            font-size: 0.8rem;
            font-weight: 400;
            color: var(--t-muted);
        }

        .badge {
            font-size: 0.65rem;
            font-weight: 700;
            padding: 3px 6px;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .badge-current {
            background: rgba(2, 132, 199, 0.12);
            color: #0369a1;
        }

        /* Segmented Pill Control for Billing Cycle */
        .segmented-control {
            display: flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 14px;
            gap: 2px;
            margin-bottom: 28px;
            border: 1px solid rgba(0, 0, 0, 0.02);
        }

        .segment-option {
            flex: 1;
            position: relative;
        }

        .segment-option input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .segment-label {
            display: block;
            text-align: center;
            padding: 10px 4px;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--t-muted);
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }

        .segment-option input:checked + .segment-label {
            background: #ffffff;
            color: var(--t-text);
            box-shadow: 0 4px 10px -2px rgba(15, 23, 42, 0.08), 0 0 1px rgba(15, 23, 42, 0.1);
        }

        .discount-pill {
            display: inline-block;
            font-size: 0.65rem;
            background: #dcfce7;
            color: #15803d;
            padding: 1px 4px;
            border-radius: 4px;
            margin-left: 3px;
            font-weight: 700;
        }

        /* Stripe-like Unified Card Fields */
        .input-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: #475569;
            margin-bottom: 8px;
        }

        .form-element {
            margin-bottom: 20px;
        }

        .form-input-clean {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.92rem;
            color: var(--t-text);
            transition: all 0.2s;
            background: #ffffff;
            outline: none;
        }

        .form-input-clean:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-light);
        }

        .stripe-payment-box {
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            overflow: hidden;
            transition: all 0.2s;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2), inset 0 0 0 1px rgba(255, 255, 255, 0.05);
            position: relative;
        }

        .stripe-payment-box::after {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
            pointer-events: none;
        }

        .stripe-payment-box:focus-within {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-light), 0 10px 25px -5px rgba(15, 23, 42, 0.2);
        }

        .stripe-row-number {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 16px 16px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stripe-card-input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 1.15rem;
            font-family: 'Courier New', Courier, monospace;
            letter-spacing: 1.5px;
            color: #ffffff;
            background: transparent;
        }

        .stripe-card-input::placeholder {
            color: rgba(255, 255, 255, 0.35);
        }

        .stripe-brand-badge {
            font-size: 0.72rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 1px;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.12);
            padding: 4px 8px;
            border-radius: 6px;
            min-width: 52px;
            text-align: center;
        }

        .stripe-row-meta {
            display: flex;
        }

        .stripe-col-expiry {
            flex: 1.2;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .stripe-col-cvv {
            flex: 0.8;
            padding: 12px 16px;
            display: flex;
            align-items: center;
        }

        .stripe-meta-select {
            border: none;
            outline: none;
            background: transparent;
            font-size: 0.9rem;
            color: #ffffff;
            cursor: pointer;
            font-family: inherit;
            appearance: none;
            -webkit-appearance: none;
            padding-right: 4px;
        }

        .stripe-meta-select option {
            background: #0f172a;
            color: #ffffff;
        }

        .stripe-meta-input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 0.9rem;
            color: #ffffff;
            background: transparent;
            font-family: inherit;
        }

        .stripe-meta-input::placeholder {
            color: rgba(255, 255, 255, 0.35);
        }

        .security-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #166534;
            background: #f0fdf4;
            border: 1px solid #dcfce7;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 0.78rem;
            margin-top: 12px;
            font-weight: 500;
        }

        /* Compact Summary Box */
        .summary-box {
            background: #fafbfc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px;
            margin: 24px 0;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.88rem;
            color: var(--t-text);
            margin-bottom: 6px;
        }

        .summary-item:last-child {
            margin-bottom: 0;
            padding-top: 10px;
            border-top: 1px dashed #e2e8f0;
            font-weight: 700;
            font-size: 1rem;
        }

        .summary-desc {
            font-size: 0.78rem;
            color: var(--t-muted);
            line-height: 1.4;
            margin-top: 8px;
        }

        /* Subtle terms checkbox */
        .terms-wrapper {
            margin-bottom: 24px;
        }

        .terms-label {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            cursor: pointer;
            font-size: 0.78rem;
            color: var(--t-muted);
            line-height: 1.4;
        }

        .terms-label input {
            margin-top: 2px;
            accent-color: var(--accent);
        }

        .terms-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }

        .terms-link:hover {
            text-decoration: underline;
        }

        /* Main Pay Button */
        .btn-pay {
            width: 100%;
            background: var(--accent);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.2);
            transition: all 0.2s;
        }

        .btn-pay:hover {
            filter: brightness(0.95);
            box-shadow: 0 6px 16px rgba(2, 132, 199, 0.3);
        }

        .btn-pay:active {
            transform: scale(0.98);
        }

        /* Confirmation Backdrop & Modal */
        .confirm-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            z-index: 9999;
            padding: 24px;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .confirm-backdrop.is-open {
            display: flex;
        }

        .confirm-modal {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
            border: 1px solid rgba(226, 232, 240, 0.9);
        }

        .confirm-modal-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(2, 132, 199, 0.08);
            color: #0369a1;
            font-size: 0.72rem;
            font-weight: 700;
            margin-bottom: 14px;
            text-transform: uppercase;
        }

        .confirm-modal h3 {
            color: var(--t-text);
            font-size: 1.1rem;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .confirm-modal p {
            color: var(--t-muted);
            font-size: 0.85rem;
            line-height: 1.5;
            margin-bottom: 16px;
        }

        .confirm-modal-summary {
            padding: 12px 14px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #334155;
            font-size: 0.8rem;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .confirm-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-modal {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-modal-cancel {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-modal-cancel:hover {
            background: #e2e8f0;
        }

        .btn-modal-confirm {
            background: var(--accent);
            color: white;
        }

        .btn-modal-confirm:hover {
            filter: brightness(0.95);
        }

        /* Spin Animation */
        @keyframes spin { 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="checkout-container">
        <div class="checkout-header">
            <h1>
                <span class="material-symbols-rounded" style="color: var(--accent); font-size: 28px;">verified_user</span>
                MicroFin Checkout
            </h1>
            <p>Set up billing for <?php echo htmlspecialchars($tenant_name); ?> to activate your platform access.</p>
        </div>

        <div class="checkout-body">
            <?php if ($error): ?>
                <div class="error">
                    <span class="material-symbols-rounded" style="font-size: 20px; color: #ef4444; flex-shrink: 0;">error</span>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST">
                <!-- 1. Subscription Plan Selection -->
                <div class="section-title">
                    <span>Select Subscription Plan</span>
                    <span style="font-size: 0.72rem; font-weight: 500; text-transform: none; color: var(--accent); cursor: pointer;" id="view-plan-features-btn">View features</span>
                </div>

                <div class="plan-stack">
                    <?php foreach ($plan_catalog as $plan_name => $plan_meta): ?>
                        <?php
                        $plan_id = 'plan_' . strtolower(preg_replace('/[^a-z0-9]+/i', '_', $plan_name));
                        $clients_label = ((int)$plan_meta['max_clients'] < 0)
                            ? 'Unlimited Clients'
                            : number_format((int)$plan_meta['max_clients']) . ' Max Clients';
                        $users_label = ((int)$plan_meta['max_users'] < 0)
                            ? 'Unlimited Users'
                            : number_format((int)$plan_meta['max_users']) . ' Max Users';
                        $is_application_plan = $plan_name === $application_plan_tier;
                        ?>
                        <div class="plan-row">
                            <input
                                type="radio"
                                name="subscription_plan"
                                id="<?php echo htmlspecialchars($plan_id); ?>"
                                value="<?php echo htmlspecialchars($plan_name); ?>"
                                <?php echo $selected_plan_tier === $plan_name ? 'checked' : ''; ?>
                            >
                            <label class="plan-label" for="<?php echo htmlspecialchars($plan_id); ?>">
                                <div class="plan-info">
                                    <div class="plan-radio-bullet"></div>
                                    <div class="plan-text-wrapper">
                                        <span class="plan-name">
                                            <?php echo htmlspecialchars($plan_name); ?>
                                            <?php if ($is_application_plan): ?>
                                                <span class="badge badge-current">Applied</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="plan-sub"><?php echo htmlspecialchars($clients_label); ?> &bull; <?php echo htmlspecialchars($users_label); ?></span>
                                    </div>
                                </div>
                                <div class="plan-price-label">
                                    ₱<?php echo number_format((float)$plan_meta['price'], 0); ?><span>/mo</span>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- 2. Billing Cycle Toggle -->
                <div class="section-title">Select Billing Interval</div>
                <div class="segmented-control">
                    <div class="segment-option">
                        <input type="radio" name="billing_cycle" id="cycle_monthly" value="Monthly" <?php echo $selected_billing_cycle === 'Monthly' ? 'checked' : ''; ?>>
                        <label class="segment-label" for="cycle_monthly">
                            Monthly<?php if ($selected_billing_cycle === 'Monthly'): ?><span class="badge badge-current" style="font-size: 0.58rem; padding: 1px 4px; vertical-align: middle; margin-left: 2px; text-transform: uppercase;">Applied</span><?php endif; ?>
                        </label>
                    </div>
                    <div class="segment-option">
                        <input type="radio" name="billing_cycle" id="cycle_quarterly" value="Quarterly" <?php echo $selected_billing_cycle === 'Quarterly' ? 'checked' : ''; ?>>
                        <label class="segment-label" for="cycle_quarterly">
                            Quarterly <span class="discount-pill">10% Off</span><?php if ($selected_billing_cycle === 'Quarterly'): ?><span class="badge badge-current" style="font-size: 0.58rem; padding: 1px 4px; vertical-align: middle; margin-left: 2px; text-transform: uppercase;">Applied</span><?php endif; ?>
                        </label>
                    </div>
                    <div class="segment-option">
                        <input type="radio" name="billing_cycle" id="cycle_yearly" value="Yearly" <?php echo $selected_billing_cycle === 'Yearly' ? 'checked' : ''; ?>>
                        <label class="segment-label" for="cycle_yearly">
                            Yearly <span class="discount-pill">20% Off</span><?php if ($selected_billing_cycle === 'Yearly'): ?><span class="badge badge-current" style="font-size: 0.58rem; padding: 1px 4px; vertical-align: middle; margin-left: 2px; text-transform: uppercase;">Applied</span><?php endif; ?>
                        </label>
                    </div>
                </div>

                <!-- 3. Billing Info & Credit Card -->
                <div class="section-title">Billing &amp; Payment</div>

                <div class="form-element">
                    <label class="input-label" for="billing_company_name">Company Entity Name</label>
                    <input type="text" name="billing_company_name" id="billing_company_name" class="form-input-clean" value="<?php echo htmlspecialchars($tenant_name); ?>" placeholder="ABC Lending Corporation" required>
                </div>

                <div class="form-element">
                    <label class="input-label" for="card_number">Card Details</label>
                    <div class="stripe-payment-box">
                        <div class="stripe-row-number">
                            <input type="text" name="card_number" id="card_number" class="stripe-card-input" placeholder="4242 4242 4242 4242" maxlength="24" required oninput="formatCardNumber(this); updateCardPreview();">
                            <div class="stripe-brand-badge" id="preview-brand">CARD</div>
                            <input type="hidden" name="card_brand" id="card_brand" value="">
                        </div>
                        <div class="stripe-row-meta">
                            <div class="stripe-col-expiry">
                                <span style="font-size: 0.8rem; color: #94a3b8; margin-right: 4px;">Expiry:</span>
                                <select name="exp_month" id="exp_month" class="stripe-meta-select" required onchange="updateCardPreview();">
                                    <option value="" disabled selected>MM</option>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo $m; ?>"><?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span style="color: #cbd5e1; font-size: 0.85rem;">/</span>
                                <select name="exp_year" id="exp_year" class="stripe-meta-select" required onchange="updateCardPreview();">
                                    <option value="" disabled selected>YY</option>
                                    <?php for ($y = $current_year; $y <= $current_year + 15; $y++): ?>
                                        <option value="<?php echo $y; ?>"><?php echo substr($y, -2); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="stripe-col-cvv">
                                <input type="password" name="cvv" id="cvv" class="stripe-meta-input" placeholder="CVV" maxlength="4" required>
                            </div>
                        </div>
                    </div>

                    <div class="security-badge">
                        <span class="material-symbols-rounded" style="font-size: 16px; color: #166534;">lock</span>
                        <span>Secured with AES-256 standard encryption. Your CVC is never stored.</span>
                    </div>
                </div>

                <!-- 4. Order Summary -->
                <div class="summary-box">
                    <div class="summary-item">
                        <span style="color: var(--t-muted);" id="summary-label-tier">Starter Plan</span>
                        <span id="summary-value-tier">₱4,999.00</span>
                    </div>
                    <div class="summary-item">
                        <span style="color: var(--t-muted);" id="summary-label-cycle">Billing Interval</span>
                        <span id="summary-value-cycle">Monthly</span>
                    </div>
                    <div class="summary-item">
                        <span>Total Due Today</span>
                        <span id="summary-value-total" style="color: var(--accent);">₱4,999.00</span>
                    </div>
                    <div class="summary-desc" id="checkout-text">
                        Loading checkout summary details...
                    </div>
                </div>

                <!-- Terms & Agreement -->
                <div class="terms-wrapper">
                    <label class="terms-label">
                        <input type="checkbox" name="agree_billing" id="agree_billing" required>
                        <span>I authorize MicroFin to save this payment method, charge this plan immediately, and auto-renew. I agree to the <a href="#" id="open-billing-tos" class="terms-link">Billing Terms &amp; Refund Policy</a>.</span>
                    </label>
                </div>

                <!-- Action Button -->
                <button type="button" id="btn-pay-submit" class="btn-pay">
                    <span class="material-symbols-rounded" style="font-size: 20px;">lock</span>
                    <span id="pay-btn-text">Authorize &amp; Pay</span>
                </button>
                <input type="submit" id="real-submit" style="display:none;">
            </form>
        </div>
    </div>

    <!-- Processing Overlay -->
    <div id="payment-overlay" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.85); backdrop-filter:blur(8px); z-index:9998; align-items:center; justify-content:center; flex-direction:column;">
        <div style="background:#ffffff; border-radius:24px; padding:40px; text-align:center; max-width:380px; width:90%; box-shadow: 0 20px 50px rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.1);">
            <div id="pay-spinner" style="width:48px; height:48px; border:4px solid #f1f5f9; border-top-color:var(--accent); border-radius:50%; animation:spin 0.8s linear infinite; margin:0 auto 24px;"></div>
            <h3 id="pay-status-title" style="color:var(--t-text); font-size:1.15rem; font-weight:700; margin:0 0 8px;">Securing Connection...</h3>
            <p id="pay-status-sub" style="color:var(--t-muted); font-size:0.85rem; margin:0 0 24px;">Please hold, we are authorizing your payment method securely.</p>
            <div id="pay-steps" style="text-align:left; display:flex; flex-direction:column; gap:10px; border-top:1px solid #f1f5f9; padding-top:20px;">
                <div id="pstep-1" style="display:flex; align-items:center; gap:10px; color:#94a3b8; font-size:0.82rem; transition:color 0.3s;"><span style="font-size:16px;">&#9675;</span> Encrypting credit card details</div>
                <div id="pstep-2" style="display:flex; align-items:center; gap:10px; color:#94a3b8; font-size:0.82rem; transition:color 0.3s;"><span style="font-size:16px;">&#9675;</span> Validating payment method status</div>
                <div id="pstep-3" style="display:flex; align-items:center; gap:10px; color:#94a3b8; font-size:0.82rem; transition:color 0.3s;"><span style="font-size:16px;">&#9675;</span> Processing initial transaction charge</div>
                <div id="pstep-4" style="display:flex; align-items:center; gap:10px; color:#94a3b8; font-size:0.82rem; transition:color 0.3s;"><span style="font-size:16px;">&#9675;</span> Activating your administrative account</div>
            </div>
        </div>
    </div>

    <!-- Billing ToS Modal -->
    <div id="billing-tos-backdrop" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(6px); z-index:9999; overflow-y:auto; padding:40px 20px; align-items:center; justify-content:center;">
        <div style="background:#ffffff; border-radius:24px; max-width:580px; width:100%; margin:auto; padding:32px; color:var(--t-text); line-height:1.6; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); border:1px solid rgba(226,232,240,0.8); position:relative;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:1px solid #e2e8f0; padding-bottom:16px; margin-bottom:20px;">
                <div>
                    <h2 style="margin:0; font-size:1.2rem; font-weight:700; color:var(--t-text); display:flex; align-items:center; gap:8px;">
                        <span class="material-symbols-rounded" style="color:var(--accent); font-size:24px;">gavel</span>
                        Billing &amp; Subscription Terms
                    </h2>
                    <p style="font-size:0.75rem; color:var(--t-muted); margin:4px 0 0 0; font-weight:500;">Effective Date: <?php echo date('F d, Y'); ?></p>
                </div>
                <button type="button" id="close-billing-tos" style="background:#f1f5f9; border:none; cursor:pointer; font-size:1.25rem; color:#64748b; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:background 0.2s; font-weight:600;">&times;</button>
            </div>
            
            <div style="max-height:360px; overflow-y:auto; padding-right:8px; margin-bottom:24px;">
                <!-- Section 1 -->
                <div style="margin-bottom:16px; display:flex; gap:12px; align-items:flex-start;">
                    <div style="background:var(--accent-light); padding:6px; border-radius:8px; color:var(--accent);">
                        <span class="material-symbols-rounded" style="font-size:18px;">account_balance_wallet</span>
                    </div>
                    <div>
                        <h3 style="color:var(--t-text); font-size:0.88rem; font-weight:700; margin:0 0 4px 0;">1. Subscription Licensing</h3>
                        <p style="font-size:0.8rem; color:var(--t-muted); margin:0;">
                            By activating your MicroFin subscription, you are granted a non-exclusive, non-transferable, and revocable license to access our platform services based on your selected plan.
                        </p>
                    </div>
                </div>

                <!-- Section 2 -->
                <div style="margin-bottom:16px; display:flex; gap:12px; align-items:flex-start;">
                    <div style="background:var(--accent-light); padding:6px; border-radius:8px; color:var(--accent);">
                        <span class="material-symbols-rounded" style="font-size:18px;">sync_alt</span>
                    </div>
                    <div>
                        <h3 style="color:var(--t-text); font-size:0.88rem; font-weight:700; margin:0 0 4px 0;">2. Recurring Billing Cycles</h3>
                        <p style="font-size:0.8rem; color:var(--t-muted); margin:0;">
                            Your billing cycle is automatically determined by your pre-selected interval (Monthly, Quarterly, or Yearly) and charged automatically against your registered payment instrument.
                        </p>
                    </div>
                </div>

                <!-- Section 3: No refund -->
                <div style="margin-bottom:16px; display:flex; gap:12px; align-items:flex-start; background:#fff5f5; border:1px solid #fee2e2; padding:12px; border-radius:10px;">
                    <div style="background:#fee2e2; padding:6px; border-radius:8px; color:#ef4444; display:flex; align-items:center; justify-content:center;">
                        <span class="material-symbols-rounded" style="font-size:18px;">warning</span>
                    </div>
                    <div>
                        <h3 style="color:#991b1b; font-size:0.88rem; font-weight:700; margin:0 0 4px 0;">3. Refund Policy</h3>
                        <p style="font-size:0.78rem; color:#b91c1c; margin:0; line-height:1.5;">
                            <strong>All fees paid for MicroFin subscriptions are non-refundable.</strong> Since our platform services are provisioned immediately upon activation, we do not issue refunds, credits, or prorated balances for mid-cycle cancellations or unused account capacities.
                        </p>
                    </div>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid #e2e8f0; padding-top:20px;">
                <button type="button" id="close-billing-tos-cancel" style="background:#f8fafc; color:#475569; border:1px solid #cbd5e1; border-radius:8px; padding:8px 16px; font-weight:600; cursor:pointer; font-size:0.82rem; transition:all 0.2s;">Decline</button>
                <button type="button" id="close-billing-tos-btn" style="background:var(--accent); color:#fff; border:none; border-radius:8px; padding:8px 20px; font-weight:600; cursor:pointer; font-size:0.82rem; transition:all 0.2s;">Agree &amp; Continue</button>
            </div>
        </div>
    </div>

    <!-- Plan Change Confirmation Modal -->
    <div id="plan-change-backdrop" class="confirm-backdrop" aria-hidden="true">
        <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="plan-change-title">
            <div class="confirm-modal-badge">
                <span class="material-symbols-rounded" style="font-size: 14px;">swap_horiz</span>
                Plan Change Confirmation
            </div>
            <h3 id="plan-change-title">Confirm subscription change</h3>
            <p id="plan-change-copy">You are changing the plan you selected during your application.</p>
            <div class="confirm-modal-summary" id="plan-change-summary"></div>
            <div class="confirm-modal-actions">
                <button type="button" id="plan-change-cancel" class="btn-modal btn-modal-cancel">Keep Current Plan</button>
                <button type="button" id="plan-change-confirm" class="btn-modal btn-modal-confirm">Yes, Change Plan</button>
            </div>
        </div>
    </div>

    <!-- Dynamic Inclusions Modal (View Details) -->
    <div id="plan-features-backdrop" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(6px); z-index:9999; overflow-y:auto; padding:40px 20px; align-items:center; justify-content:center;">
        <div style="background:#ffffff; border-radius:24px; max-width:480px; width:100%; margin:auto; padding:28px; color:var(--t-text); box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); border:1px solid rgba(226,232,240,0.8); position:relative;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:16px;">
                <h3 id="features-modal-title" style="margin:0; font-size:1.1rem; font-weight:700; color:var(--t-text);">Starter Plan Features</h3>
                <button type="button" id="close-features-modal" style="background:none; border:none; cursor:pointer; font-size:1.5rem; color:#64748b; font-weight:600; line-height:1;">&times;</button>
            </div>
            <div id="features-modal-body" style="max-height:300px; overflow-y:auto; display:flex; flex-direction:column; gap:10px; padding-right:4px;">
                <!-- Dyn details will be injected here -->
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tosBtn = document.getElementById('open-billing-tos');
            const tosModal = document.getElementById('billing-tos-backdrop');
            const closeTos1 = document.getElementById('close-billing-tos');
            const closeTos2 = document.getElementById('close-billing-tos-btn');
            const closeTosCancel = document.getElementById('close-billing-tos-cancel');
            
            const payBtn = document.getElementById('btn-pay-submit');
            const realSubmit = document.getElementById('real-submit');
            const overlay = document.getElementById('payment-overlay');
            const form = document.querySelector('form');
            const planChangeBackdrop = document.getElementById('plan-change-backdrop');
            const planChangeCopy = document.getElementById('plan-change-copy');
            const planChangeSummary = document.getElementById('plan-change-summary');
            const planChangeCancel = document.getElementById('plan-change-cancel');
            const planChangeConfirm = document.getElementById('plan-change-confirm');
            const applicationPlanName = <?php echo json_encode($application_plan_tier, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
            const applicationPlanAvailable = <?php echo $application_plan_is_available ? 'true' : 'false'; ?>;
            
            // Features modal selectors
            const viewPlanFeaturesBtn = document.getElementById('view-plan-features-btn');
            const planFeaturesBackdrop = document.getElementById('plan-features-backdrop');
            const closeFeaturesModal = document.getElementById('close-features-modal');
            const featuresModalTitle = document.getElementById('features-modal-title');
            const featuresModalBody = document.getElementById('features-modal-body');

            if (tosBtn) tosBtn.addEventListener('click', e => { e.preventDefault(); tosModal.style.display = 'flex'; });
            if (closeTos1) closeTos1.addEventListener('click', () => tosModal.style.display = 'none');
            if (closeTos2) closeTos2.addEventListener('click', () => tosModal.style.display = 'none');
            if (closeTosCancel) closeTosCancel.addEventListener('click', () => tosModal.style.display = 'none');
            if (tosModal) tosModal.addEventListener('click', e => { if (e.target === tosModal) tosModal.style.display = 'none'; });

            // Features modal event listeners
            if (viewPlanFeaturesBtn) {
                viewPlanFeaturesBtn.addEventListener('click', () => {
                    const activePlanName = getSelectedPlanName();
                    const activePlan = planCatalog[activePlanName];
                    if (activePlan) {
                        featuresModalTitle.textContent = `${activePlanName} Plan Features`;
                        let content = '';
                        if (activePlan.inclusions && activePlan.inclusions.length > 0) {
                            activePlan.inclusions.forEach(inc => {
                                content += `<div style="display:flex; align-items:center; gap:8px; font-size:0.85rem; color:#475569;">
                                    <span class="material-symbols-rounded" style="color:#16a34a; font-size:18px;">check_circle</span>
                                    <span>${inc}</span>
                                </div>`;
                            });
                        } else {
                            content = `<div style="color:var(--t-muted); font-size:0.85rem;">Standard administrative and operation limits apply.</div>`;
                        }
                        featuresModalBody.innerHTML = content;
                        planFeaturesBackdrop.style.display = 'flex';
                    }
                });
            }
            if (closeFeaturesModal) closeFeaturesModal.addEventListener('click', () => planFeaturesBackdrop.style.display = 'none');
            if (planFeaturesBackdrop) {
                planFeaturesBackdrop.addEventListener('click', e => {
                    if (e.target === planFeaturesBackdrop) planFeaturesBackdrop.style.display = 'none';
                });
            }

            function selectSubscriptionPlan(planName) {
                if (!planName) return false;
                const planInput = document.querySelector(`input[name="subscription_plan"][value="${planName}"]`);
                if (!planInput) return false;
                planInput.checked = true;
                updateCheckoutSummary();
                return true;
            }

            function showPlanChangeModal(currentPlanName, selectedPlanName) {
                return new Promise((resolve) => {
                    if (!planChangeBackdrop || !planChangeCopy || !planChangeSummary || !planChangeCancel || !planChangeConfirm) {
                        resolve('change-plan');
                        return;
                    }

                    const close = (result) => {
                        planChangeBackdrop.classList.remove('is-open');
                        planChangeBackdrop.setAttribute('aria-hidden', 'true');
                        document.body.style.overflow = '';
                        planChangeCancel.removeEventListener('click', onCancel);
                        planChangeConfirm.removeEventListener('click', onConfirm);
                        planChangeBackdrop.removeEventListener('click', onBackdropClick);
                        document.removeEventListener('keydown', onEscape);
                        resolve(result);
                    };

                    const onCancel = () => close('keep-current');
                    const onConfirm = () => close('change-plan');
                    const onBackdropClick = (event) => {
                        if (event.target === planChangeBackdrop) close('dismiss');
                    };
                    const onEscape = (event) => {
                        if (event.key === 'Escape') close('dismiss');
                    };

                    planChangeCopy.textContent = `You originally applied for the ${currentPlanName} plan, and you are about to activate the ${selectedPlanName} plan instead.`;
                    planChangeSummary.innerHTML = `<strong>Current plan:</strong> ${currentPlanName}<br><strong>New activation plan:</strong> ${selectedPlanName}`;
                    planChangeBackdrop.classList.add('is-open');
                    planChangeBackdrop.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';

                    planChangeCancel.addEventListener('click', onCancel);
                    planChangeConfirm.addEventListener('click', onConfirm);
                    planChangeBackdrop.addEventListener('click', onBackdropClick);
                    document.addEventListener('keydown', onEscape);
                });
            }

            if (payBtn) payBtn.addEventListener('click', async (e) => {
                if (!form.reportValidity()) return;
                e.preventDefault();

                const selectedPlanName = getSelectedPlanName();
                if (applicationPlanAvailable && applicationPlanName && selectedPlanName !== applicationPlanName) {
                    const planDecision = await showPlanChangeModal(applicationPlanName, selectedPlanName);
                    if (planDecision === 'keep-current') {
                        selectSubscriptionPlan(applicationPlanName);
                    } else if (planDecision !== 'change-plan') {
                        return;
                    }
                }

                overlay.style.display = 'flex';
                
                const steps = [
                    document.getElementById('pstep-1'),
                    document.getElementById('pstep-2'),
                    document.getElementById('pstep-3'),
                    document.getElementById('pstep-4')
                ];
                
                for(let i=0; i<steps.length; i++) {
                    await new Promise(r => setTimeout(r, 600 + Math.random()*400));
                    steps[i].style.color = '#10b981';
                    steps[i].innerHTML = '<span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;color:#10b981;">check_circle</span>' + steps[i].innerHTML.substring(steps[i].innerHTML.indexOf('</span>') + 7);
                }
                await new Promise(r => setTimeout(r, 400));
                document.getElementById('pay-spinner').style.borderColor = '#10b981';
                document.getElementById('pay-status-title').textContent = 'Payment Successful';
                document.getElementById('pay-status-title').style.color = '#10b981';
                document.getElementById('pay-status-sub').textContent = 'Redirecting to your dashboard...';
                await new Promise(r => setTimeout(r, 800));
                realSubmit.click();
            });
        });

        function formatCardNumber(input) {
            let v = input.value.replace(/\D/g, '');
            let formatted = v.match(/.{1,4}/g)?.join(' ') || v;
            input.value = formatted;
        }

        function validateExpirationDate() {
            const monthVal = document.getElementById('exp_month').value;
            const yearVal = document.getElementById('exp_year').value;
            const payBtn = document.getElementById('btn-pay-submit');
            let errorDiv = document.querySelector('.error');
            
            if (!monthVal || !yearVal) {
                return true;
            }
            
            const expMonth = parseInt(monthVal, 10);
            const expYear = parseInt(yearVal, 10);
            
            const today = new Date();
            const oneYearFromNow = new Date(today.getFullYear() + 1, today.getMonth(), 1);
            const selectedExp = new Date(expYear, expMonth - 1, 1);
            
            if (selectedExp < oneYearFromNow) {
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'error';
                    const formBody = document.querySelector('.checkout-body');
                    formBody.insertBefore(errorDiv, formBody.firstChild);
                }
                errorDiv.innerHTML = '<span class="material-symbols-rounded" style="font-size:20px;color:#ef4444;flex-shrink:0;">error</span><div>For subscription security, your card expiration date must be at least one year from now.</div>';
                errorDiv.style.display = 'flex';
                payBtn.disabled = true;
                payBtn.style.opacity = '0.5';
                payBtn.style.cursor = 'not-allowed';
                return false;
            } else {
                if (errorDiv && errorDiv.textContent.includes('expiration date')) {
                    errorDiv.style.display = 'none';
                    errorDiv.textContent = '';
                }
                payBtn.disabled = false;
                payBtn.style.opacity = '1';
                payBtn.style.cursor = 'pointer';
                return true;
            }
        }

        function updateCardPreview() {
            const numberInput = document.getElementById('card_number');
            const number = numberInput.value.replace(/\D/g, '');
            
            let brand = 'CARD';
            if (number.length > 0) {
                const first = number[0];
                const firstTwo = number.substring(0, 2);
                if (first === '4') brand = 'VISA';
                else if (['51','52','53','54','55'].includes(firstTwo)) brand = 'MC';
                else if (['34','37'].includes(firstTwo)) brand = 'AMEX';
                else if (firstTwo === '36' || firstTwo === '38') brand = 'DINERS';
            }
            document.getElementById('preview-brand').textContent = brand;
            document.getElementById('card_brand').value = brand;
            
            validateExpirationDate();
        }

        const planCatalog = <?php echo json_encode($plan_catalog, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const selectedPlanInputs = Array.from(document.querySelectorAll('input[name="subscription_plan"]'));
        const applicationPlanName = <?php echo json_encode($application_plan_tier, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const applicationPlanAvailable = <?php echo $application_plan_is_available ? 'true' : 'false'; ?>;

        function getSelectedPlanName() {
            const selectedInput = selectedPlanInputs.find((input) => input.checked);
            return selectedInput ? selectedInput.value : <?php echo json_encode($selected_plan_tier); ?>;
        }

        function updateCheckoutSummary() {
            const selectedPlanName = getSelectedPlanName();
            const selectedPlan = planCatalog[selectedPlanName];
            const mrr = Number(selectedPlan?.price || 0);
            
            const selectedCycleInput = document.querySelector('input[name="billing_cycle"]:checked');
            const cycle = selectedCycleInput ? selectedCycleInput.value : 'Monthly';

            if (!mrr || mrr <= 0) return;

            let multiplier = 1;
            let discount = 0;
            let cycleDays = 30;
            if (cycle === 'Yearly') { multiplier = 12; discount = 0.20; cycleDays = 365; }
            else if (cycle === 'Quarterly') { multiplier = 3; discount = 0.10; cycleDays = 90; }

            const subtotal = mrr * multiplier;
            const total = subtotal * (1 - discount);

            const formatNextDate = (d) => d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            const today = new Date();
            const nextBillingDate = new Date(today);
            nextBillingDate.setDate(today.getDate() + cycleDays);

            const totalFormatted = total.toLocaleString('en-US', { style: 'currency', currency: 'PHP' });
            const mrrFormatted = mrr.toLocaleString('en-US', { style: 'currency', currency: 'PHP' });

            // Update Labels
            document.getElementById('summary-label-tier').textContent = `${selectedPlanName} Plan`;
            document.getElementById('summary-value-tier').textContent = mrrFormatted;
            document.getElementById('summary-value-cycle').textContent = cycle;
            document.getElementById('summary-value-total').textContent = totalFormatted;
            
            // Update Pay button text
            document.getElementById('pay-btn-text').textContent = `Authorize & Pay ${totalFormatted}`;

            let cycleDesc = `recurring billing of <strong>${totalFormatted}</strong> every ${cycleDays} days`;
            if (cycle === 'Monthly') cycleDesc = `recurring monthly billing of <strong>${mrrFormatted}</strong>`;

            document.getElementById('checkout-text').innerHTML = `Your plan will renew on <strong>${formatNextDate(nextBillingDate)}</strong> with a ${cycleDesc}.`;
        }

        selectedPlanInputs.forEach((input) => {
            input.addEventListener('change', () => {
                updateCheckoutSummary();
            });
        });

        document.querySelectorAll('input[name="billing_cycle"]').forEach(input => {
            input.addEventListener('change', updateCheckoutSummary);
        });

        updateCheckoutSummary();
    </script>
</body>
</html>

