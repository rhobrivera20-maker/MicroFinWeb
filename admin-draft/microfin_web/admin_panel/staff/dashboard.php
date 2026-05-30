<?php
require_once "../../../microfin_backend/auth/session_auth.php";
mf_start_backend_session();
require_once "../../../microfin_backend/config/db_connect.php";
require_once "../../../microfin_backend/engines/credit_policy.php";
mf_require_tenant_session($pdo, [
    'response' => 'redirect',
    'redirect' => '../../tenant_login/login.php',
    'append_tenant_slug' => true,
]);

// 2. Authorization Check (Only Employees)
if ($_SESSION['user_type'] !== 'Employee') {
    header("Location: ../admin.php");
    exit;
}

// 3. Setup Wizard Check
$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

$check_stmt = $pdo->prepare('SELECT force_password_change, role_id, ui_theme FROM users WHERE user_id = ? AND tenant_id = ?');
$check_stmt->execute([$user_id, $tenant_id]);
$user_data = $check_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data || $user_data['force_password_change']) {
    header('Location: setup_wizard.php');
    exit;
}

$ui_theme = (($user_data['ui_theme'] ?? ($_SESSION['ui_theme'] ?? 'light')) === 'dark') ? 'dark' : 'light';
$_SESSION['ui_theme'] = $ui_theme;

// 4. Load Permissions
$role_id = $user_data['role_id'];
$perm_stmt = $pdo->prepare('
    SELECT p.permission_code 
    FROM role_permissions rp 
    JOIN permissions p ON rp.permission_id = p.permission_id 
    WHERE rp.role_id = ?
');
$perm_stmt->execute([$role_id]);
$permissions = $perm_stmt->fetchAll(PDO::FETCH_COLUMN);

function has_permission($code)
{
    global $permissions;
    return in_array($code, $permissions);
}

// Fetch Pending Applications
$pending_applications = [];
if (has_permission('VIEW_APPLICATIONS') || has_permission('MANAGE_APPLICATIONS')) {
    $apps_stmt = $pdo->prepare("
        SELECT la.application_id, la.application_number, la.requested_amount, 
               la.application_status, la.submitted_date, la.created_at,
               c.first_name, c.last_name, lp.product_name
        FROM loan_applications la
        JOIN clients c ON la.client_id = c.client_id
        JOIN loan_products lp ON la.product_id = lp.product_id
        WHERE la.tenant_id = ? AND la.application_status NOT IN ('Approved', 'Rejected', 'Cancelled', 'Withdrawn')
        ORDER BY COALESCE(la.submitted_date, la.created_at) DESC
    ");
    $apps_stmt->execute([$tenant_id]);
    $pending_applications = $apps_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Clients block has been moved to functions/db_clients.php and is only loaded on ?tab=clients


$loan_products = [];
$loan_products_stmt = $pdo->prepare("SELECT product_id, product_name, 'Loan Product' AS product_type, min_amount, max_amount, min_term_months, max_term_months, interest_rate FROM loan_products WHERE tenant_id = ? AND is_active = 1 ORDER BY product_name ASC");
$loan_products_stmt->execute([$tenant_id]);
$loan_products = $loan_products_stmt->fetchAll(PDO::FETCH_ASSOC);

$document_types = [];
$document_types_stmt = $pdo->query("SELECT document_type_id, document_name, loan_purpose, is_required FROM document_types WHERE is_active = 1 ORDER BY is_required DESC, document_name ASC");
$document_types = $document_types_stmt->fetchAll(PDO::FETCH_ASSOC);

$walk_in_policy = mf_get_tenant_credit_policy($pdo, $tenant_id);
$walk_in_employment_statuses = ['Full Time', 'Part Time', 'Contract', 'Freelancer / Gig', 'Self Employed', 'Casual / Seasonal', 'Retired / Pensioner', 'Student', 'Unemployed'];

$walk_in_gender_options = ['Male', 'Female'];
$walk_in_civil_status_options = ['Single', 'Married', 'Widowed', 'Divorced', 'Separated'];

// Fetch dynamic ID Types from system_settings
$walk_in_id_types = [];
$settings_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = 'policy_console_compliance_documents' LIMIT 1");
$settings_stmt->execute([$tenant_id]);
$docs_json = $settings_stmt->fetchColumn();
if ($docs_json) {
    $docs_policy = json_decode((string)$docs_json, true) ?: [];
    foreach ($docs_policy['document_requirements'] ?? [] as $req) {
        if (($req['category_key'] ?? '') === 'identity_document') {
            foreach ($req['document_options'] ?? [] as $opt) {
                if (!empty($opt['is_accepted'])) {
                    $walk_in_id_types[] = [
                        'value' => $opt['document_name'],
                        'label' => $opt['document_name']
                    ];
                }
            }
            break;
        }
    }
}
if (empty($walk_in_id_types)) {
    $walk_in_id_types = [
        ['value' => 'National ID (PhilID/ePhilID)', 'label' => 'National ID (PhilID/ePhilID)'],
        ['value' => 'Passport', 'label' => 'Passport'],
        ['value' => 'Driver\'s License', 'label' => 'Driver\'s License']
    ];
}

// Fetch tenant branding
$brand_stmt = $pdo->prepare('SELECT theme_primary_color, theme_secondary_color, theme_text_main, theme_text_muted, theme_bg_body, theme_bg_card, font_family, logo_path FROM tenant_branding WHERE tenant_id = ?');
$brand_stmt->execute([$tenant_id]);
$tenant_brand = $brand_stmt->fetch(PDO::FETCH_ASSOC);

$theme_color = ($tenant_brand && $tenant_brand['theme_primary_color']) ? $tenant_brand['theme_primary_color'] : '#2563eb';
$theme_sidebar = ($tenant_brand && $tenant_brand['theme_secondary_color']) ? $tenant_brand['theme_secondary_color'] : '#0f172a';
$theme_text_main = ($tenant_brand && $tenant_brand['theme_text_main']) ? $tenant_brand['theme_text_main'] : '#0f172a';
$theme_text_muted = ($tenant_brand && $tenant_brand['theme_text_muted']) ? $tenant_brand['theme_text_muted'] : '#64748b';
$theme_bg_body = ($tenant_brand && $tenant_brand['theme_bg_body']) ? $tenant_brand['theme_bg_body'] : '#f1f5f9';
$theme_bg_card = ($tenant_brand && $tenant_brand['theme_bg_card']) ? $tenant_brand['theme_bg_card'] : '#ffffff';
$theme_font = ($tenant_brand && $tenant_brand['font_family']) ? $tenant_brand['font_family'] : 'DM Sans';
$logo_path = ($tenant_brand && $tenant_brand['logo_path']) ? $tenant_brand['logo_path'] : '';

// Compute sidebar text color (auto-contrast)
function hex_is_dark($hex)
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3)
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;
    return $lum < 140;
}

function hexToRgb($hex)
{
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return "$r, $g, $b";
}

$sidebar_text = hex_is_dark($theme_sidebar) ? '#f8fafc' : '#0f172a';
$sidebar_text_muted = hex_is_dark($theme_sidebar) ? 'rgba(248,250,252,0.55)' : 'rgba(15,23,42,0.45)';
$sidebar_hover_bg = hex_is_dark($theme_sidebar) ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
$sidebar_active_bg = $theme_color . '22';

$avatar_stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ? AND tenant_id = ?");
$avatar_stmt->execute([$_SESSION['user_id'], $_SESSION['tenant_id']]);
$avatar_user = $avatar_stmt->fetch(PDO::FETCH_ASSOC);

$f = trim($avatar_user['first_name'] ?? '');
$l = trim($avatar_user['last_name'] ?? '');
$adminDisplay = (!empty($f) || !empty($l)) ? trim("$f $l") : ($_SESSION['username'] ?? 'User');
$avF = !empty($f) ? mb_substr($f, 0, 1) : mb_substr($adminDisplay, 0, 1);
$avL = !empty($l) ? mb_substr($l, -1) : mb_substr($adminDisplay, -1);
$initials = mb_strtoupper($avF . $avL);

$name_parts = explode(' ', $adminDisplay);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($ui_theme); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($_SESSION['tenant_name']); ?> — Employee Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($theme_font); ?>:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="../admin.css">
    <!-- html2pdf for PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* ── CSS Variables (tenant-driven) ── */
        :root {
            --primary-color:
                <?php echo htmlspecialchars($theme_color); ?>
            ;
            --primary-rgb:
                <?php echo hexToRgb($theme_color); ?>
            ;

            /* Backwards compatibility with dashboard existing sizes/names */
            --brand: var(--primary-color);
            --brand-light: rgba(var(--primary-rgb), 0.1);
            --brand-mid: rgba(var(--primary-rgb), 0.3);
            --body-bg: var(--bg-body);
            --card-bg: var(--bg-card);
            --text: var(--text-main);
            --muted: var(--text-muted);
            --border: var(--border-color);
            --font: var(--font-family);

            --sidebar-bg:
                <?php echo htmlspecialchars($theme_bg_card); ?>
            ;
            --sidebar-text: var(--text-main);
            --sidebar-muted: var(--text-muted);
            --sidebar-hover: var(--bg-body);
            --sidebar-active: rgba(var(--primary-rgb), 0.1);
            --bg-body:
                <?php echo htmlspecialchars($theme_bg_body); ?>
            ;
            --bg-card:
                <?php echo htmlspecialchars($theme_bg_card); ?>
            ;
            --text-main:
                <?php echo htmlspecialchars($theme_text_main); ?>
            ;
            --text-muted:
                <?php echo htmlspecialchars($theme_text_muted); ?>
            ;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, .07), 0 1px 2px rgba(0, 0, 0, .05);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, .08), 0 2px 6px rgba(0, 0, 0, .04);
            --shadow-lg: 0 10px 40px rgba(0, 0, 0, .12);
            --sidebar-w: 260px;
            --header-h: 70px;
            --radius: 12px;
            --radius-sm: 8px;
            --font-family: '<?php echo htmlspecialchars($theme_font); ?>', sans-serif;
            --mono: 'JetBrains Mono', monospace;
            --transition: .18s ease;
        }

        [data-theme="dark"] {
            --bg-body: #0b1220;
            --bg-card: #111827;
            --sidebar-bg: #111827;
            --text-main: #e5e7eb;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --sidebar-text: #cbd5e1;
            --sidebar-active-bg: rgba(var(--primary-rgb), 0.24);

            --body-bg: var(--bg-body);
            --card-bg: var(--bg-card);
            --text: var(--text-main);
            --muted: var(--text-muted);
            --border: var(--border-color);
        }

        /* ── Scrollbar Styling ── */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            border: 2px solid transparent;
            background-clip: padding-box;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.2);
            background-clip: padding-box;
        }
        [data-theme="dark"] ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
        }
        [data-theme="dark"] ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        /* Firefox */
        * {
            scrollbar-width: thin;
            scrollbar-color: rgba(0, 0, 0, 0.1) transparent;
        }
        [data-theme="dark"] * {
            scrollbar-color: rgba(255, 255, 255, 0.1) transparent;
        }

        /* ── Reset & Base ── */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            font-size: 14px;
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font);
            background: var(--body-bg);
            color: var(--text);
            display: flex;
            min-height: 100vh;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            overflow-y: auto;
            transition: transform var(--transition);
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 20px 18px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, .07);
        }

        .logo-mark {
            width: 38px;
            height: 38px;
            border-radius: 9px;
            background: var(--brand);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }

        .logo-mark img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .logo-mark .ms {
            color: #fff;
            font-size: 20px;
        }

        .logo-text {
            overflow: hidden;
        }

        .logo-text h2 {
            font-size: .9rem;
            font-weight: 600;
            color: var(--sidebar-text);
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .logo-text p {
            font-size: .72rem;
            color: var(--sidebar-muted);
        }

        .nav-section {
            padding: 12px 10px 4px;
        }

        .nav-label {
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--sidebar-muted);
            padding: 0 8px;
            margin-bottom: 4px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            border-radius: var(--radius-sm);
            color: var(--sidebar-muted);
            text-decoration: none;
            font-size: .85rem;
            font-weight: 500;
            cursor: pointer;
            transition: background var(--transition), color var(--transition);
            position: relative;
        }

        .nav-item:hover {
            background: var(--sidebar-hover);
            color: var(--sidebar-text);
        }

        .nav-item.active {
            background: var(--sidebar-active);
            color: var(--brand);
        }

        .nav-item.active .ms {
            color: var(--brand);
        }

        .nav-item .ms {
            font-size: 19px;
            flex-shrink: 0;
            transition: color var(--transition);
        }

        .nav-badge {
            margin-left: auto;
            background: var(--brand);
            color: #fff;
            font-size: .65rem;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 99px;
            min-width: 18px;
            text-align: center;
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 12px 10px;
            border-top: 1px solid rgba(255, 255, 255, .07);
        }

        /* ── Main Layout ── */
        .main-wrap {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            min-width: 0;
        }

        /* ── Top Header ── */
        .topbar {
            height: var(--header-h);
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 0 24px;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .topbar-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text);
            flex: 1;
        }

        .topbar-title span {
            color: var(--muted);
            font-weight: 400;
            font-size: .85rem;
            margin-left: 6px;
        }

        .icon-btn {
            width: 34px;
            height: 34px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--muted);
            transition: background var(--transition), color var(--transition);
        }

        .icon-btn:hover {
            background: var(--brand-light);
            color: var(--brand);
            border-color: var(--brand-mid);
        }

        .icon-btn .ms {
            font-size: 18px;
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 5px 10px 5px 5px;
            border: 1px solid var(--border);
            border-radius: 99px;
            cursor: pointer;
            background: transparent;
            transition: background var(--transition);
        }

        .user-chip:hover {
            background: var(--brand-light);
        }

        .avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--brand);
            color: #fff;
            font-size: .7rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .user-chip-name {
            font-size: .8rem;
            font-weight: 500;
            color: var(--text);
        }

        .user-chip-role {
            font-size: .7rem;
            color: var(--muted);
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--brand);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-size: .95rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity var(--transition), transform var(--transition);
        }

        .btn-primary:hover {
            opacity: .88;
            transform: translateY(-1px);
        }

        .btn-primary .ms {
            font-size: 16px;
        }

        /* ── Content Area ── */
        .content {
            flex: 1;
            padding: 32px;
            overflow-y: auto;
            height: calc(100vh - var(--header-h));
        }

        /* ── View Sections ── */
        .view {
            display: none;
        }

        .view.active {
            display: block;
            animation: fadeIn .2s ease;
        }

        /* Hide 2FA card when not in active profile tab */
        .view:not(.active) .profile-2fa-wrapper {
            display: none !important;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ── Page Header ── */
        .page-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 22px;
        }

        .page-icon {
            width: 42px;
            height: 42px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .page-header h1 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text);
        }

        .page-header p {
            font-size: .82rem;
            color: var(--muted);
            margin-top: 1px;
        }

        .page-header-actions {
            margin-left: auto;
            display: flex;
            gap: 8px;
        }

        /* ── Cards & Stats ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            transition: box-shadow var(--transition), transform var(--transition);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            background: var(--brand-light);
            color: var(--brand);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 4px;
        }

        .stat-icon .ms {
            font-size: 24px;
        }

        .stat-label {
            font-size: .85rem;
            color: var(--text);
            font-weight: 600;
            margin-bottom: 2px;
            text-transform: none;
            letter-spacing: 0;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1.1;
        }

        .stat-value.brand {
            color: var(--brand);
        }

        .stat-sub {
            font-size: .8rem;
            color: var(--text-muted);
            margin-top: 2px;
            opacity: 0.85;
            line-height: 1.3;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h3 {
            font-size: .92rem;
            font-weight: 600;
            color: var(--text);
            flex: 1;
        }

        .card-header .ms {
            font-size: 18px;
            color: var(--brand);
        }

        /* ── Tables ── */
        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead tr {
            background: var(--body-bg);
        }

        th {
            padding: 11px 16px;
            font-size: .72rem;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .06em;
            text-align: left;
            white-space: nowrap;
        }

        td {
            padding: 12px 16px;
            font-size: .85rem;
            color: var(--text);
            border-top: 1px solid var(--border);
            vertical-align: middle;
        }

        tbody tr {
            transition: background var(--transition);
        }

        tbody tr:hover {
            background: var(--brand-light);
        }

        .td-muted {
            color: var(--muted) !important;
        }

        .td-mono {
            font-family: var(--mono);
            font-size: .8rem;
        }

        .td-bold {
            font-weight: 600;
        }

        .empty-row td {
            text-align: center;
            padding: 40px 16px;
            color: var(--muted);
        }

        /* ── Status Badges ── */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 9px;
            border-radius: 99px;
            font-size: .72rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-green {
            background: #dcfce7;
            color: #166534;
        }

        .badge-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-amber {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-blue {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-purple {
            background: #ede9fe;
            color: #5b21b6;
        }

        .badge-gray {
            background: #f1f5f9;
            color: #475569;
        }

        .badge-teal {
            background: #ccfbf1;
            color: #115e59;
        }

        [data-theme="dark"] .badge-green {
            background: #14532d40;
            color: #86efac;
        }

        [data-theme="dark"] .badge-red {
            background: #7f1d1d40;
            color: #fca5a5;
        }

        [data-theme="dark"] .badge-amber {
            background: #78350f40;
            color: #fcd34d;
        }

        [data-theme="dark"] .badge-blue {
            background: #1e3a5f40;
            color: #93c5fd;
        }

        [data-theme="dark"] .badge-purple {
            background: #3b076440;
            color: #c4b5fd;
        }

        [data-theme="dark"] .badge-gray {
            background: #1e293b;
            color: #94a3b8;
        }

        /* ── Buttons ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            font-size: .95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition);
            border: none;
            font-family: inherit;
        }

        .btn .ms {
            font-size: 15px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: .85rem;
        }

        .btn-outline {
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text);
        }

        .btn-outline:hover {
            background: var(--brand-light);
            border-color: var(--brand-mid);
            color: var(--brand);
        }

        .btn-brand {
            background: var(--brand);
            color: #fff;
        }

        .btn-brand:hover {
            opacity: .85;
        }

        .btn-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-danger:hover {
            background: #fca5a5;
        }

        .btn-success {
            background: #dcfce7;
            color: #166534;
        }

        .btn-success:hover {
            background: #bbf7d0;
        }

        .table-icon-btn {
            width: 34px;
            height: 34px;
            padding: 0;
            justify-content: center;
        }

        .status-stack {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .status-note {
            font-size: .72rem;
            color: var(--muted);
            line-height: 1.35;
        }

        /* ── Filter Tabs ── */
        .filter-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 5px 14px;
            border-radius: 99px;
            font-size: .78rem;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid var(--border);
            background: var(--card-bg);
            color: var(--muted);
            transition: all var(--transition);
        }

        .filter-tab:hover {
            border-color: var(--brand);
            color: var(--brand);
        }

        .filter-tab.active {
            background: var(--brand);
            color: #fff;
            border-color: var(--brand);
        }

        /* ── Search Bar ── */
        .search-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }

        .search-input-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            max-width: 340px;
        }

        .search-input-wrap .ms {
            color: var(--muted);
            font-size: 17px;
        }

        .search-input-wrap input {
            border: none;
            outline: none;
            background: none;
            color: var(--text);
            font-family: var(--font);
            font-size: .85rem;
            flex: 1;
        }

        /* ── Welcome Card (Home) ── */
        .welcome-banner {
            background: linear-gradient(135deg, var(--brand) 0%, color-mix(in srgb, var(--brand) 60%, #000) 100%);
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 16px;
            position: relative;
            overflow: hidden;
            color: #fff;
        }

        .welcome-banner h1 {
            font-size: 1.4rem;
            font-weight: 700;
            margin: 0 0 4px 0;
        }

        .welcome-banner p {
            font-size: 0.85rem;
            margin: 0 0 8px 0;
            opacity: 0.95;
        }

        .welcome-banner-meta {
            display: flex;
            gap: 16px;
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .06);
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            bottom: -50px;
            right: 80px;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .04);
        }

        .welcome-banner h1 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .welcome-banner p {
            font-size: .85rem;
            opacity: .8;
        }

        .welcome-banner-meta {
            display: flex;
            gap: 20px;
            margin-top: 14px;
        }

        .welcome-meta-item {
            font-size: .78rem;
            opacity: .75;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .welcome-meta-item .ms {
            font-size: 15px;
        }

        /* ── Activity Feed ── */
        .activity-item {
            display: flex;
            gap: 10px;
            padding: 10px 16px;
            border-top: 1px solid var(--border);
        }

        .activity-item:first-child {
            border-top: none;
        }

        .activity-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--brand);
            flex-shrink: 0;
            margin-top: 6px;
        }

        .activity-text {
            font-size: .83rem;
            color: var(--text);
            line-height: 1.5;
        }

        .activity-time {
            font-size: .72rem;
            color: var(--muted);
            margin-top: 2px;
        }

        /* ── Modal ── */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65) !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 500;
            padding: 20px;
        }

        .modal-backdrop.open {
            display: flex;
        }

        #dashboardPopupModal {
            z-index: 1050 !important;
        }

        .modal-backdrop.top {
            align-items: flex-start;
            padding-top: 48px;
        }

        .modal {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-height: 88vh;
            overflow-y: auto;
            animation: modalIn .22s ease;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(.96) translateY(10px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-sm {
            max-width: 440px;
        }

        .modal-md {
            max-width: 560px;
        }

        .modal-lg {
            max-width: 700px;
        }

        .modal-xl {
            max-width: 820px;
        }

        .modal-header {
            padding: 18px 22px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h3 {
            font-size: 1rem;
            font-weight: 600;
            flex: 1;
        }

        .modal-body {
            padding: 22px;
        }

        .modal-footer {
            padding: 14px 22px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
        }

        .popup-message {
            font-size: .9rem;
            line-height: 1.6;
            color: var(--text);
            white-space: pre-line;
        }

        .popup-input-wrap {
            margin-top: 18px;
        }

        .popup-input-error {
            display: none;
            margin-top: 6px;
            font-size: .74rem;
            color: #b91c1c;
            font-weight: 600;
        }

        /* ── Forms ── */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .form-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 14px;
        }

        .form-full {
            grid-column: 1 / -1;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group label {
            font-size: .78rem;
            font-weight: 600;
            color: var(--muted);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 8px 11px;
            background: var(--body-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-family: var(--font);
            font-size: .85rem;
            transition: border-color var(--transition), box-shadow var(--transition);
            outline: none;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px var(--brand-mid);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 70px;
        }

        .form-hint {
            font-size: .72rem;
            color: var(--muted);
        }

        .section-sep {
            grid-column: 1 / -1;
            border: none;
            border-top: 1px solid var(--border);
            margin: 6px 0 2px;
        }

        .section-label {
            grid-column: 1 / -1;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--brand);
            padding-top: 4px;
        }

        /* ── Document Checklist ── */
        .doc-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            max-height: 240px;
            overflow-y: auto;
            padding: 4px 0;
        }

        .doc-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--body-bg);
        }

        .doc-item input[type=checkbox] {
            width: 15px;
            height: 15px;
            accent-color: var(--brand);
            flex-shrink: 0;
        }

        .doc-item-label {
            flex: 1;
            font-size: .82rem;
            color: var(--text);
        }

        .doc-badge {
            font-size: .65rem;
            background: var(--brand-light);
            color: var(--brand);
            padding: 1px 6px;
            border-radius: 99px;
            font-weight: 600;
        }

        .doc-item input[type=file] {
            font-size: .75rem;
            color: var(--muted);
            flex: 0 0 auto;
        }

        /* ── Loading Spinner ── */
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--border);
            border-top-color: var(--brand);
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .loading-row td {
            text-align: center;
            padding: 32px;
        }

        /* ── Amortization table compact ── */
        .sched-table td,
        .sched-table th {
            padding: 8px 12px;
        }

        .sched-table td {
            font-size: .8rem;
        }

        /* ── Reports grid ── */
        .reports-kpi {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .kpi-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px 18px;
            box-shadow: var(--shadow);
        }

        .kpi-label {
            font-size: .72rem;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 6px;
        }

        .kpi-val {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
        }

        /* ── Read-only detail views ── */
        .detail-sections {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .detail-section {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--body-bg);
            padding: 16px 18px;
        }

        .detail-section-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
        }

        .detail-section-header .ms {
            color: var(--brand);
            font-size: 18px;
        }

        .detail-section-title {
            font-size: .74rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--brand);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px 16px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }

        .detail-item-full {
            grid-column: 1 / -1;
        }

        .detail-label {
            font-size: .72rem;
            color: var(--muted);
        }

        .detail-value {
            font-size: .85rem;
            font-weight: 500;
            color: var(--text);
            line-height: 1.45;
            word-break: break-word;
        }

        .detail-value.is-empty {
            color: var(--muted);
            font-style: italic;
            font-weight: 400;
        }

        .detail-table {
            overflow-x: auto;
        }

        /* ── Two-col layout for reports breakdown ── */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-wrap {
                margin-left: 0;
            }

            .form-grid,
            .form-grid-3,
            .two-col,
            .detail-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>

<body>

    <!-- ════════════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════════════ -->
    <?php
    $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'home';
    include __DIR__ . '/components/sidebar.php';
    ?>

    <!-- ════════════════════════════════════════════
     MAIN
═══════════════════════════════════════════════ -->
    <div class="main-wrap">
        <?php include __DIR__ . '/components/header.php'; ?>

        <div class="content">

            <!-- ── HOME ── -->
            <section id="home" class="view <?= $current_tab === 'home' || !$current_tab ? 'active' : '' ?>">
                <div class="welcome-banner">
                    <h1>Good <?php echo date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening'); ?>,
                        <?php echo htmlspecialchars($name_parts[0]); ?>!</h1>
                    <p><?php echo date('l, F j, Y'); ?> · <?php echo htmlspecialchars($_SESSION['tenant_name']); ?></p>
                    <div class="welcome-banner-meta">
                        <span class="welcome-meta-item"><span class="material-symbols-rounded ms">schedule</span>
                            <?php echo date('h:i A'); ?></span>
                        <span class="welcome-meta-item"><span class="material-symbols-rounded ms">badge</span>
                            <?php echo htmlspecialchars($_SESSION['role'] ?? 'Employee'); ?></span>
                    </div>
                </div>

                <div class="stats-grid" id="homeStats">
                    <?php if (has_permission('VIEW_APPLICATIONS')): ?>
                        <div class="stat-card">
                            <div class="stat-icon"><span class="material-symbols-rounded ms">pending_actions</span></div>
                            <div class="stat-label">Pending Applications</div>
                            <div class="stat-value brand" id="statPendingApps"><?php echo count($pending_applications); ?>
                            </div>
                            <div class="stat-sub">Needs your review</div>
                        </div>
                    <?php endif; ?>
                    <?php if (has_permission('VIEW_LOANS')): ?>
                        <div class="stat-card">
                            <div class="stat-icon"><span class="material-symbols-rounded ms">account_balance_wallet</span>
                            </div>
                            <div class="stat-label">Active Loans</div>
                            <div class="stat-value" id="statActiveLoans">—</div>
                            <div class="stat-sub">Currently disbursed</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><span class="material-symbols-rounded ms">warning</span></div>
                            <div class="stat-label">Overdue Loans</div>
                            <div class="stat-value" style="color:#ef4444;" id="statOverdueLoans">—</div>
                            <div class="stat-sub">Needs follow-up</div>
                        </div>
                    <?php endif; ?>
                    <?php if (has_permission('PROCESS_PAYMENTS')): ?>
                        <div class="stat-card">
                            <div class="stat-icon"><span class="material-symbols-rounded ms">payments</span></div>
                            <div class="stat-label">Today's Collections</div>
                            <div class="stat-value brand" id="statTodayCollections">—</div>
                            <div class="stat-sub">Posted payments today</div>
                        </div>
                    <?php endif; ?>
                    <?php if (has_permission('VIEW_CLIENTS')): ?>
                        <div class="stat-card">
                            <div class="stat-icon"><span class="material-symbols-rounded ms">verified_user</span></div>
                            <div class="stat-label">Active Clients</div>
                            <div class="stat-value" id="statActiveClients">0</div>
                            <div class="stat-sub">Ready for servicing</div>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                    <?php if (has_permission('VIEW_APPLICATIONS')): ?>
                        <div class="card">
                            <div class="card-header">
                                <span class="material-symbols-rounded ms">list_alt</span>
                                <h3>Recent Applications</h3>
                                <a class="btn btn-sm btn-outline" data-target="applications" href="#applications"
                                    style="text-decoration:none;">View All</a>
                            </div>
                            <div>
                                <?php if (empty($pending_applications)): ?>
                                    <div style="padding:24px;text-align:center;color:var(--muted);font-size:.85rem;">No pending
                                        applications.</div>
                                <?php else: ?>
                                    <?php foreach (array_slice($pending_applications, 0, 5) as $app): ?>
                                        <div class="activity-item">
                                            <div class="activity-dot"></div>
                                            <div>
                                                <div class="activity-text">
                                                    <strong><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></strong>
                                                    — <?php echo htmlspecialchars($app['product_name']); ?>
                                                    <strong
                                                        style="color:var(--brand);">₱<?php echo number_format($app['requested_amount'], 0); ?></strong>
                                                </div>
                                                <div class="activity-time">
                                                    <?php echo htmlspecialchars($app['application_status']); ?> ·
                                                    <?php echo date('M j', strtotime($app['submitted_date'] ?? $app['created_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <span class="material-symbols-rounded ms">notifications</span>
                            <h3>Quick Actions</h3>
                        </div>
                        <div style="padding:16px;display:flex;flex-direction:column;gap:8px;">
                            <?php if (has_permission('CREATE_CLIENTS')): ?>
                                <!-- Hidden: Walk-in Customer button -->
                                <!--
                                <button class="btn btn-outline" onclick="openWalkInModal()"
                                    style="justify-content:flex-start;width:100%;">
                                    <span class="material-symbols-rounded ms">person_add</span> Walk-in Customer
                                </button>
                                -->
                            <?php endif; ?>
                            <?php if (has_permission('PROCESS_PAYMENTS')): ?>
                                <button class="btn btn-outline" onclick="navTo('payments');loadPayments();"
                                    style="justify-content:flex-start;width:100%;">
                                    <span class="material-symbols-rounded ms">receipt_long</span> View Receipts &
                                    Transactions
                                </button>
                            <?php endif; ?>
                            <?php if (has_permission('VIEW_LOANS')): ?>
                                <button class="btn btn-outline" onclick="navTo('loans')"
                                    style="justify-content:flex-start;width:100%;">
                                    <span class="material-symbols-rounded ms">real_estate_agent</span> View All Loans
                                </button>
                            <?php endif; ?>
                            <?php if (has_permission('VIEW_REPORTS')): ?>
                                <button class="btn btn-outline" onclick="navTo('reports');loadReports('month');"
                                    style="justify-content:flex-start;width:100%;">
                                    <span class="material-symbols-rounded ms">bar_chart</span> Monthly Report
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── CLIENTS (Moved to ?tab=clients) ── -->

            <!-- ── CREDIT ACCOUNTS ── -->
            <?php if (has_permission('VIEW_CREDIT_ACCOUNTS') || has_permission('VIEW_CLIENTS') || has_permission('CREATE_CLIENTS')): ?>
                <section id="credit-accounts" class="view">
                    <div class="page-header">
                        <div class="page-icon" style="background:rgba(236,72,153,.12);color:#ec4899;">
                            <span class="material-symbols-rounded ms" style="font-size:22px;">credit_card</span>
                        </div>
                        <div>
                            <h1>Credit Accounts Management</h1>
                            <p>Review borrower credit limits, score profile, and upgrade readiness before staff confirms any increase.</p>
                        </div>
                    </div>

                    <div class="card">
                        <div style="display:flex;gap:8px;margin-bottom:16px;">
                            <input type="text" id="creditAccountSearch" placeholder="Search by name, email, or contact number..." style="flex:1;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--body-bg);color:var(--text);font-family:var(--font);font-size:.85rem;outline:none;" oninput="onCreditAccountSearchInput()">
                        </div>

                        <div style="overflow-x:auto;">
                            <table style="width:100%;border-collapse:collapse;">
                                <thead>
                                    <tr style="background:var(--border-bg);transition:background 0.15s;" onmouseover="this.style.background='color-mix(in srgb, var(--brand), transparent 85%)'" onmouseout="this.style.background='var(--border-bg)'">
                                        <th style="padding:12px 16px;text-align:left;font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600;cursor:pointer;user-select:none;" onclick="sortCreditAccounts('client_name')">Client <span id="sort-client_name"></span></th>
                                        <th style="padding:12px 16px;text-align:left;font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600;cursor:pointer;user-select:none;" onclick="sortCreditAccounts('credit_score')">Credit Score <span id="sort-credit_score"></span></th>
                                        <th style="padding:12px 16px;text-align:left;font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600;cursor:pointer;user-select:none;" onclick="sortCreditAccounts('credit_limit')">Credit Limit <span id="sort-credit_limit"></span></th>
                                        <th style="padding:12px 16px;text-align:left;font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600;cursor:pointer;user-select:none;" onclick="sortCreditAccounts('status')">Status <span id="sort-status"></span></th>
                                        <th style="padding:12px 16px;text-align:center;font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="creditAccountsTbody">
                                    <tr class="loading-row"><td colspan="6"><span class="spinner"></span></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (has_permission('VIEW_APPLICATIONS') || has_permission('MANAGE_APPLICATIONS')): ?>
                <section id="applications" class="view">
                    <div class="page-header">
                        <div class="page-icon" style="background:rgba(59,130,246,.1);color:#3b82f6;">
                            <span class="material-symbols-rounded ms" style="font-size:22px;">description</span>
                        </div>
                        <div>
                            <h1>Loan Applications</h1>
                            <p>Review submitted loan applications, inspect documents, and make the lending decision.</p>
                        </div>
                        <div class="page-header-actions">
                            <button class="btn btn-outline"
                                onclick="loadApps(document.querySelector('#appFilterTabs .filter-tab.active')?.dataset?.status||'all')">
                                <span class="material-symbols-rounded ms">refresh</span> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="filter-tabs" id="appFilterTabs">
                        <button class="filter-tab active" data-status="all" onclick="loadApps('all',this)">All</button>
                        <button class="filter-tab" data-status="Under Review" onclick="loadApps('Under Review',this)">Under
                            Review</button>
                        <button class="filter-tab" data-status="Rejected"
                            onclick="loadApps('Rejected',this)">Rejected</button>
                    </div>
                    <div class="card">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>App #</th>
                                        <th>Client</th>
                                        <th>Product</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="appsTbody">
                                    <tr class="loading-row">
                                        <td colspan="7"><span class="spinner"></span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- ── LOANS ── -->
            <?php if (has_permission('VIEW_LOANS') || has_permission('CREATE_LOANS') || has_permission('APPROVE_LOANS')): ?>
                <section id="loans" class="view">
                    <div class="page-header">
                        <div class="page-icon" style="background:rgba(79,70,229,.1);color:#4f46e5;">
                            <span class="material-symbols-rounded ms" style="font-size:22px;">real_estate_agent</span>
                        </div>
                        <div>
                            <h1>Loans Management</h1>
                            <p>Handle approved applications waiting for disbursement, then monitor released loans and
                                payment schedules.</p>
                        </div>
                        <div class="page-header-actions">
                            <button class="btn btn-outline"
                                onclick="loadLoans(getActiveLoanFilter(), document.querySelector('#loanFilterTabs .filter-tab.active'))">
                                <span class="material-symbols-rounded ms">refresh</span> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card" style="margin-bottom:16px;">
                        <div class="card-header">
                            <span class="material-symbols-rounded ms">payments</span>
                            <h3>Awaiting Disbursement</h3>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>App #</th>
                                        <th>Client</th>
                                        <th>Product</th>
                                        <th>Approved Amount</th>
                                        <th>Approved On</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="loanDisbursementTbody">
                                    <tr class="loading-row">
                                        <td colspan="6"><span class="spinner"></span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="filter-tabs" id="loanFilterTabs">
                        <button class="filter-tab active" data-status="all" onclick="loadLoans('all',this)">All</button>
                        <button class="filter-tab" data-status="Active" onclick="loadLoans('Active',this)">Active</button>
                        <button class="filter-tab" data-status="Overdue"
                            onclick="loadLoans('Overdue',this)">Overdue</button>
                        <button class="filter-tab" data-status="Fully Paid" onclick="loadLoans('Fully Paid',this)">Fully
                            Paid</button>
                    </div>
                    <div class="card">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Loan #</th>
                                        <th>Client</th>
                                        <th>Product</th>
                                        <th>Principal</th>
                                        <th>Balance</th>
                                        <th>Next Due</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="loansTbody">
                                    <tr class="loading-row">
                                        <td colspan="8"><span class="spinner"></span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- ── PAYMENTS ── -->
            <?php if (has_permission('PROCESS_PAYMENTS')): ?>
                <section id="payments" class="view">
                    <div class="page-header">
                        <div class="page-icon" style="background:rgba(16,185,129,.1);color:#10b981;">
                            <span class="material-symbols-rounded ms" style="font-size:22px;">receipt_long</span>
                        </div>
                        <div>
                            <h1>Receipts & Transactions</h1>
                            <p>Today's collections: <strong id="todayTotal" style="color:var(--brand);">₱0.00</strong></p>
                        </div>
                    </div>
                    <div class="stats-grid" style="margin-bottom:16px;">
                        <div class="stat-card">
                            <div class="stat-icon"><span class="material-symbols-rounded ms">payments</span></div>
                            <div class="stat-label">Today's Collections</div>
                            <div class="stat-value brand" id="receiptTodayTotal">â€”</div>
                            <div class="stat-sub">Sum of posted receipts today</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><span class="material-symbols-rounded ms">receipt</span></div>
                            <div class="stat-label">Today's Transactions</div>
                            <div class="stat-value" id="receiptTodayCount">0</div>
                            <div class="stat-sub">Transactions posted today</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><span class="material-symbols-rounded ms">history</span></div>
                            <div class="stat-label">Latest Posting</div>
                            <div class="stat-value" id="receiptLatestPosted">â€”</div>
                            <div class="stat-sub">Most recent transaction date</div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Receipt #</th>
                                        <th>Transaction Ref</th>
                                        <th>Client</th>
                                        <th>Loan #</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="paymentsTbody">
                                    <tr class="loading-row">
                                        <td colspan="8"><span class="spinner"></span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (has_permission('VIEW_USERS')): ?>
                <section id="users" class="view">
                    <div class="page-header">
                        <div class="page-icon" style="background:rgba(245,158,11,.12);color:#f59e0b;">
                            <span class="material-symbols-rounded ms" style="font-size:22px;">badge</span>
                        </div>
                        <div>
                            <h1>Team Directory</h1>
                            <p>View staff accounts assigned to this tenant. Account creation and edits stay in the Admin
                                panel.</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Staff Member</th>
                                        <th>Email</th>
                                        <th>Department</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="usersTbody">
                                    <tr class="loading-row">
                                        <td colspan="5"><span class="spinner"></span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- ── REPORTS ── -->
            <?php if (has_permission('VIEW_REPORTS')): ?>
                <section id="reports" class="view">
                    <div class="page-header">
                        <div class="page-icon" style="background:rgba(168,85,247,.1);color:#a855f7;">
                            <span class="material-symbols-rounded ms" style="font-size:22px;">analytics</span>
                        </div>
                        <div>
                            <h1>Reports & Analytics</h1>
                            <p>Financial performance and portfolio overview.</p>
                        </div>
                        <div class="page-header-actions" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            <div>
                                <button class="filter-tab active" onclick="loadReports('week');setActiveTab(this)">Week</button>
                                <button class="filter-tab" onclick="loadReports('month');setActiveTab(this)">Month</button>
                                <button class="filter-tab" onclick="loadReports('year');setActiveTab(this)">Year</button>
                            </div>
                            <button class="btn btn-outline btn-sm" onclick="exportReportsPDF()" style="height:32px;">
                                <span class="material-symbols-rounded ms" style="font-size:18px;">download</span> Export PDF
                            </button>
                        </div>
                    </div>
                    <div id="reportsBody">
                        <div style="text-align:center;padding:40px;color:var(--muted);"><span class="spinner"></span></div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- ── CLIENTS ── -->
            <section id="clients" class="view <?= $current_tab === 'clients' ? 'active' : '' ?>">
                <div class="page-header">
                    <div class="page-icon" style="background:rgba(16,185,129,.1);color:#10b981;">
                        <span class="material-symbols-rounded ms" style="font-size:22px;">group</span>
                    </div>
                    <div>
                        <h1>Client Management</h1>
                        <p>View, search, and manage all registered borrowers.</p>
                    </div>
                </div>

                <div class="card">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Full Name</th>
                                    <th>Monthly Income</th>
                                    <th>Employment Status</th>
                                    <th>Created At</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                require_once __DIR__ . '/functions/db_clients.php';
                                $clientsResult = get_all_tenant_clients($pdo, $tenant_id);
                                $clients = $clientsResult['data'];
                                ?>
                                <?php if (empty($clients)): ?>
                                    <tr class="empty-row">
                                        <td colspan="5">No clients found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($clients as $client): ?>
                                        <tr>
                                            <td class="td-bold">
                                                <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                                            </td>
                                            <td class="td-mono">
                                                ₱<?php echo number_format($client['monthly_income'] ?? 0, 2); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($client['employment_status'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="td-muted">
                                                <?php
                                                $date = $client['created_at'] ?? $client['registration_date'] ?? null;
                                                if ($date) {
                                                    echo date('m/d/Y', strtotime($date));
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline table-icon-btn" title="View Details" onclick="viewClient(<?php echo htmlspecialchars(json_encode($client)); ?>)">
                                                    <span class="material-symbols-rounded ms">visibility</span>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ── TEAM DIRECTORY ── -->
            <section id="team" class="view <?= $current_tab === 'team' ? 'active' : '' ?>">
                <?php
                if (!has_permission('VIEW_USERS') && !has_permission('CREATE_USERS')) {
                    echo "<div style='padding: 32px;text-align:center;'><h2 style='color:#f87171;'>Access Denied</h2><p>You do not have permission to view the team directory.</p></div>";
                } else {
                    require_once __DIR__ . '/functions/db_team.php';
                    $teamData = get_tenant_staff($pdo, $_SESSION['tenant_id']);
                    $all_staff = $teamData['data'];
                    $available_roles = get_tenant_roles($pdo, $_SESSION['tenant_id']);
                ?>
                <div class="page-header">
                    <div class="page-icon" style="background:rgba(99,102,241,.1);color:#6366f1;">
                        <span class="material-symbols-rounded ms" style="font-size:22px;">badge</span>
                    </div>
                    <div>
                        <h1>Team Directory</h1>
                        <p>View and manage employee accounts and administrative access.</p>
                    </div>
                    <div class="page-header-actions">
                        <?php if (has_permission('CREATE_USERS')): ?>
                            <button class="btn btn-primary" onclick="openModal('inviteStaffModal')">
                                <span class="material-symbols-rounded ms">person_add</span> Invite Staff
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card" style="margin-top: 24px;">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Staff Name</th>
                                    <th>Email Address</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <?php if (has_permission('CREATE_USERS')): ?>
                                        <th style="text-align: center;">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_staff)): ?>
                                    <tr class="empty-row"><td colspan="5">No staff members found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($all_staff as $staff): ?>
                                        <tr>
                                            <td class="td-bold">
                                                <?php echo htmlspecialchars($staff['full_name'] ?? '—'); ?>
                                            </td>
                                            <td class="td-muted">
                                                <?php echo htmlspecialchars($staff['email'] ?? '—'); ?>
                                            </td>
                                            <td>
                                                <span style="background: rgba(59, 130, 246, 0.1); padding: 4px 10px; border-radius: 6px; color: #3b82f6; font-size: 0.75rem; font-weight: 600;">
                                                    <?php echo htmlspecialchars($staff['role_name'] ?? 'No Role Assigned'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $val = trim((string)($staff['status'] ?? 'Unknown'));
                                                $st = strtolower($val);
                                                $bg = ''; $fg = '';
                                                if (in_array($st, ['active', 'verified'])) { $bg = '#dcfce7'; $fg = '#166534'; }
                                                elseif (in_array($st, ['suspended', 'locked', 'inactive'])) { $bg = '#fee2e2'; $fg = '#991b1b'; }
                                                else { $bg = '#f1f5f9'; $fg = '#475569'; }
                                                echo "<span style='display:inline-flex;align-items:center;padding:3px 10px;border-radius:99px;font-size:.7rem;font-weight:600;background:{$bg};color:{$fg};'>" 
                                                   . htmlspecialchars($val ?: 'Unknown') . "</span>";
                                                ?>
                                            </td>
                                            <?php if (has_permission('CREATE_USERS')): ?>
                                                <td style="text-align: center;">
                                                    <?php if ((int)$staff['user_id'] === (int)$_SESSION['user_id']): ?>
                                                        <span style="font-size: 0.8rem; color: var(--muted); font-weight: 600;">You</span>
                                                    <?php elseif ($staff['user_type'] === 'Admin' || $staff['role_name'] === 'Admin'): ?>
                                                        <span style="font-size: 0.8rem; color: var(--muted);">Restricted</span>
                                                    <?php else: ?>
                                                        <?php if (strcasecmp($staff['status'], 'Suspended') === 0 || strcasecmp($staff['status'], 'Locked') === 0 || strcasecmp($staff['status'], 'Inactive') === 0): ?>
                                                            <button class="btn btn-sm btn-outline" style="color: #10b981; border-color: #10b981;" onclick="activateStaff(<?php echo (int)$staff['user_id']; ?>, '<?php echo (int)($staff['role_id'] ?? 0); ?>')">Activate</button>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-outline" onclick="openManageStaffModal(<?php echo (int)$staff['user_id']; ?>, '<?php echo htmlspecialchars($staff['full_name'] ?? ''); ?>', '<?php echo (int)($staff['role_id'] ?? 0); ?>', '<?php echo htmlspecialchars($staff['status'] ?? ''); ?>')">Manage</button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ── INVITE STAFF MODAL ── -->
                <?php if (has_permission('CREATE_USERS')): ?>
                <div class="modal-backdrop top" id="inviteStaffModal">
                    <div class="modal" style="width: 100%; max-width: 500px; background:var(--bg-card); border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
                        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid var(--border);">
                            <h3 style="margin:0; font-size:1.2rem;">Add Staff Member</h3>
                            <span class="material-symbols-rounded ms" style="cursor:pointer; color:var(--muted);" onclick="closeModal('inviteStaffModal')">close</span>
                        </div>
                        <form method="POST" action="../admin.php">
                            <input type="hidden" name="action" value="create_staff">
                            <input type="hidden" name="create_as_admin" value="0">
                            <input type="hidden" name="redirect_url" value="dashboard.php#team">
                            <div class="modal-body" style="padding: 24px;">
                                <div class="form-group" style="margin-bottom: 1.5rem;">
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Profile Setup</label>
                                    <div class="profile-mode-toggle" style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: rgba(var(--primary-rgb), 0.05); border-radius: 8px; border: 1px solid rgba(var(--primary-rgb), 0.1);">
                                        <div>
                                            <strong id="staff-profile-mode-title" style="display: block; font-weight: 600; font-size: 0.95rem; margin-bottom: 4px;">Complete During Onboarding</strong>
                                            <span id="staff-profile-mode-description" style="font-size: 0.8rem; color: var(--text-muted); display: block; line-height: 1.4;">Only the login account is created now. The staff finishes their profile after first login.</span>
                                        </div>
                                        <label class="switch profile-mode-switch" style="transform: scale(0.9); flex-shrink: 0; margin-left: 1rem;">
                                            <input type="checkbox" id="staff-profile-mode" name="profile_mode" value="fill_now" onchange="toggleStaffProfileMode()">
                                            <span class="slider round"></span>
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group" style="margin-bottom: 1rem;">
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Email Address <span style="color:var(--danger-color);">*</span></label>
                                    <input type="email" class="form-control" name="email" id="staff-email-input" required oninput="validateStaffEmail()" onblur="validateStaffEmail()" style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; outline:none; font-family:inherit;">
                                    <div id="staff-email-error" style="color: #dc2626; font-size: 0.8rem; margin-top: 4px; display: none;"></div>
                                    <div id="staff-email-success" style="color: #16a34a; font-size: 0.8rem; margin-top: 4px; display: none;">✓ Email is available</div>
                                </div>

                                <div class="form-group" style="margin-bottom: 1rem;">
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Role <span style="color:var(--danger-color);">*</span></label>
                                    <select name="role_id" class="form-control" required style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; background: var(--bg-body); outline:none; font-family:inherit;">
                                        <option value="">Select a role...</option>
                                        <?php foreach ($available_roles as $r): ?>
                                            <?php if (strcasecmp($r['role_name'], 'Admin') === 0) continue; ?>
                                            <option value="<?php echo (int)$r['role_id']; ?>"><?php echo htmlspecialchars($r['role_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div id="staff-fill-now-fields" style="display: none; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                    <div class="form-group" style="margin-bottom: 0; grid-column: 1 / -1;">
                                        <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Username <span style="font-weight: normal; color: var(--text-muted); font-size: 0.85rem;">(Optional. Defaults to first name)</span></label>
                                        <input type="text" class="form-control" name="custom_username" id="staff-custom-username" placeholder="e.g. Maria" style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; outline:none; font-family:inherit;">
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">First Name <span style="color:var(--danger-color);">*</span></label>
                                        <input type="text" class="form-control" name="first_name" id="staff-first-name" style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; outline:none; font-family:inherit;">
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Middle Name</label>
                                        <input type="text" class="form-control" name="middle_name" id="staff-middle-name" placeholder="Optional" style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; outline:none; font-family:inherit;">
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Last Name <span style="color:var(--danger-color);">*</span></label>
                                        <input type="text" class="form-control" name="last_name" id="staff-last-name" style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; outline:none; font-family:inherit;">
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Suffix</label>
                                        <input type="text" class="form-control" name="suffix" id="staff-suffix" placeholder="Optional, e.g. Jr, Sr, III" style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; outline:none; font-family:inherit;">
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Phone Number <span style="color:var(--danger-color);">*</span></label>
                                        <input type="text" class="form-control" name="phone_number" id="staff-phone-number" style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; outline:none; font-family:inherit;">
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Date of Birth <span style="color:var(--danger-color);">*</span></label>
                                        <input type="date" class="form-control" name="date_of_birth" id="staff-date-of-birth" max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>" style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; outline:none; font-family:inherit;">
                                    </div>
                                </div>

                                <script>
                                    function toggleStaffProfileMode() {
                                        var cb = document.getElementById("staff-profile-mode");
                                        var fields = document.getElementById("staff-fill-now-fields");
                                        var fn = document.getElementById("staff-first-name");
                                        var ln = document.getElementById("staff-last-name");
                                        var pn = document.getElementById("staff-phone-number");
                                        var dob = document.getElementById("staff-date-of-birth");
                                        var title = document.getElementById("staff-profile-mode-title");
                                        var desc = document.getElementById("staff-profile-mode-description");
                                        if (cb && fields && fn && ln && pn && dob) {
                                            if (cb.checked) {
                                                fields.style.display = "grid";
                                                fn.required = true;
                                                ln.required = true;
                                                pn.required = true;
                                                dob.required = true;
                                                title.innerText = "Profile Filled Now";
                                                desc.innerText = "You will complete their profile details on their behalf.";
                                            } else {
                                                fields.style.display = "none";
                                                fn.required = false;
                                                ln.required = false;
                                                pn.required = false;
                                                dob.required = false;
                                                title.innerText = "Complete During Onboarding";
                                                desc.innerText = "Only the login account is created now. The staff finishes their profile after first login.";
                                            }
                                        }
                                    }

                                    function validateStaffEmail() {
                                        var emailInput = document.getElementById("staff-email-input");
                                        var errorDiv = document.getElementById("staff-email-error");
                                        var successDiv = document.getElementById("staff-email-success");
                                        var submitBtn = document.getElementById("add-staff-submit-btn");
                                        var email = emailInput.value.trim();

                                        if (!email) {
                                            errorDiv.style.display = "none";
                                            successDiv.style.display = "none";
                                            emailInput.style.borderColor = "";
                                            emailInput.style.backgroundColor = "";
                                            if (submitBtn) {
                                                submitBtn.disabled = false;
                                                submitBtn.style.cursor = "pointer";
                                                submitBtn.style.opacity = "1";
                                            }
                                            return;
                                        }

                                        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                                        if (!emailRegex.test(email)) {
                                            errorDiv.textContent = "Please enter a valid email address.";
                                            errorDiv.style.display = "block";
                                            successDiv.style.display = "none";
                                            emailInput.style.borderColor = "#dc2626";
                                            emailInput.style.backgroundColor = "#fef2f2";
                                            if (submitBtn) {
                                                submitBtn.disabled = true;
                                                submitBtn.style.cursor = "not-allowed";
                                                submitBtn.style.opacity = "0.5";
                                            }
                                            return;
                                        }

                                        errorDiv.textContent = "Checking...";
                                        errorDiv.style.display = "block";
                                        errorDiv.style.color = "#6b7280";
                                        successDiv.style.display = "none";
                                        emailInput.style.borderColor = "";
                                        emailInput.style.backgroundColor = "";
                                        if (submitBtn) {
                                            submitBtn.disabled = true;
                                            submitBtn.style.cursor = "not-allowed";
                                            submitBtn.style.opacity = "0.5";
                                        }

                                        var formData = new FormData();
                                        formData.append("action", "check_staff_email");
                                        formData.append("email", email);

                                        fetch("../admin.php", {
                                            method: "POST",
                                            body: formData
                                        })
                                        .then(response => response.json())
                                        .then(data => {
                                            if (data.exists) {
                                                errorDiv.textContent = "A staff or admin account with this email already exists in your organization.";
                                                errorDiv.style.display = "block";
                                                errorDiv.style.color = "#dc2626";
                                                successDiv.style.display = "none";
                                                emailInput.style.borderColor = "#dc2626";
                                                emailInput.style.backgroundColor = "#fef2f2";
                                                if (submitBtn) {
                                                    submitBtn.disabled = true;
                                                    submitBtn.style.cursor = "not-allowed";
                                                    submitBtn.style.opacity = "0.5";
                                                }
                                            } else {
                                                errorDiv.style.display = "none";
                                                successDiv.style.display = "block";
                                                emailInput.style.borderColor = "#16a34a";
                                                emailInput.style.backgroundColor = "#f0fdf4";
                                                if (submitBtn) {
                                                    submitBtn.disabled = false;
                                                    submitBtn.style.cursor = "pointer";
                                                    submitBtn.style.opacity = "1";
                                                }
                                            }
                                        })
                                        .catch(error => {
                                            errorDiv.style.display = "none";
                                            successDiv.style.display = "none";
                                            emailInput.style.borderColor = "";
                                            emailInput.style.backgroundColor = "";
                                            if (submitBtn) {
                                                submitBtn.disabled = false;
                                                submitBtn.style.cursor = "pointer";
                                                submitBtn.style.opacity = "1";
                                            }
                                        });
                                    }
                                </script>
                            </div>
                            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 12px; padding: 20px 24px; border-top: 1px solid var(--border);">
                                <button type="button" class="btn btn-secondary" onclick="closeModal('inviteStaffModal')" style="padding: 10px 20px;">Cancel</button>
                                <button type="submit" id="add-staff-submit-btn" class="btn btn-primary" style="padding: 10px 24px;">Add Staff</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ── MANAGE STAFF MODAL ── -->
                <?php if (has_permission('CREATE_USERS')): ?>
                <div class="modal-backdrop top" id="manageStaffModal">
                    <div class="modal" style="width: 100%; max-width: 400px; background:var(--bg-card); border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
                        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid var(--border);">
                            <h3 style="margin:0; font-size:1.2rem;">Manage Staff</h3>
                            <span class="material-symbols-rounded ms" style="cursor:pointer; color:var(--muted);" onclick="closeModal('manageStaffModal')">close</span>
                        </div>
                        <div class="modal-body" style="padding: 24px;">
                            <p id="manageStaffNameDisplay" style="font-weight: 600; margin-top:0; margin-bottom: 20px; font-size: 1rem; color: var(--text-color);"></p>
                            <form id="manageStaffForm" style="display: flex; flex-direction: column; gap: 16px;">
                                <input type="hidden" id="manage_user_id">
                                
                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Update Role</label>
                                    <select id="manage_role_id" required style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; background: var(--bg-body); outline:none; font-family:inherit;">
                                        <option value="">Select a role...</option>
                                        <?php foreach ($available_roles as $r): ?>
                                            <?php if (strcasecmp($r['role_name'], 'Admin') === 0) continue; ?>
                                            <option value="<?php echo (int)$r['role_id']; ?>"><?php echo htmlspecialchars($r['role_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--muted); margin-bottom:6px;">Update Status</label>
                                    <select id="manage_status" required style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; background: var(--bg-body); outline:none; font-family:inherit;">
                                        <option value="Active">Active</option>
                                        <option value="Suspended">Suspend</option>
                                    </select>
                                </div>
                                
                                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 16px;">
                                    <button type="button" class="btn btn-secondary" onclick="closeModal('manageStaffModal')" style="padding: 10px 20px;">Cancel</button>
                                    <button type="submit" class="btn btn-primary" id="btnSubmitManage" style="padding: 10px 24px;">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php } ?>
            </section>

            <!-- ── PROFILE ── -->
            <section id="profile" class="view <?= $current_tab === 'profile' ? 'active' : '' ?>">
                <?php
                require_once __DIR__ . '/functions/db_profile.php';
                $user_profile = get_user_profile($pdo, $_SESSION['user_id'], $_SESSION['tenant_id']);

                if (!$user_profile) {
                    echo "<div style='padding: 32px;text-align:center;'><h2 style='color:#f87171;'>Error Loading Profile</h2><p>Could not fetch your profile data.</p></div>";
                } else {
                    $initials = strtoupper(substr(trim($user_profile['first_name'] ?? ''), 0, 1) . substr(trim($user_profile['last_name'] ?? ''), 0, 1));
                    if (trim($initials) === '') {
                        $initials = strtoupper(substr(trim($user_profile['username'] ?? ''), 0, 2));
                    }
                ?>
                <div class="page-header">
                    <div class="page-icon" style="background:rgba(59,130,246,.1);color:#3b82f6;">
                        <span class="material-symbols-rounded ms" style="font-size:22px;">manage_accounts</span>
                    </div>
                    <div>
                        <h1>My Profile</h1>
                        <p>Manage your personal information and security settings.</p>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px;">
                    <!-- Personal Information Card -->
                    <div class="card" style="padding: 16px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border);">
                            <div class="avatar" style="width: 48px; height: 48px; font-size: 1.2rem; background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white;">
                                <?php echo $initials; ?>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 1rem; color: var(--text);">
                                    <?php echo htmlspecialchars(trim($user_profile['first_name'] . ' ' . $user_profile['last_name'])) ?: htmlspecialchars($user_profile['username']); ?>
                                </h3>
                                <p style="margin: 2px 0 0; font-size: 0.75rem; color: var(--text-muted);">
                                    <?php echo htmlspecialchars($user_profile['role_name'] ?? $user_profile['user_type']); ?>
                                </p>
                            </div>
                        </div>

                        <form id="profileForm">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div>
                                    <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 4px; display: block;">First Name</label>
                                    <input type="text" id="prof_first_name" required value="<?php echo htmlspecialchars($user_profile['first_name'] ?? ''); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-body); color: var(--text); font-size: 0.85rem;">
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 4px; display: block;">Last Name</label>
                                    <input type="text" id="prof_last_name" required value="<?php echo htmlspecialchars($user_profile['last_name'] ?? ''); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-body); color: var(--text); font-size: 0.85rem;">
                                </div>
                            </div>
                            <div style="margin-top: 12px;">
                                <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 4px; display: block;">Username</label>
                                <input type="text" id="prof_username" required placeholder="Choose a unique username" value="<?php echo htmlspecialchars($user_profile['username'] ?? ''); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-body); color: var(--text); font-size: 0.85rem;">
                            </div>
                            <div style="margin-top: 12px;">
                                <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 4px; display: block;">Email Address</label>
                                <div style="display: flex; gap: 6px;">
                                    <input type="email" id="prof_email" readonly data-current="<?php echo htmlspecialchars($user_profile['email'] ?? ''); ?>" value="<?php echo htmlspecialchars($user_profile['email'] ?? ''); ?>" style="flex: 1; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-body); color: var(--text-muted); font-size: 0.85rem;">
                                    <button type="button" class="btn btn-secondary" onclick="openEmailChangeModal()" style="padding: 8px 12px; font-size: 0.8rem;">Change</button>
                                </div>
                            </div>
                            <div style="margin-top: 12px;">
                                <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 4px; display: block;">Contact Number</label>
                                <input type="text" id="prof_contact_number" placeholder="e.g. +63 912 345 6789" value="<?php echo htmlspecialchars($user_profile['phone_number'] ?? ''); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-body); color: var(--text); font-size: 0.85rem;">
                            </div>
                            <div style="margin-top: 16px; display: flex; justify-content: space-between; align-items: center;">
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    Member since <?php 
                                        $member_date = !empty($user_profile['hire_date']) ? $user_profile['hire_date'] : $user_profile['created_at'];
                                        echo date('F j, Y', strtotime($member_date)); 
                                    ?>
                                </div>
                                <button type="submit" class="btn btn-primary" id="btnSaveProfile" style="padding: 8px 16px; font-size: 0.85rem;">Save Changes</button>
                            </div>
                        </form>
                    </div>

                    <!-- Security Settings Card -->
                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <!-- Change Password -->
                        <div class="card" style="padding: 16px;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                                <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; display: flex; align-items: center; justify-content: center;">
                                    <span class="material-symbols-rounded ms" style="font-size: 18px;">lock</span>
                                </div>
                                <div>
                                    <h3 style="margin: 0; font-size: 0.9rem; color: var(--text);">Change Password</h3>
                                </div>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <div>
                                    <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 4px; display: block;">Current Password</label>
                                    <input type="password" id="current_password" placeholder="Enter current password" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-body); color: var(--text); font-size: 0.85rem;">
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 4px; display: block;">New Password</label>
                                    <input type="password" id="new_password" placeholder="Min 8 characters" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-body); color: var(--text); font-size: 0.85rem;">
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 4px; display: block;">Confirm Password</label>
                                    <input type="password" id="confirm_password" placeholder="Confirm new password" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-body); color: var(--text); font-size: 0.85rem;">
                                </div>
                                <div id="password_validation_msg" style="font-size: 0.75rem; color: var(--text-muted);"></div>
                                <button type="button" id="btnChangePassword" class="btn btn-primary" style="width: 100%; padding: 8px; font-size: 0.85rem;" disabled>Update Password</button>
                            </div>
                        </div>

                        <!-- 2FA Settings -->
                        <div class="card profile-2fa-wrapper" id="profile-2fa-wrapper" style="display: none;">
                            <?php
                                $two_fa_enabled = (int) ($user_profile['two_fa_enabled'] ?? 0) === 1;
                                $two_fa_endpoint = '../../auth/two_fa_endpoint.php';
                                include dirname(dirname(__DIR__)) . '/auth/two_fa_card.php';
                            ?>
                        </div>

                        <!-- Account Info -->
                        <div class="card" style="padding: 16px;">
                            <h3 style="margin: 0 0 12px; font-size: 0.9rem; color: var(--text);">Account Information</h3>
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <?php if (!empty($user_profile['department'])): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem;">
                                    <span style="color: var(--text-muted);">Department</span>
                                    <span style="color: var(--text); font-weight: 500;"><?php echo htmlspecialchars($user_profile['department']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($user_profile['position'])): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem;">
                                    <span style="color: var(--text-muted);">Position</span>
                                    <span style="color: var(--text); font-weight: 500;"><?php echo htmlspecialchars($user_profile['position']); ?></span>
                                </div>
                                <?php endif; ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem;">
                                    <span style="color: var(--text-muted);">User ID</span>
                                    <span style="color: var(--text); font-weight: 500; font-family: monospace;"><?php echo htmlspecialchars($user_profile['user_id'] ?? $_SESSION['user_id']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- EMAIL CHANGE MODAL -->
                <div id="emailChangeModal" class="staff-email-modal">
                    <div class="card">
                        <h3>Change Email Address</h3>

                        <!-- Step 1: Request Email -->
                        <div id="emailStep1">
                            <label>New Email Address</label>
                            <input type="email" id="new_email_input" placeholder="e.g. john@example.com">
                            <p id="email_msg"></p>

                            <div class="staff-email-modal-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeEmailModal()">Cancel</button>
                                <button type="button" id="btnSendOtp" class="btn btn-primary" disabled>Send OTP</button>
                            </div>
                        </div>

                        <!-- Step 2: Verify OTP -->
                        <div id="emailStep2" style="display:none;">
                            <p style="font-size:0.85rem; color:var(--text-muted); margin:0 0 16px;">
                                We've sent a 6-digit verification code to <strong id="sentToEmail" style="color:var(--text);"></strong>.
                            </p>
                            <label>Enter Verification Code</label>
                            <input type="text" id="otp_input" maxlength="6" style="font-size:1.1rem; letter-spacing:4px; text-align:center;">

                            <div class="staff-email-modal-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeEmailModal()">Cancel</button>
                                <button type="button" id="btnVerifyOtp" class="btn btn-primary">Verify & Update</button>
                            </div>
                        </div>

                    </div>
                </div>

                <?php } ?>
            </section>
        </div><!-- /content -->
    </div><!-- /main-wrap -->


    <!-- ════════════════════════════════════════════
     MODALS
═══════════════════════════════════════════════ -->

    <!-- Application Review Modal -->
    <div class="modal-backdrop top" id="appReviewModal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <span class="material-symbols-rounded ms" style="color:var(--brand);">description</span>
                <h3 id="appModalTitle">Application Review</h3>
                <button class="icon-btn" onclick="closeModal('appReviewModal')"><span
                        class="material-symbols-rounded ms">close</span></button>
            </div>
            <div class="modal-body" id="appModalBody">
                <div style="text-align:center;padding:32px;"><span class="spinner"></span></div>
            </div>
            <div class="modal-footer" id="appModalFooter">
                <button class="btn btn-outline" onclick="closeModal('appReviewModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Dashboard Popup Modal -->
    <div class="modal-backdrop" id="dashboardPopupModal">
        <div class="modal modal-sm">
            <div class="modal-header">
                <span class="material-symbols-rounded ms" id="dashboardPopupIcon"
                    style="color:var(--brand);">info</span>
                <h3 id="dashboardPopupTitle">Notice</h3>
                <button class="icon-btn" onclick="dismissDashboardPopup()"><span
                        class="material-symbols-rounded ms">close</span></button>
            </div>
            <div class="modal-body">
                <div class="popup-message" id="dashboardPopupMessage"></div>
                <div class="form-group popup-input-wrap" id="dashboardPopupInputWrap" style="display:none;">
                    <label id="dashboardPopupInputLabel" for="dashboardPopupInput">Details</label>
                    <textarea id="dashboardPopupInput" placeholder="" style="min-height:110px;"></textarea>
                    <div class="popup-input-error" id="dashboardPopupInputError">This field is required.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" id="dashboardPopupCancel" onclick="resolveDashboardPopup(false)"
                    style="display:none;">Cancel</button>
                <button class="btn btn-brand" id="dashboardPopupConfirm"
                    onclick="resolveDashboardPopup(true)">OK</button>
            </div>
        </div>
    </div>

    <!-- Loan Release Modal -->
    <div class="modal-backdrop" id="loanReleaseModal">
        <div class="modal modal-md">
            <div class="modal-header">
                <span class="material-symbols-rounded ms" style="color:var(--brand);">rocket_launch</span>
                <h3>Release Loan</h3>
                <button class="icon-btn" onclick="closeModal('loanReleaseModal')"><span
                        class="material-symbols-rounded ms">close</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="releaseAppId">
                <input type="hidden" id="releaseAmount">
                <input type="hidden" id="releaseDate">
                <input type="hidden" id="releaseMethod">
                <input type="hidden" id="releaseFreq">
                <input type="hidden" id="releaseRef">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Application #</label>
                        <input type="text" id="releaseAppNumber" readonly>
                    </div>
                    <div class="form-group">
                        <label>Approved Amount</label>
                        <input type="text" id="releaseAmountPreview" readonly>
                    </div>
                    <div class="form-group">
                        <label>Release Date</label>
                        <input type="text" id="releaseDatePreview" readonly>
                    </div>
                    <div class="form-group">
                        <label>Disbursement Method</label>
                        <input type="text" id="releaseMethodPreview" readonly>
                    </div>
                    <div class="form-group">
                        <label>Payment Frequency</label>
                        <input type="text" id="releaseFreqPreview" readonly>
                    </div>
                    <div class="form-group">
                        <label>Disbursement Reference</label>
                        <input type="text" id="releaseRefPreview" readonly>
                    </div>
                    <div class="form-group form-full">
                        <small style="display:block;color:var(--muted);font-size:.78rem;line-height:1.5;">These release
                            details are filled automatically from the approved application and current system
                            defaults.</small>
                    </div>
                    <div class="form-group form-full" id="withdrawalDetailsGroup" style="display:none;">
                        <label style="font-weight:600;color:var(--brand);">Withdrawal Details</label>
                        <div id="withdrawalDetailsContent" style="background:var(--body-bg);padding:12px;border-radius:8px;font-size:.85rem;"></div>
                    </div>
                    <div class="form-group form-full">
                        <label>Notes (optional)</label>
                        <textarea id="releaseNotes" placeholder="Add internal release notes if needed."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('loanReleaseModal')">Cancel</button>
                <button class="btn btn-brand" onclick="submitLoanRelease()">
                    <span class="material-symbols-rounded ms">rocket_launch</span> Release Loan
                </button>
            </div>
        </div>
    </div>

    <!-- Loan Detail Modal -->
    <div class="modal-backdrop top" id="loanDetailModal">
        <div class="modal modal-xl">
            <div class="modal-header">
                <span class="material-symbols-rounded ms" style="color:var(--brand);">real_estate_agent</span>
                <h3 id="loanDetailTitle">Loan Details</h3>
                <button class="icon-btn" onclick="closeModal('loanDetailModal')"><span
                        class="material-symbols-rounded ms">close</span></button>
            </div>
            <div class="modal-body" id="loanDetailBody">
                <div style="text-align:center;padding:32px;"><span class="spinner"></span></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('loanDetailModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Credit Score Calculation Modal -->
    <div class="modal-backdrop top" id="creditScoreModal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <span class="material-symbols-rounded ms" style="color:var(--brand);">credit_score</span>
                <h3 id="creditScoreModalTitle">Credit Score Calculation</h3>
                <button class="icon-btn" onclick="closeModal('creditScoreModal')"><span
                        class="material-symbols-rounded ms">close</span></button>
            </div>
            <div class="modal-body" id="creditScoreModalBody">
                <div style="text-align:center;padding:32px;"><span class="spinner"></span></div>
            </div>
            <div class="modal-footer" id="creditScoreModalFooter">
                <button class="btn btn-outline" onclick="closeModal('creditScoreModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Post Payment Modal -->
    <div class="modal-backdrop" id="paymentModal">
        <div class="modal modal-md">
            <div class="modal-header">
                <span class="material-symbols-rounded ms" style="color:#10b981;">add_card</span>
                <h3>Post Payment</h3>
                <button class="icon-btn" onclick="closeModal('paymentModal')"><span
                        class="material-symbols-rounded ms">close</span></button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group form-full">
                        <label>Select Loan</label>
                        <select id="payLoanId" onchange="onPayLoanChange()">
                            <option value="">— Loading loans… —</option>
                        </select>
                        <p class="form-hint" id="payLoanInfo"></p>
                    </div>
                    <div class="form-group">
                        <label>Payment Amount (PHP)</label>
                        <input type="number" id="payAmount" step="0.01" min="1">
                    </div>
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select id="payMethod">
                            <option>Cash</option>
                            <option>GCash</option>
                            <option>Bank Transfer</option>
                            <option>Check</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Payment Date</label>
                        <input type="date" id="payDate">
                    </div>
                    <div class="form-group">
                        <label>OR / Receipt #</label>
                        <input type="text" id="payOR">
                    </div>
                    <div class="form-group">
                        <label>Reference # (GCash/Bank)</label>
                        <input type="text" id="payRef">
                    </div>
                    <div class="form-group form-full">
                        <label>Remarks</label>
                        <input type="text" id="payRemarks">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('paymentModal')">Cancel</button>
                <button class="btn btn-brand" onclick="submitPayment()">
                    <span class="material-symbols-rounded ms">check</span> Post Payment
                </button>
            </div>
        </div>
    </div>

    <!-- Client Detail Modal -->
    <div class="modal-backdrop top" id="clientDetailModal">
        <div class="modal modal-xl">
            <div class="modal-header">
                <span class="material-symbols-rounded ms" style="color:#10b981;">person</span>
                <h3 id="clientDetailTitle">Client Profile</h3>
                <button class="icon-btn" onclick="closeModal('clientDetailModal')"><span
                        class="material-symbols-rounded ms">close</span></button>
            </div>
            <div class="modal-body" id="clientDetailBody">
                <div style="text-align:center;padding:32px;"><span class="spinner"></span></div>
            </div>
            <div class="modal-footer" id="clientDetailFooter">
                <button class="btn btn-outline" onclick="closeModal('clientDetailModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════ -->
    <script src="script.js"></script>
    <script>
        const USER_PERMISSIONS = <?php echo json_encode($permissions ?? []); ?>;
        function hasPermission(code) {
            return USER_PERMISSIONS.includes(code);
        }

        // ── Utilities ──────────────────────────────────────────────
        function debounce(fn, ms) { return () => { clearTimeout(_debounceTimer); _debounceTimer = setTimeout(fn, ms); }; }
        function fmt(n) { return '₱' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        function fmtDate(d) { if (!d) return '—'; const dt = new Date(d); return isNaN(dt.getTime()) ? d : dt.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: '2-digit' }); }
        function todayIsoDate() { return new Date().toISOString().slice(0, 10); }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            })[char]);
        }
        function isBlank(value) { return value === null || value === undefined || String(value).trim() === ''; }
        function parseJsonObject(value) {
            if (!value) return {};
            if (typeof value === 'object') return value;
            try {
                const parsed = JSON.parse(value);
                if (typeof parsed === 'string') return parseJsonObject(parsed);
                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch (_) {
                return {};
            }
        }
        function buildDisbursementReference(appId, releaseDate = '') {
            const datePart = String(releaseDate || todayIsoDate()).replace(/[^0-9]/g, '');
            const safeDate = datePart.length === 8 ? datePart : todayIsoDate().replace(/-/g, '');
            const safeId = String(parseInt(appId || 0, 10) || 0).padStart(6, '0');
            return `DISB-${safeDate}-${safeId}`;
        }
        function resolveApprovedDisbursementAmount(app = {}) {
            const approved = parseFloat(app.approved_amount || 0);
            const requested = parseFloat(app.requested_amount || 0);
            return approved > 0 ? approved : requested;
        }
        function resolveReleaseMethod(app = {}) {
            const data = parseJsonObject(app.application_data);
            const preferred = String(data.disbursement_method || data.release_method || 'Cash').trim();
            return ['Cash', 'Check', 'Bank Transfer', 'GCash'].includes(preferred) ? preferred : 'Cash';
        }
        function resolveWithdrawalDetails(app = {}) {
            const data = parseJsonObject(app.application_data);
            const method = String(data.disbursement_method || data.release_method || 'Cash').trim();
            let details = '';
            
            if (method === 'GCash') {
                const gcashNumber = data.gcash_number || '';
                if (gcashNumber) details = `<strong>GCash Number:</strong> ${escapeHtml(gcashNumber)}`;
            } else if (method === 'Bank Transfer') {
                const bankName = data.bank_name || '';
                const accountNumber = data.account_number || '';
                const accountName = data.account_name || '';
                if (bankName || accountNumber) {
                    details = [];
                    if (bankName) details.push(`<strong>Bank:</strong> ${escapeHtml(bankName)}`);
                    if (accountNumber) details.push(`<strong>Account:</strong> ${escapeHtml(accountNumber)}`);
                    if (accountName) details.push(`<strong>Account Name:</strong> ${escapeHtml(accountName)}`);
                    details = details.join('<br>');
                }
            } else if (method === 'Cash Pickup') {
                details = 'Client will pick up cash at branch. Valid ID required.';
            } else if (method === 'Check') {
                details = 'Check will be issued in client\'s name.';
            }
            
            return details;
        }
        function resolveReleasePaymentFrequency(app = {}) {
            const data = parseJsonObject(app.application_data);
            const preferred = String(data.payment_frequency || data.repayment_frequency || data.frequency || 'Monthly').trim();
            return ['Daily', 'Weekly', 'Bi-Weekly', 'Monthly'].includes(preferred) ? preferred : 'Monthly';
        }
        function formatTextValue(value, emptyLabel = 'Not provided') {
            if (isBlank(value)) return `<span class="detail-value is-empty">${escapeHtml(emptyLabel)}</span>`;
            return escapeHtml(value);
        }
        function formatMoneyValue(value, emptyLabel = 'Not provided') {
            const amount = parseFloat(value);
            if (!Number.isFinite(amount) || amount <= 0) return `<span class="detail-value is-empty">${escapeHtml(emptyLabel)}</span>`;
            return fmt(amount);
        }
        function formatDateValue(value, emptyLabel = 'Not provided') {
            if (isBlank(value) || value === '1990-01-01') return `<span class="detail-value is-empty">${escapeHtml(emptyLabel)}</span>`;
            return escapeHtml(fmtDate(value));
        }
        function documentHref(doc = {}) {
            const directUrl = String(doc?.file_url || '').trim();
            if (directUrl !== '') {
                return directUrl;
            }

            const filePath = String(doc?.file_path || '').replace(/^\/+/, '').trim();
            return filePath ? `../../../${filePath}` : '';
        }
        function renderDetailItem(label, valueHtml, full = false) {
            return `<div class="detail-item${full ? ' detail-item-full' : ''}"><div class="detail-label">${escapeHtml(label)}</div><div class="detail-value">${valueHtml}</div></div>`;
        }
        function joinAddress(parts) {
            return parts.map(part => String(part ?? '').trim()).filter(Boolean).join(', ');
        }
        function sourceBadge(userType) {
            return userType === 'Client'
                ? '<span class="badge badge-blue">Mobile App</span>'
                : '<span class="badge badge-gray">Walk-in / Staff</span>';
        }
        function applicationMonitorState(status = '') {
            const map = {
                'Draft': 'Draft',
                'Submitted': 'Under Review',
                'Pending Review': 'Under Review',
                'Under Review': 'Under Review',
                'Document Verification': 'Under Review',
                'Credit Investigation': 'Under Review',
                'For Approval': 'Under Review',
                'Reviewed': 'Under Review',
                'Approved': 'Approved',
                'Rejected': 'Rejected',
                'Cancelled': 'Rejected',
                'Withdrawn': 'Rejected'
            };
            return map[status] || status || 'Under Review';
        }
        function applicationMonitorBadge(status = '') {
            const monitor = applicationMonitorState(status);
            return `<div class="status-stack">${badge(monitor)}</div>`;
        }
        function matchesApplicationFilter(rawStatus, filter = 'all') {
            if (!filter || filter === 'all') return true;
            return applicationMonitorState(rawStatus) === filter;
        }
        function getActiveAppFilter() {
            return document.querySelector('#appFilterTabs .filter-tab.active')?.dataset?.status || 'all';
        }

        function getActiveLoanFilter() {
            return document.querySelector('#loanFilterTabs .filter-tab.active')?.dataset?.status || 'all';
        }

        function getRequestErrorMessage(error, fallback = 'Something went wrong.') {
            if (error && typeof error.message === 'string' && error.message.trim() !== '') {
                return error.message.trim();
            }
            return fallback;
        }

        async function fetchJsonStrict(url, options = {}) {
            const response = await fetch(url, options);
            const raw = await response.text();
            const normalizedRaw = raw.replace(/^\uFEFF/, '');
            let payload = {};

            if (normalizedRaw.trim() !== '') {
                try {
                    payload = JSON.parse(normalizedRaw);
                } catch (_) {
                    throw new Error('The server returned an invalid response. Please refresh and try again.');
                }
            }

            if (!response.ok) {
                throw new Error(payload.message || `Request failed with status ${response.status}.`);
            }

            if (!payload || typeof payload !== 'object') {
                throw new Error('The server returned an empty response. Please refresh and try again.');
            }

            return payload;
        }

        function badge(s) {
            const map = {
                'Active': 'badge-green',
                'Approved': 'badge-green',
                'Posted': 'badge-green',
                'Verified': 'badge-green',
                'Fully Paid': 'badge-blue',
                'Under Review': 'badge-blue',
                'For Approval': 'badge-purple',
                'Credit Investigation': 'badge-purple',
                'Document Verification': 'badge-purple',
                'Overdue': 'badge-red',
                'Rejected': 'badge-red',
                'Bounced': 'badge-red',
                'Blacklisted': 'badge-red',
                'Cancelled': 'badge-gray',
                'Withdrawn': 'badge-gray',
                'Inactive': 'badge-gray',
                'Suspended': 'badge-gray',
                'Draft': 'badge-amber',
                'Submitted': 'badge-amber',
                'Pending Review': 'badge-amber',
                'Pending': 'badge-amber',
                'Partially Paid': 'badge-amber',
            };
            const cls = map[s] || 'badge-gray';
            return `<span class="badge ${cls}">${s}</span>`;
        }

        function openModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.add('open');
        }

        function closeModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.remove('open');
        }

        function dashboardPopupVariantConfig(variant = 'info') {
            const map = {
                info: { icon: 'info', color: 'var(--brand)', buttonClass: 'btn btn-brand', title: 'Notice' },
                success: { icon: 'check_circle', color: '#16a34a', buttonClass: 'btn btn-success', title: 'Success' },
                warning: { icon: 'warning', color: '#b45309', buttonClass: 'btn btn-brand', title: 'Please Review' },
                danger: { icon: 'error', color: '#991b1b', buttonClass: 'btn btn-danger', title: 'Action Needed' }
            };
            return map[variant] || map.info;
        }

        function getDashboardPopupElements() {
            return {
                modal: document.getElementById('dashboardPopupModal'),
                icon: document.getElementById('dashboardPopupIcon'),
                title: document.getElementById('dashboardPopupTitle'),
                message: document.getElementById('dashboardPopupMessage'),
                inputWrap: document.getElementById('dashboardPopupInputWrap'),
                inputLabel: document.getElementById('dashboardPopupInputLabel'),
                input: document.getElementById('dashboardPopupInput'),
                inputError: document.getElementById('dashboardPopupInputError'),
                cancel: document.getElementById('dashboardPopupCancel'),
                confirm: document.getElementById('dashboardPopupConfirm')
            };
        }

        function dismissDashboardPopup() {
            resolveDashboardPopup(false);
        }

        function resolveDashboardPopup(confirmed) {
            const els = getDashboardPopupElements();
            if (!els.modal) return;

            const rawValue = els.input ? els.input.value : '';
            const resolvedValue = dashboardPopupState.trimInput === false ? rawValue : rawValue.trim();

            if (confirmed && dashboardPopupState.requiresInput && resolvedValue === '') {
                if (els.inputError) {
                    els.inputError.textContent = dashboardPopupState.requiredMessage || 'This field is required.';
                    els.inputError.style.display = 'block';
                }
                if (els.input) els.input.focus();
                return;
            }

            closeModal('dashboardPopupModal');

            const resolver = dashboardPopupResolver;
            dashboardPopupResolver = null;
            dashboardPopupState = { requiresInput: false, trimInput: true, inputValue: '' };

            if (els.input) {
                els.input.value = '';
            }
            if (els.inputError) {
                els.inputError.style.display = 'none';
                els.inputError.textContent = 'This field is required.';
            }

            if (typeof resolver === 'function') {
                resolver({ confirmed, value: resolvedValue });
            }
        }

        function showDashboardPopup({
            title = 'Notice',
            message = '',
            variant = 'info',
            confirmText = 'OK',
            cancelText = 'Cancel',
            showCancel = false,
            requireInput = false,
            inputLabel = 'Details',
            inputPlaceholder = '',
            inputValue = '',
            requiredMessage = 'This field is required.'
        } = {}) {
            const els = getDashboardPopupElements();
            const variantConfig = dashboardPopupVariantConfig(variant);

            if (dashboardPopupResolver) {
                resolveDashboardPopup(false);
            }

            dashboardPopupState = {
                requiresInput: requireInput === true,
                trimInput: true,
                inputValue,
                requiredMessage
            };

            if (els.icon) {
                els.icon.textContent = variantConfig.icon;
                els.icon.style.color = variantConfig.color;
            }
            if (els.title) {
                els.title.textContent = title || variantConfig.title;
            }
            if (els.message) {
                els.message.textContent = message;
            }
            if (els.confirm) {
                els.confirm.textContent = confirmText;
                els.confirm.className = variantConfig.buttonClass;
            }
            if (els.cancel) {
                els.cancel.textContent = cancelText;
                els.cancel.style.display = showCancel ? '' : 'none';
            }
            if (els.inputWrap) {
                els.inputWrap.style.display = requireInput ? '' : 'none';
            }
            if (els.inputLabel) {
                els.inputLabel.textContent = inputLabel;
            }
            if (els.input) {
                els.input.placeholder = inputPlaceholder;
                els.input.value = inputValue || '';
            }
            if (els.inputError) {
                els.inputError.style.display = 'none';
                els.inputError.textContent = requiredMessage;
            }

            openModal('dashboardPopupModal');

            if (requireInput && els.input) {
                requestAnimationFrame(() => els.input.focus());
            } else if (els.confirm) {
                requestAnimationFrame(() => els.confirm.focus());
            }

            return new Promise(resolve => {
                dashboardPopupResolver = resolve;
            });
        }

        async function showAlertPopup(message, options = {}) {
            return showDashboardPopup({
                title: options.title || 'Notice',
                message,
                variant: options.variant || 'info',
                confirmText: options.confirmText || 'OK',
                showCancel: false
            });
        }

        async function showConfirmPopup(message, options = {}) {
            const result = await showDashboardPopup({
                title: options.title || 'Please Confirm',
                message,
                variant: options.variant || 'warning',
                confirmText: options.confirmText || 'Continue',
                cancelText: options.cancelText || 'Cancel',
                showCancel: true
            });
            return result.confirmed === true;
        }

        async function showPromptPopup(message, options = {}) {
            return showDashboardPopup({
                title: options.title || 'Action Needed',
                message,
                variant: options.variant || 'danger',
                confirmText: options.confirmText || 'Submit',
                cancelText: options.cancelText || 'Cancel',
                showCancel: true,
                requireInput: true,
                inputLabel: options.inputLabel || 'Details',
                inputPlaceholder: options.inputPlaceholder || '',
                inputValue: options.inputValue || '',
                requiredMessage: options.requiredMessage || 'This field is required.'
            });
        }

        function setActiveTab(el) {
            el.closest('.page-header-actions').querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            el.classList.add('active');
        }

        function navTo(target) {
            document.querySelectorAll('.nav-item[data-target]').forEach(n => {
                if (n.dataset.target === target) n.click();
            });
        }

        // ── Navigation ─────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', () => {
            const navItems = document.querySelectorAll('.nav-item[data-target]');
            const views = document.querySelectorAll('.view');
            const title = document.getElementById('pageTitle');

            navItems.forEach(item => {
                item.addEventListener('click', e => {
                    const tid = item.dataset.target;
                    const tv = document.getElementById(tid);
                    
                    // If target view isn't in DOM (migrated), let browser navigate normally to ?tab=...
                    if (!tv) return; 

                    e.preventDefault();
                    e.stopPropagation();
                    
                    navItems.forEach(n => n.classList.remove('active'));
                    item.classList.add('active');
                    views.forEach(v => v.classList.remove('active'));
                    
                    tv.classList.add('active');
                    const titleText = item.dataset.title || item.textContent.trim();
                    const subtitleText = item.dataset.subtitle || tid.charAt(0).toUpperCase() + tid.slice(1);
                    if (title) {
                        title.innerHTML = `${escapeHtml(titleText)} <span>${escapeHtml(subtitleText)}</span>`;
                    }
                    history.pushState(null, '', `#${tid}`);

                    // Show/hide 2FA wrapper based on profile tab
                    const twoFaWrapper = document.getElementById('profile-2fa-wrapper');
                    if (twoFaWrapper) {
                        twoFaWrapper.style.display = tid === 'profile' ? 'block' : 'none';
                    }
                    
                    // Lazy load on first visit - use requestAnimationFrame to ensure DOM is updated
                    requestAnimationFrame(() => {
                        if (tid === 'credit-accounts') loadCreditAccounts();
                        if (tid === 'applications') loadApps('all');
                        if (tid === 'loans') loadLoans('all');
                        if (tid === 'payments') loadPayments();
                        if (tid === 'users') loadUsers();
                        if (tid === 'reports') loadReports('month');
                    });
                });
            });

            // Handle hash or current tab loading on initial page load only
            let hashTab = location.hash.replace('#', '');
            let urlTab = new URLSearchParams(window.location.search).get('tab') || 'home';
            
            // If there is a hash, always activate the view (even if it matches url tab)
            if (hashTab) {
                const n = document.querySelector(`.nav-item[data-target="${hashTab}"]`); 
                const tv = document.getElementById(hashTab);
                if (n && tv) {
                    setTimeout(() => {
                        navItems.forEach(nav => nav.classList.remove('active'));
                        n.classList.add('active');
                        views.forEach(v => v.classList.remove('active'));
                        tv.classList.add('active');
                        const titleText = n.dataset.title || n.textContent.trim();
                        const subtitleText = n.dataset.subtitle || hashTab.charAt(0).toUpperCase() + hashTab.slice(1);
                        if (title) {
                            title.innerHTML = `${escapeHtml(titleText)} <span>${escapeHtml(subtitleText)}</span>`;
                        }

                        // Show/hide 2FA wrapper based on profile tab
                        const twoFaWrapper = document.getElementById('profile-2fa-wrapper');
                        if (twoFaWrapper) {
                            twoFaWrapper.style.display = hashTab === 'profile' ? 'block' : 'none';
                        }

                        // Load data based on tab
                        if (hashTab === 'credit-accounts') loadCreditAccounts();
                        if (hashTab === 'applications') loadApps('all');
                        if (hashTab === 'loans') loadLoans('all');
                        if (hashTab === 'payments') loadPayments();
                        if (hashTab === 'users') loadUsers();
                        if (hashTab === 'reports') loadReports('month');
                    }, 10);
                }
            }

            // Handle hash changes after initial load (e.g., when navigating from migrated tabs)
            window.addEventListener('hashchange', () => {
                const newHash = location.hash.replace('#', '');
                if (newHash) {
                    const n = document.querySelector(`.nav-item[data-target="${newHash}"]`);
                    const tv = document.getElementById(newHash);
                    if (n && tv) {
                        navItems.forEach(nav => nav.classList.remove('active'));
                        n.classList.add('active');
                        views.forEach(v => v.classList.remove('active'));
                        tv.classList.add('active');
                        const titleText = n.dataset.title || n.textContent.trim();
                        const subtitleText = n.dataset.subtitle || newHash.charAt(0).toUpperCase() + newHash.slice(1);
                        if (title) {
                            title.innerHTML = `${escapeHtml(titleText)} <span>${escapeHtml(subtitleText)}</span>`;
                        }

                        // Show/hide 2FA wrapper based on profile tab
                        const twoFaWrapper = document.getElementById('profile-2fa-wrapper');
                        if (twoFaWrapper) {
                            twoFaWrapper.style.display = newHash === 'profile' ? 'block' : 'none';
                        }

                        // Load data based on tab
                        if (newHash === 'credit-accounts') loadCreditAccounts();
                        if (newHash === 'applications') loadApps('all');
                        if (newHash === 'loans') loadLoans('all');
                        if (newHash === 'payments') loadPayments();
                        if (newHash === 'users') loadUsers();
                        if (newHash === 'reports') loadReports('month');
                    }
                }
            });

            // Theme toggle
            const themeBtn = document.getElementById('themeToggle');
            const html = document.documentElement;
            themeBtn.addEventListener('click', () => {
                const nt = html.dataset.theme === 'dark' ? 'light' : 'dark';
                html.dataset.theme = nt;
                themeBtn.querySelector('.ms').textContent = nt === 'dark' ? 'light_mode' : 'dark_mode';
                fetch(API.theme, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ theme: nt }) }).catch(() => { });
            });

            // Date defaults
            const today = new Date().toISOString().slice(0, 10);
            document.getElementById('payDate') && (document.getElementById('payDate').value = today);
            document.getElementById('releaseDate') && (document.getElementById('releaseDate').value = today);
            const legacyPaymentsHint = document.getElementById('todayTotal');
            if (legacyPaymentsHint && legacyPaymentsHint.parentElement) {
                legacyPaymentsHint.parentElement.textContent = 'Review posted receipts, transaction references, and collection activity.';
            }

            // Doc upload sync
            document.querySelectorAll('.document-upload-input').forEach(inp => {
                inp.addEventListener('change', () => {
                    const cb = document.querySelector(`.doc-collected-checkbox[data-doc-id="${inp.dataset.docId}"]`);
                    if (cb && inp.files.length > 0) cb.checked = true;
                });
            });
            document.querySelectorAll('.doc-collected-checkbox').forEach(cb => {
                cb.addEventListener('change', () => {
                    if (!cb.checked) {
                        const inp = document.querySelector(`.document-upload-input[data-doc-id="${cb.dataset.docId}"]`);
                        if (inp) inp.value = '';
                    }
                });
            });

            loadDashboardStats();
        });

        // ── Dashboard Stats ─────────────────────────────────────────
        async function loadDashboardStats() {
            try {
                const r = await fetch(API.dashboard + '?action=stats');
                const d = await r.json();
                if (d.status !== 'success') return;
                const s = d.data;
                if (s.pending_applications !== undefined) {
                    setText('statPendingApps', s.pending_applications);
                    const pendingBadge = document.getElementById('navPendingAppsBadge');
                    if (pendingBadge) {
                        pendingBadge.textContent = s.pending_applications;
                        pendingBadge.style.display = s.pending_applications > 0 ? 'inline-flex' : 'none';
                    }
                }
                if (s.active_clients !== undefined) setText('statActiveClients', s.active_clients);
                if (s.active_loans !== undefined) setText('statActiveLoans', s.active_loans);
                if (s.overdue_loans !== undefined) setText('statOverdueLoans', s.overdue_loans);
                if (s.todays_collections !== undefined) {
                    setText('statTodayCollections', fmt(s.todays_collections));
                    setText('receiptTodayTotal', fmt(s.todays_collections));
                }
            } catch (_) { }
        }
        function setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }

        // ── App Filter (live API) ─────────────────────────────────────
        async function loadApps(status = 'all', btn = null) {
            const filterTabs = Array.from(document.querySelectorAll('#appFilterTabs .filter-tab'));
            const activeBtn = btn || filterTabs.find(tab => tab.dataset.status === status);
            if (activeBtn) {
                filterTabs.forEach(tab => tab.classList.remove('active'));
                activeBtn.classList.add('active');
            }
            const tbody = document.getElementById('appsTbody');
            tbody.innerHTML = '<tr class="loading-row"><td colspan="7"><span class="spinner"></span></td></tr>';
            try {
                const d = await fetchJsonStrict(API.applications + '?action=list');
                if (d.status !== 'success') {
                    throw new Error(d.message || 'Could not load applications.');
                }

                const rows = (d.data || []).filter(application => matchesApplicationFilter(application.application_status, status));
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="empty-row"><td colspan="7">No applications found for this filter.</td></tr>';
                    return;
                }

                tbody.innerHTML = rows.map(a => `<tr>
        <td class="td-mono td-bold">${escapeHtml(a.application_number)}</td>
        <td class="td-bold">${escapeHtml(a.first_name)} ${escapeHtml(a.last_name)}</td>
        <td class="td-muted">${escapeHtml(a.product_name)}</td>
        <td class="td-bold" style="color:var(--brand);">${fmt(a.requested_amount)}</td>
        <td class="td-muted">${fmtDate(a.submitted_date || a.created_at)}</td>
        <td>${applicationMonitorBadge(a.application_status)}</td>
        <td><button class="icon-btn table-icon-btn" onclick="viewApplication(${a.application_id})" title="Open application" aria-label="Open application"><span class="material-symbols-rounded ms">visibility</span></button></td>
    </tr>`).join('');
            } catch (error) {
                tbody.innerHTML = `<tr class="empty-row"><td colspan="7">${escapeHtml(getRequestErrorMessage(error, 'Could not load applications.'))}</td></tr>`;
            }
        }

        function filterApps(status, btn) { loadApps(status, btn); }

        async function collectApplicationActionNotes(action) {
            const notesField = document.getElementById('appActionNotes');
            const currentNotes = ((notesField && notesField.value) || '').trim();

            if (action === 'reject') {
                const popup = await showPromptPopup('Please enter the rejection reason for this loan application.', {
                    title: 'Reject Loan Application',
                    variant: 'danger',
                    confirmText: 'Reject Loan',
                    inputLabel: 'Rejection Reason',
                    inputPlaceholder: 'State the reason for rejecting this application...',
                    inputValue: currentNotes,
                    requiredMessage: 'Rejection reason is required.'
                });

                if (!popup.confirmed) {
                    return null;
                }

                return popup.value;
            }

            return currentNotes;
        }

        // ── View Application ────────────────────────────────────────
        async function viewApplication(id) {
            openModal('appReviewModal');
            document.getElementById('appModalBody').innerHTML = '<div style="text-align:center;padding:32px;"><span class="spinner"></span></div>';
            document.getElementById('appModalFooter').innerHTML = '<button class="btn btn-outline" onclick="closeModal(\'appReviewModal\')">Close</button>';
            const r = await fetch(API.applications + `?action=view&id=${id}`);
            const d = await r.json();
            if (d.status !== 'success') { document.getElementById('appModalBody').innerHTML = `<p style="color:#ef4444;">${d.message}</p>`; return; }
            const a = d.data;
            document.getElementById('appModalTitle').textContent = 'App: ' + a.application_number;

            document.getElementById('appModalBody').innerHTML = `
        <div class="form-grid" style="margin-bottom:18px;">
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Client</p><p style="font-weight:600;">${a.first_name} ${a.last_name}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Status</p>${badge(a.application_status)}</div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Product</p><p>${a.product_name} (${a.product_type})</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Requested Amount</p><p style="font-weight:700;color:var(--brand);font-size:1.05rem;">${fmt(a.requested_amount)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Term</p><p>${a.loan_term_months} months</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Interest Rate</p><p>${a.interest_rate}% / month</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Contact</p><p>${a.contact_number || '—'}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Submitted</p><p>${fmtDate(a.submitted_date || a.created_at)}</p></div>
            ${a.loan_purpose ? `<div style="grid-column:1/-1;"><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Loan Purpose</p><p>${a.loan_purpose}</p></div>` : ''}
        </div>
        ${a.review_notes ? `<div style="background:var(--body-bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px;margin-bottom:12px;"><p style="font-size:.72rem;color:var(--muted);margin-bottom:4px;">Review Notes</p><p style="font-size:.85rem;">${a.review_notes}</p></div>` : ''}
        ${a.rejection_reason ? `<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:var(--radius-sm);padding:12px;margin-bottom:12px;"><p style="font-size:.72rem;color:#991b1b;margin-bottom:4px;">Rejection Reason</p><p style="font-size:.85rem;color:#7f1d1d;">${a.rejection_reason}</p></div>` : ''}
        ${a.application_status === 'Approved' ? `<div class="form-group" style="margin-bottom:14px;"><label>Approved Amount (PHP)</label><input type="number" id="approvedAmountInput" value="${a.approved_amount || a.requested_amount}" style="padding:8px 11px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--body-bg);color:var(--text);width:100%;font-family:var(--font);font-size:.85rem;"></div>` : ''}
        <div class="form-group"><label>Action Notes (optional)</label><textarea id="appActionNotes" placeholder="Add optional review or approval notes..." style="padding:8px 11px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--body-bg);color:var(--text);width:100%;font-family:var(--font);font-size:.85rem;min-height:70px;resize:vertical;outline:none;"></textarea></div>`;

            const footer = document.getElementById('appModalFooter');
            footer.innerHTML = '<button class="btn btn-outline" onclick="closeModal(\'appReviewModal\')">Close</button>';
            const s = a.application_status;
            if (s === 'Submitted' || s === 'Pending Review') {
                footer.innerHTML += `<button class="btn btn-brand" onclick="appAction(${a.application_id},'start_review')">Start Review</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
            } else if (s === 'Under Review') {
                footer.innerHTML += `<button class="btn btn-outline" onclick="appAction(${a.application_id},'verify_docs')"><span class="material-symbols-rounded ms">verified</span> Verify Docs</button>
                             <button class="btn btn-success" onclick="appAction(${a.application_id},'approve',true)"><span class="material-symbols-rounded ms">check_circle</span> Approve</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
            } else if (s === 'Document Verification') {
                footer.innerHTML += `<button class="btn btn-brand" onclick="appAction(${a.application_id},'credit_inv')">Credit Investigation</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
            } else if (s === 'Credit Investigation') {
                footer.innerHTML += `<button class="btn btn-brand" onclick="appAction(${a.application_id},'for_approval')">For Approval</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
            } else if (s === 'For Approval') {
                footer.innerHTML += `<button class="btn btn-success" onclick="appAction(${a.application_id},'approve',true)"><span class="material-symbols-rounded ms">check_circle</span> Approve</button>
                             <button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>`;
            } else if (s === 'Draft') {
                footer.innerHTML += `<button class="btn btn-brand" onclick="appAction(${a.application_id},'submit')">Submit Application</button>`;
            }
        }

        async function appAction(id, action, needsAmount = false) {
            const notes = await collectApplicationActionNotes(action);
            const approved = needsAmount ? parseFloat((document.getElementById('approvedAmountInput') || {}).value || 0) : null;
            if (notes === null) return;
            const payload = { application_id: id, action, notes };
            if (approved) payload.approved_amount = approved;
            const r = await fetch(API.applications, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const d = await r.json();
            await showAlertPopup(d.message || 'Application updated.', {
                title: d.status === 'success' ? 'Success' : 'Unable to Update Application',
                variant: d.status === 'success' ? 'success' : 'danger'
            });
            if (d.status === 'success') {
                closeModal('appReviewModal');
                loadApps(getActiveAppFilter());
                loadDashboardStats();
            }
        }

        // ── Loans ────────────────────────────────────────────────────
        // Credit policy modal override
        async function viewApplication(id) {
            openModal('appReviewModal');
            document.getElementById('appModalBody').innerHTML = '<div style="text-align:center;padding:32px;"><span class="spinner"></span></div>';
            document.getElementById('appModalFooter').innerHTML = '<button class="btn btn-outline" onclick="closeModal(\'appReviewModal\')">Close</button>';
            let d;
            try {
                d = await fetchJsonStrict(API.applications + `?action=view&id=${id}`);
            } catch (error) {
                document.getElementById('appModalBody').innerHTML = `<p style="color:#ef4444;">${escapeHtml(getRequestErrorMessage(error, 'Could not load this application.'))}</p>`;
                return;
            }
            if (d.status !== 'success') {
                document.getElementById('appModalBody').innerHTML = `<p style="color:#ef4444;">${escapeHtml(d.message || 'Could not load this application.')}</p>`;
                return;
            }

            const a = d.data;
            const reviewableStatuses = ['Submitted', 'Pending Review', 'Under Review', 'Document Verification', 'Credit Investigation', 'For Approval'];
            const showApprovedAmountInput = reviewableStatuses.includes(a.application_status);
            const safe = value => String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            })[char]);
            const latestScore = a.latest_credit_score !== null && a.latest_credit_score !== undefined && a.latest_credit_score !== ''
                ? `${safe(a.latest_credit_score)}`
                : '<span class="detail-value is-empty">Not available</span>';
            const clientDocs = Array.isArray(a.client_documents) ? a.client_documents : [];
            const applicationDocs = Array.isArray(a.application_documents) ? a.application_documents : [];
            const clientDocsRows = clientDocs.length ? clientDocs.map(doc => {
                const href = documentHref(doc);
                const fileHtml = href
                    ? `<a href="${safe(href)}" target="_blank" class="btn btn-sm btn-outline"><span class="material-symbols-rounded ms" style="font-size:16px;">visibility</span> View</a>`
                    : '<span class="td-muted">Not uploaded</span>';
                return `<tr>
            <td class="td-bold">${safe(doc.document_name || doc.file_name || 'Client document')}</td>
            <td>${fileHtml}</td>
            <td class="td-muted">${fmtDate(doc.upload_date)}</td>
            <td>${badge(doc.verification_status || 'Pending')}</td>
        </tr>`;
            }).join('') : '<tr class="empty-row"><td colspan="4">No client verification documents found.</td></tr>';
            const applicationDocsRows = applicationDocs.length ? applicationDocs.map(doc => {
                const href = documentHref(doc);
                const fileHtml = href
                    ? `<a href="${safe(href)}" target="_blank" class="btn btn-sm btn-outline"><span class="material-symbols-rounded ms" style="font-size:16px;">visibility</span> View</a>`
                    : '<span class="td-muted">File unavailable</span>';
                return `<tr>
            <td class="td-bold">${safe(doc.document_name || doc.file_name || 'Application attachment')}</td>
            <td>${fileHtml}</td>
            <td class="td-muted">${fmtDate(doc.upload_date)}</td>
        </tr>`;
            }).join('') : '<tr class="empty-row"><td colspan="3">No application attachments were submitted.</td></tr>';

            // Check capital sufficiency before building the modal
            const capitalAvailable = parseFloat(a.capital_available_balance || 0);
            const capitalReserved = parseFloat(a.capital_reserved_amount || 0);
            const trulyAvailable = capitalAvailable - capitalReserved;
            const requestedAmount = parseFloat(a.requested_amount || 0);
            const hasSufficientCapital = trulyAvailable >= requestedAmount;

            document.getElementById('appModalTitle').textContent = a.application_number;
            document.getElementById('appModalBody').innerHTML = `
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px 16px;margin-bottom:16px;">
            <div><span style="font-size:.65rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Client</span><div style="font-weight:600;font-size:.9rem;margin-top:2px;">${safe(a.first_name)} ${safe(a.last_name)}</div></div>
            <div><span style="font-size:.65rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Status</span><div style="margin-top:2px;">${applicationMonitorBadge(a.application_status)}</div></div>
            <div><span style="font-size:.65rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Product</span><div style="font-size:.85rem;margin-top:2px;">${safe(a.product_name)}</div></div>
            <div><span style="font-size:.65rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Requested Amount</span><div style="font-weight:700;color:var(--brand);font-size:1rem;margin-top:2px;">${fmt(a.requested_amount)}</div></div>
            <div><span style="font-size:.65rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Term</span><div style="font-size:.85rem;margin-top:2px;">${safe(a.loan_term_months)} months</div></div>
            <div><span style="font-size:.65rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Interest Rate</span><div style="font-size:.85rem;margin-top:2px;">${safe(a.interest_rate)}% / month</div></div>
            <div><span style="font-size:.65rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Credit Limit</span><div style="font-size:.85rem;margin-top:2px;">${parseFloat(a.credit_limit || 0) > 0 ? fmt(a.credit_limit) : 'Not set'}</div></div>
            <div><span style="font-size:.65rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Credit Score</span><div style="font-size:.85rem;margin-top:2px;">${latestScore}</div></div>
            ${a.loan_purpose ? `<div style="grid-column:1/-1;"><span style="font-size:.65rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Loan Purpose</span><div style="font-size:.85rem;margin-top:2px;">${safe(a.loan_purpose)}</div></div>` : ''}
        </div>
        ${applicationDocs.length ? `<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:12px;"><div style="font-size:.7rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">Attachments</div><table style="width:100%;border-collapse:collapse;font-size:.8rem;"><thead><tr style="background:#f1f5f9;"><th style="padding:8px;text-align:left;font-weight:600;color:#475569;">Document</th><th style="padding:8px;text-align:left;font-weight:600;color:#475569;">Uploaded</th></tr></thead><tbody>${applicationDocsRows}</tbody></table></div>` : ''}
        ${a.approval_notes ? `<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:12px;margin-bottom:12px;"><div style="font-size:.7rem;font-weight:600;color:#166534;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Approval Notes</div><div style="font-size:.85rem;color:#14532d;line-height:1.4;">${safe(a.approval_notes)}</div></div>` : ''}
        ${a.review_notes ? `<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:12px;"><div style="font-size:.7rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Review Notes</div><div style="font-size:.85rem;color:#334155;line-height:1.4;">${safe(a.review_notes)}</div></div>` : ''}
        ${a.rejection_reason ? `<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:12px;margin-bottom:12px;"><div style="font-size:.7rem;font-weight:600;color:#991b1b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Rejection Reason</div><div style="font-size:.85rem;color:#7f1d1d;line-height:1.4;">${safe(a.rejection_reason)}</div></div>` : ''}
        ${!hasSufficientCapital ? `<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:12px;margin-bottom:12px;"><div style="font-size:.7rem;font-weight:600;color:#991b1b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Insufficient Capital</div><div style="font-size:.85rem;color:#7f1d1d;line-height:1.4;">Your capital is not sufficient for approving this loan. Please contact your admin.</div></div>` : ''}
        <div style="margin-top:16px;"><label style="font-size:.75rem;font-weight:600;color:#475569;display:block;margin-bottom:6px;">Action Notes</label><textarea id="appActionNotes" placeholder="Enter notes..." style="width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;color:#1e293b;font-size:.85rem;font-family:inherit;min-height:70px;resize:vertical;transition:border-color .2s;" onfocus="this.style.borderColor='#cbd5e1'" onblur="this.style.borderColor='#e2e8f0'"></textarea></div>`;

            const footer = document.getElementById('appModalFooter');
            footer.innerHTML = '<button class="btn btn-outline" onclick="closeModal(\'appReviewModal\')">Close</button>';
            
            if (reviewableStatuses.includes(a.application_status)) {
                let approveButtons = '';
                if (hasSufficientCapital) {
                    approveButtons = `<button class="btn btn-success" onclick="appAction(${a.application_id},'approve')">Approve</button>
                             <?php if (has_permission('VIEW_LOANS') || has_permission('CREATE_LOANS') || has_permission('APPROVE_LOANS')): ?><button class="btn btn-primary" onclick="appAction(${a.application_id},'approve_disburse')">Approve & Disburse</button><?php endif; ?>`;
                } else {
                    approveButtons = `<button class="btn btn-success" disabled style="opacity:0.5;cursor:not-allowed;">Approve</button>
                             <?php if (has_permission('VIEW_LOANS') || has_permission('CREATE_LOANS') || has_permission('APPROVE_LOANS')): ?><button class="btn btn-primary" disabled style="opacity:0.5;cursor:not-allowed;">Approve & Disburse</button><?php endif; ?>`;
                }
                footer.innerHTML += `<button class="btn btn-danger" onclick="appAction(${a.application_id},'reject')">Reject</button>
                             ${approveButtons}`;
            }
        }

        async function appAction(id, action, needsAmount = false) {
            const notes = await collectApplicationActionNotes(action);
            const approved = needsAmount ? parseFloat((document.getElementById('approvedAmountInput') || {}).value || 0) : null;

            if (notes === null) {
                return;
            }
            if (needsAmount && !(approved > 0)) {
                await showAlertPopup('Please enter an approved amount.', {
                    title: 'Approved Amount Required',
                    variant: 'warning'
                });
                return;
            }

            const payload = { application_id: id, action, notes };
            if (needsAmount) {
                payload.approved_amount = approved;
            }

            try {
                const d = await fetchJsonStrict(API.applications, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                if (d.status === 'success') {
                    closeModal('appReviewModal');
                    await showAlertPopup(d.message || 'Application updated.', {
                        title: 'Success',
                        variant: 'success'
                    });
                    loadApps(getActiveAppFilter());
                    loadDashboardStats();
                    return;
                }
                await showAlertPopup(d.message || 'Could not update the application.', {
                    title: 'Unable to Update Application',
                    variant: 'danger'
                });
            } catch (error) {
                await showAlertPopup(getRequestErrorMessage(error, 'Could not update the application.'), {
                    title: 'Unable to Update Application',
                    variant: 'danger'
                });
            }
        }

        async function loadPendingDisbursements() {
            const tbody = document.getElementById('loanDisbursementTbody');
            if (!tbody) return;
            tbody.innerHTML = '<tr class="loading-row"><td colspan="6"><span class="spinner"></span></td></tr>';
            pendingDisbursementApps = {};

            try {
                const response = await fetch(API.loans + '?action=approved_applications');
                const result = await response.json();
                const rows = result.data || [];

                if (result.status !== 'success') {
                    tbody.innerHTML = `<tr class="empty-row"><td colspan="6">${escapeHtml(result.message || 'Could not load pending disbursements.')}</td></tr>`;
                    return;
                }

                if (!rows.length) {
                    tbody.innerHTML = '<tr class="empty-row"><td colspan="6">No approved applications are waiting for disbursement.</td></tr>';
                    return;
                }

                tbody.innerHTML = rows.map(app => {
                    pendingDisbursementApps[String(app.application_id)] = app;
                    const approvedAmount = resolveApprovedDisbursementAmount(app);
                    const approvedDate = app.approval_date || app.submitted_date;
                    const actionHtml = `<?php if (has_permission('APPROVE_LOANS')): ?><button class="btn btn-sm btn-brand" onclick="openLoanRelease(${Number(app.application_id)})"><span class="material-symbols-rounded ms" style="font-size:16px;">payments</span> Release</button><?php else: ?><span class="td-muted">View only</span><?php endif; ?>`;

                    return `<tr>
                <td class="td-mono td-bold">${escapeHtml(app.application_number)}</td>
                <td class="td-bold">${escapeHtml(app.first_name)} ${escapeHtml(app.last_name)}</td>
                <td class="td-muted">${escapeHtml(app.product_name)}</td>
                <td class="td-bold" style="color:var(--brand);">${fmt(approvedAmount)}</td>
                <td class="td-muted">${fmtDate(approvedDate)}</td>
                <td>${actionHtml}</td>
            </tr>`;
                }).join('');
            } catch (error) {
                console.error(error);
                tbody.innerHTML = '<tr class="empty-row"><td colspan="6">Could not load pending disbursements right now.</td></tr>';
            }
        }

        async function loadLoans(status = 'all', btn = null) {
            if (btn) {
                btn.closest('.filter-tabs').querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                btn.classList.add('active');
            }
            loadPendingDisbursements();
            const tbody = document.getElementById('loansTbody');
            if (!tbody) return;
            tbody.innerHTML = '<tr class="loading-row"><td colspan="8"><span class="spinner"></span></td></tr>';
            const r = await fetch(API.loans + '?action=list&status=' + encodeURIComponent(status));
            const d = await r.json();
            if (!d.data || !d.data.length) { tbody.innerHTML = '<tr class="empty-row"><td colspan="8">No loans found.</td></tr>'; return; }
            tbody.innerHTML = d.data.map(l => `
        <tr>
            <td class="td-mono td-bold">${l.loan_number}</td>
            <td>${l.first_name} ${l.last_name}</td>
            <td class="td-muted">${l.product_name}</td>
            <td class="td-bold">${fmt(l.principal_amount)}</td>
            <td class="td-bold" style="color:${parseFloat(l.remaining_balance) > 0 ? 'var(--brand)' : '#22c55e'};">${fmt(l.remaining_balance)}</td>
            <td class="td-muted" style="color:${l.days_overdue > 0 ? '#ef4444' : ''};">${fmtDate(l.next_payment_due)}</td>
            <td>${badge(l.loan_status)}</td>
            <td><button class="btn btn-sm btn-outline" onclick="viewLoan(${l.loan_id})">View</button></td>
        </tr>`).join('');
        }

        async function viewLoan(id) {
            activeLoanId = id;
            openModal('loanDetailModal');
            document.getElementById('loanDetailBody').innerHTML = '<div style="text-align:center;padding:32px;"><span class="spinner"></span></div>';
            const [lr, sr] = await Promise.all([
                fetch(API.loans + `?action=view&loan_id=${id}`),
                fetch(API.loans + `?action=schedule&loan_id=${id}`)
            ]);
            const ld = await lr.json(); const sd = await sr.json();
            if (ld.status !== 'success') { document.getElementById('loanDetailBody').innerHTML = `<p style="color:#ef4444;">${ld.message}</p>`; return; }
            const l = ld.data;
            document.getElementById('loanDetailTitle').textContent = l.loan_number;
            const sched = (sd.data || []).map(s => `<tr>
        <td style="text-align:center;">#${s.payment_number}</td>
        <td>${fmtDate(s.due_date)}</td>
        <td>${fmt(s.beginning_balance)}</td>
        <td>${fmt(s.principal_amount)}</td>
        <td>${fmt(s.interest_amount)}</td>
        <td class="td-bold">${fmt(s.total_payment)}</td>
        <td>${badge(s.payment_status)}</td>
    </tr>`).join('');

            document.getElementById('loanDetailBody').innerHTML = `
        <div class="form-grid" style="margin-bottom:20px;">
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Client</p><p style="font-weight:600;">${l.first_name} ${l.last_name}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Status</p>${badge(l.loan_status)}</div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Principal</p><p style="font-weight:700;">${fmt(l.principal_amount)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Remaining Balance</p><p style="font-weight:700;color:var(--brand);">${fmt(l.remaining_balance)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Monthly Amortization</p><p>${fmt(l.monthly_amortization)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Next Due</p><p style="color:${l.days_overdue > 0 ? '#ef4444' : ''};">${fmtDate(l.next_payment_due)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Release Date</p><p>${fmtDate(l.release_date)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Maturity Date</p><p>${fmtDate(l.maturity_date)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Payment Frequency</p><p>${escapeHtml(l.payment_frequency || 'Monthly')}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Disbursement Method</p><p>${escapeHtml(l.disbursement_method || 'Cash')}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Total Paid</p><p style="color:#22c55e;font-weight:600;">${fmt(l.total_paid)}</p></div>
            <div><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Interest Rate</p><p>${l.interest_rate}% / month</p></div>
            <div style="grid-column:1 / -1;"><p style="font-size:.72rem;color:var(--muted);margin-bottom:3px;">Disbursement Reference</p><p>${escapeHtml(l.disbursement_reference || 'Auto-generated on release')}</p></div>
        </div>
        <p style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:10px;">Amortization Schedule</p>
        <div style="overflow-x:auto;"><table class="sched-table">
            <thead><tr style="background:var(--body-bg);">
                <th style="text-align:center;">#</th><th>Due Date</th><th>Beg. Balance</th>
                <th>Principal</th><th>Interest</th><th>Total</th><th>Status</th>
            </tr></thead>
            <tbody>${sched || '<tr class="empty-row"><td colspan="7">No schedule found.</td></tr>'}</tbody>
        </table></div>`;
        }

        function openLoanRelease(appId, amount) {
            closeModal('appReviewModal');
            const app = pendingDisbursementApps[String(appId)] || {};
            const releaseDate = todayIsoDate();
            const approvedAmount = resolveApprovedDisbursementAmount(app) || parseFloat(amount || 0) || 0;
            const releaseMethod = resolveReleaseMethod(app);
            const paymentFrequency = resolveReleasePaymentFrequency(app);
            const releaseReference = buildDisbursementReference(appId, releaseDate);
            const withdrawalDetails = resolveWithdrawalDetails(app);

            document.getElementById('releaseAppId').value = appId;
            document.getElementById('releaseAppNumber').value = app.application_number || `Application #${appId}`;
            document.getElementById('releaseAmount').value = approvedAmount;
            document.getElementById('releaseAmountPreview').value = fmt(approvedAmount);
            document.getElementById('releaseDate').value = releaseDate;
            document.getElementById('releaseDatePreview').value = fmtDate(releaseDate);
            document.getElementById('releaseMethod').value = releaseMethod;
            document.getElementById('releaseMethodPreview').value = releaseMethod;
            document.getElementById('releaseFreq').value = paymentFrequency;
            document.getElementById('releaseFreqPreview').value = paymentFrequency;
            document.getElementById('releaseRef').value = releaseReference;
            document.getElementById('releaseRefPreview').value = releaseReference;
            document.getElementById('releaseNotes').value = '';
            
            // Show withdrawal details if available
            const detailsGroup = document.getElementById('withdrawalDetailsGroup');
            const detailsContent = document.getElementById('withdrawalDetailsContent');
            if (withdrawalDetails) {
                detailsGroup.style.display = 'block';
                detailsContent.innerHTML = withdrawalDetails;
            } else {
                detailsGroup.style.display = 'none';
            }
            
            openModal('loanReleaseModal');
        }

        async function submitLoanRelease() {
            const payload = {
                application_id: parseInt(document.getElementById('releaseAppId').value),
                approved_amount: parseFloat(document.getElementById('releaseAmount').value),
                disbursement_method: document.getElementById('releaseMethod').value,
                release_date: document.getElementById('releaseDate').value,
                payment_frequency: document.getElementById('releaseFreq').value,
                disbursement_reference: document.getElementById('releaseRef').value,
                notes: document.getElementById('releaseNotes').value,
            };
            if (!payload.application_id) {
                await showAlertPopup('Missing approved application details.', {
                    title: 'Loan Release Unavailable',
                    variant: 'danger'
                });
                return;
            }
            const r = await fetch(API.loans + '?action=release', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const d = await r.json();
            if (d.status === 'success') {
                closeModal('loanReleaseModal');
                await showAlertPopup(d.message || 'Loan released successfully.', {
                    title: 'Success',
                    variant: 'success'
                });
                loadLoans(getActiveLoanFilter(), document.querySelector('#loanFilterTabs .filter-tab.active'));
                loadDashboardStats();
                switchView('loans');
                return;
            }
            await showAlertPopup(d.message || 'Could not release this loan.', {
                title: 'Unable to Release Loan',
                variant: 'danger'
            });
        }

        // ── Payments ──────────────────────────────────────────────────
        async function loadPayments() {
            const tbody = document.getElementById('paymentsTbody');
            if (!tbody) return;
            tbody.innerHTML = '<tr class="loading-row"><td colspan="8"><span class="spinner"></span></td></tr>';
            const r = await fetch(API.payments + '?action=list');
            const d = await r.json();
            const rows = d.data || [];
            if (d.todays_total !== undefined) setText('receiptTodayTotal', fmt(d.todays_total));
            const todayString = new Date().toISOString().slice(0, 10);
            const todaysCount = rows.filter(p => String(p.payment_date || '').slice(0, 10) === todayString && p.payment_status !== 'Cancelled').length;
            setText('receiptTodayCount', todaysCount);
            setText('receiptLatestPosted', rows.length ? fmtDate(rows[0].payment_date || rows[0].created_at) : 'â€”');
            if (!rows.length) { tbody.innerHTML = '<tr class="empty-row"><td colspan="8">No transaction records found.</td></tr>'; return; }
            tbody.innerHTML = rows.map(p => `<tr>
        <td class="td-mono td-bold">${escapeHtml(p.official_receipt_number || p.payment_reference || '-')}</td>
        <td class="td-mono td-muted">${escapeHtml(p.payment_reference_number || p.payment_reference || '-')}</td>
        <td>${escapeHtml(p.first_name)} ${escapeHtml(p.last_name)}</td>
        <td class="td-mono td-muted">${escapeHtml(p.loan_number)}</td>
        <td class="td-bold" style="color:#10b981;">${fmt(p.payment_amount)}</td>
        <td class="td-muted">${escapeHtml(p.payment_method)}</td>
        <td class="td-muted">${fmtDate(p.payment_date)}</td>
        <td>${badge(p.payment_status)}</td>
    </tr>`).join('');
        }

        async function loadPayments() {
            const tbody = document.getElementById('paymentsTbody');
            if (!tbody) return;
            tbody.innerHTML = '<tr class="loading-row"><td colspan="8"><span class="spinner"></span></td></tr>';
            const r = await fetch(API.payments + '?action=list');
            const d = await r.json();
            const rows = d.data || [];
            if (d.todays_total !== undefined) setText('receiptTodayTotal', fmt(d.todays_total));
            const todayString = new Date().toISOString().slice(0, 10);
            const todaysCount = rows.filter(p => String(p.payment_date || '').slice(0, 10) === todayString && p.payment_status !== 'Cancelled').length;
            setText('receiptTodayCount', todaysCount);
            setText('receiptLatestPosted', rows.length ? fmtDate(rows[0].payment_date || rows[0].created_at) : '-');
            if (!rows.length) {
                tbody.innerHTML = '<tr class="empty-row"><td colspan="8">No transaction records found.</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map(p => `<tr>
        <td class="td-mono td-bold">${escapeHtml(p.official_receipt_number || p.payment_reference || '-')}</td>
        <td class="td-mono td-muted">${escapeHtml(p.payment_reference_number || p.payment_reference || '-')}</td>
        <td>${escapeHtml(p.first_name)} ${escapeHtml(p.last_name)}</td>
        <td class="td-mono td-muted">${escapeHtml(p.loan_number)}</td>
        <td class="td-bold" style="color:#10b981;">${fmt(p.payment_amount)}</td>
        <td class="td-muted">${escapeHtml(p.payment_method)}</td>
        <td class="td-muted">${fmtDate(p.payment_date)}</td>
        <td>${badge(p.payment_status)}</td>
    </tr>`).join('');
        }

        async function loadPaymentLoans() {
            const sel = document.getElementById('payLoanId');
            if (!sel) return;
            const r = await fetch(API.payments + '?action=active_loans');
            const d = await r.json();
            if (!d.data) return;
            sel.innerHTML = '<option value="">— Select a loan —</option>' +
                d.data.map(l => `<option value="${l.loan_id}" data-balance="${l.remaining_balance}" data-amort="${l.monthly_amortization}" data-due="${l.next_payment_due}">
            ${l.first_name} ${l.last_name} — ${l.loan_number} (Bal: ${fmt(l.remaining_balance)})
        </option>`).join('');
        }

        function onPayLoanChange() {
            const sel = document.getElementById('payLoanId');
            const opt = sel.selectedOptions[0];
            const info = document.getElementById('payLoanInfo');
            if (!opt || !opt.value) { info.textContent = ''; return; }
            info.textContent = `Balance: ${fmt(opt.dataset.balance)} · Monthly: ${fmt(opt.dataset.amort)} · Next Due: ${fmtDate(opt.dataset.due)}`;
            document.getElementById('payAmount').value = opt.dataset.amort;
        }

        function openPaymentFromLoan() {
            const sel = document.getElementById('payLoanId');
            if (sel && activeLoanId) { sel.value = activeLoanId; onPayLoanChange(); }
            closeModal('loanDetailModal');
            openModal('paymentModal');
        }

        async function submitPayment() {
            const payload = {
                loan_id: parseInt(document.getElementById('payLoanId').value),
                payment_amount: parseFloat(document.getElementById('payAmount').value),
                payment_method: document.getElementById('payMethod').value,
                payment_date: document.getElementById('payDate').value,
                or_number: document.getElementById('payOR').value,
                payment_ref_number: document.getElementById('payRef').value,
                remarks: document.getElementById('payRemarks').value,
            };
            if (!payload.loan_id || !payload.payment_amount) {
                await showAlertPopup('Please select a loan and enter an amount.', {
                    title: 'Payment Details Required',
                    variant: 'warning'
                });
                return;
            }
            const r = await fetch(API.payments + '?action=post', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const d = await r.json();
            if (d.status === 'success') {
                closeModal('paymentModal');
                await showAlertPopup(d.message || 'Payment posted successfully.', {
                    title: 'Success',
                    variant: 'success'
                });
                loadPayments();
                loadDashboardStats();
                loadPaymentLoans();
                return;
            }
            await showAlertPopup(d.message || 'Could not post this payment.', {
                title: 'Unable to Post Payment',
                variant: 'danger'
            });
        }

        // ── Reports ───────────────────────────────────────────────────
        async function loadReports(period = 'month') {
            const body = document.getElementById('reportsBody');
            if (!body) return;
            body.innerHTML = '<div style="text-align:center;padding:40px;"><span class="spinner"></span></div>';
            try {
                const r = await fetch(API.dashboard + `?action=reports&period=${period}`);
                const d = await r.json();
                if (d.status !== 'success') { body.innerHTML = '<p style="color:var(--muted);padding:24px;">Could not load report data.</p>'; return; }
                const rpt = d.data;
                const sm = rpt.summary || {};
                const daily = rpt.daily_summary || [];
                const methods = rpt.method_breakdown || [];
                const sources = rpt.source_breakdown || [];
                const staff = rpt.staff_summary || [];
                const clients = rpt.client_summary || [];
                const recent = rpt.recent_transactions || [];

                // — Bar chart helper (pure CSS) —
                function miniBar(items, labelKey, valueKey, colorFn) {
                    if (!items.length) return '<p style="padding:20px;color:var(--muted);font-size:.85rem;">No data for this period.</p>';
                    const max = Math.max(...items.map(i => parseFloat(i[valueKey]) || 0), 1);
                    return items.map(i => {
                        const val = parseFloat(i[valueKey]) || 0;
                        const pct = Math.round((val / max) * 100);
                        const color = typeof colorFn === 'function' ? colorFn(i) : 'var(--brand)';
                        return `<div style="padding:10px 20px;border-top:1px solid var(--border);">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                <span style="font-size:.83rem;font-weight:500;">${escapeHtml(i[labelKey] || 'Unknown')}</span>
                                <strong style="font-size:.83rem;">${fmt(val)}</strong>
                            </div>
                            <div style="height:6px;border-radius:99px;background:var(--border);overflow:hidden;">
                                <div style="height:100%;width:${pct}%;background:${color};border-radius:99px;transition:width .4s ease;"></div>
                            </div>
                        </div>`;
                    }).join('');
                }

                // — Daily trend sparkline (pure CSS) —
                function dailyTrend(days) {
                    if (!days.length) return '<p style="padding:20px;color:var(--muted);font-size:.85rem;">No daily data available.</p>';
                    const max = Math.max(...days.map(d => parseFloat(d.total_amount) || 0), 1);
                    return `<div style="display:flex;align-items:flex-end;gap:3px;height:120px;padding:14px 20px 10px;">
                        ${days.map(d => {
                            const val = parseFloat(d.total_amount) || 0;
                            const pct = Math.max(Math.round((val / max) * 100), 3);
                            const label = d.transaction_day ? new Date(d.transaction_day).toLocaleDateString('en-PH', {month:'short', day:'numeric'}) : '?';
                            return `<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;min-width:0;" title="${label}: ${fmt(val)}">
                                <div style="font-size:.8rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;">${fmt(val)}</div>
                                <div style="width:100%;max-width:36px;height:${pct}%;background:var(--brand);border-radius:4px 4px 0 0;min-height:3px;transition:height .4s ease;"></div>
                                <div style="font-size:.75rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;">${escapeHtml(label)}</div>
                            </div>`;
                        }).join('')}
                    </div>`;
                }

                body.innerHTML = `
            <!-- Range label -->
            <div style="margin-bottom:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <span class="badge badge-blue" style="font-size:.78rem;padding:4px 10px;">${escapeHtml(rpt.range_label || '')}</span>
                <span style="font-size:.78rem;color:var(--muted);">${escapeHtml(rpt.summary_note || '')}</span>
            </div>

            <!-- KPI Cards -->
            <div class="reports-kpi">
                <div class="kpi-card">
                    <div class="kpi-label">Total Collections</div>
                    <div class="kpi-val" style="color:var(--brand);">${fmt(sm.total_amount)}</div>
                    <div style="font-size:.72rem;color:var(--muted);margin-top:4px;">${sm.total_transactions} transaction${sm.total_transactions !== 1 ? 's' : ''}</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Staff Collections</div>
                    <div class="kpi-val">${fmt(sm.staff_amount)}</div>
                    <div style="font-size:.72rem;color:var(--muted);margin-top:4px;">${sm.staff_transactions} posted by staff</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Client App Payments</div>
                    <div class="kpi-val">${fmt(sm.client_amount)}</div>
                    <div style="font-size:.72rem;color:var(--muted);margin-top:4px;">${sm.client_transactions} via mobile</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Unique Clients</div>
                    <div class="kpi-val">${sm.unique_clients}</div>
                    <div style="font-size:.72rem;color:var(--muted);margin-top:4px;">${sm.active_staff} active staff</div>
                </div>
            </div>

            <!-- Daily Collections Trend -->
            <div class="card" style="margin-bottom:16px;">
                <div class="card-header">
                    <span class="material-symbols-rounded ms">show_chart</span>
                    <h3>Daily Collections Trend</h3>
                </div>
                ${dailyTrend(daily)}
            </div>

            <!-- Source & Method Breakdown -->
            <div class="two-col" style="margin-bottom:16px;">
                <div class="card">
                    <div class="card-header">
                        <span class="material-symbols-rounded ms">compare_arrows</span>
                        <h3>By Collection Source</h3>
                    </div>
                    <div style="padding:4px 0;">
                        ${sources.map(s => `
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-top:1px solid var(--border);">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span class="material-symbols-rounded ms" style="font-size:18px;color:${s.source_key === 'staff' ? '#6366f1' : '#10b981'};">${s.source_key === 'staff' ? 'badge' : 'phone_android'}</span>
                                    <div>
                                        <div style="font-size:.85rem;font-weight:600;">${escapeHtml(s.source_label)}</div>
                                        <div style="font-size:.72rem;color:var(--muted);">${s.transaction_count} transaction${s.transaction_count !== 1 ? 's' : ''}</div>
                                    </div>
                                </div>
                                <strong style="color:var(--brand);">${fmt(s.total_amount)}</strong>
                            </div>`).join('') || '<p style="padding:20px;color:var(--muted);font-size:.85rem;">No data.</p>'}
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <span class="material-symbols-rounded ms">account_balance_wallet</span>
                        <h3>By Payment Method</h3>
                    </div>
                    <div style="padding:4px 0;">
                        ${miniBar(methods, 'payment_method', 'total_amount', m => {
                            const method = String(m.payment_method || '').toLowerCase();
                            if (method.includes('cash')) return '#22c55e';
                            if (method.includes('gcash') || method.includes('mobile')) return '#3b82f6';
                            if (method.includes('bank') || method.includes('transfer')) return '#6366f1';
                            if (method.includes('check')) return '#f59e0b';
                            return 'var(--brand)';
                        })}
                    </div>
                </div>
            </div>

            <!-- Staff & Client Breakdown -->
            <div class="two-col" style="margin-bottom:16px;">
                <div class="card">
                    <div class="card-header">
                        <span class="material-symbols-rounded ms">groups</span>
                        <h3>Staff Performance</h3>
                    </div>
                    <div style="padding:4px 0;">
                        ${staff.length ? staff.map(s => `
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-top:1px solid var(--border);">
                                <div>
                                    <div style="font-size:.85rem;font-weight:600;">${escapeHtml(s.staff_name)}</div>
                                    <div style="font-size:.72rem;color:var(--muted);">${escapeHtml(s.staff_role)} · ${s.transaction_count} txn · ${s.unique_clients} client${s.unique_clients != 1 ? 's' : ''}</div>
                                </div>
                                <strong style="color:var(--brand);">${fmt(s.total_amount)}</strong>
                            </div>`).join('') : '<p style="padding:20px;color:var(--muted);font-size:.85rem;">No staff collections this period.</p>'}
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <span class="material-symbols-rounded ms">person</span>
                        <h3>Top Paying Clients</h3>
                    </div>
                    <div style="padding:4px 0;">
                        ${clients.length ? clients.map((c, i) => `
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 20px;border-top:1px solid var(--border);">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span style="width:22px;height:22px;border-radius:50%;background:${i < 3 ? 'var(--brand)' : 'var(--border)'};color:${i < 3 ? '#fff' : 'var(--muted)'};display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;flex-shrink:0;">${i + 1}</span>
                                    <div>
                                        <div style="font-size:.83rem;font-weight:500;">${escapeHtml(c.client_name)}</div>
                                        <div style="font-size:.72rem;color:var(--muted);">${c.transaction_count} payment${c.transaction_count != 1 ? 's' : ''}</div>
                                    </div>
                                </div>
                                <strong style="font-size:.85rem;">${fmt(c.total_amount)}</strong>
                            </div>`).join('') : '<p style="padding:20px;color:var(--muted);font-size:.85rem;">No client payments this period.</p>'}
                    </div>
                </div>
            </div>

            <!-- Recent Transactions Ledger -->
            <div class="card">
                <div class="card-header">
                    <span class="material-symbols-rounded ms">receipt_long</span>
                    <h3>Recent Transactions</h3>
                    <span class="badge badge-gray" style="font-size:.7rem;">${recent.length} shown</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr>
                            <th>Reference</th><th>Client</th><th>Loan #</th>
                            <th>Amount</th><th>Method</th><th>Source</th><th>Date</th><th>Status</th>
                        </tr></thead>
                        <tbody>
                            ${recent.length ? recent.map(t => `<tr>
                                <td class="td-mono" style="font-size:.78rem;">${escapeHtml(t.reference_no || t.receipt_number || '—')}</td>
                                <td class="td-bold">${escapeHtml(t.client_name)}</td>
                                <td class="td-muted">${escapeHtml(t.loan_number || '—')}</td>
                                <td class="td-bold" style="color:var(--brand);">${fmt(t.amount)}</td>
                                <td class="td-muted">${escapeHtml(t.payment_method || '—')}</td>
                                <td>${t.source_key === 'staff'
                                    ? '<span class="badge badge-blue" style="font-size:.68rem;">Staff</span>'
                                    : '<span class="badge badge-green" style="font-size:.68rem;">App</span>'}</td>
                                <td class="td-muted">${fmtDate(t.transaction_date)}</td>
                                <td>${badge(t.transaction_status)}</td>
                            </tr>`).join('') : '<tr class="empty-row"><td colspan="8">No transactions found for this period.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            </div>`;
            } catch (error) {
                console.error(error);
                body.innerHTML = '<p style="color:var(--muted);padding:24px;">Could not load report data.</p>';
            }
        }

        function exportReportsPDF() {
            const element = document.getElementById('reportsBody');
            if (!element) return;
            
            const opt = {
                margin:       [10, 10, 10, 10],
                filename:     'Reports_Analytics.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(element).save();
        }

        // ── Users ──────────────────────────────────────────────────────
        async function loadUsers() {
            const tbody = document.getElementById('usersTbody');
            if (!tbody) return;
            const r = await fetch('../../../microfin_backend/api/api_auth.php?action=list_users');
            const d = await r.json();
            if (d.status !== 'success') {
                tbody.innerHTML = '<tr class="empty-row"><td colspan="5">The team directory is unavailable right now.</td></tr>';
                return;
            }
            const rows = d.data || [];
            if (!rows.length) {
                tbody.innerHTML = '<tr class="empty-row"><td colspan="5">No staff accounts are available for this tenant.</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map(u => `<tr>
        <td class="td-bold">${u.first_name || ''} ${u.last_name || ''} <span style="font-size:.78rem;color:var(--muted);">(${u.username})</span></td>
        <td class="td-muted">${u.email || '—'}</td>
        <td class="td-muted">${u.department || '—'}</td>
        <td class="td-muted">${u.position || u.role_name || '—'}</td>
        <td>${badge(u.status)}</td>
    </tr>`).join('');
        }

        // ── Close on backdrop click ─────────────────────────────────────
        document.querySelectorAll('.modal-backdrop').forEach(bd => {
            bd.addEventListener('click', e => {
                if (e.target !== bd) return;
                if (bd.id === 'dashboardPopupModal') {
                    dismissDashboardPopup();
                    return;
                }
                closeModal(bd.id);
            });
        });

        // ── Team Directory Functions ─────────────────────────────────────
        function openManageStaffModal(userId, fullName, roleId, status) {
            document.getElementById('manage_user_id').value = userId;
            document.getElementById('manageStaffNameDisplay').textContent = "Editing: " + fullName;
            document.getElementById('manage_role_id').value = roleId;
            
            let st = status || 'Active';
            if (st === 'Locked' || st === 'Inactive') st = 'Suspended';
            if (!['Active','Suspended'].includes(st)) {
                st = 'Suspended';
            }
            document.getElementById('manage_status').value = st;
            
            openModal('manageStaffModal');
        }

        async function activateStaff(userId, roleId) {
            if (!await showConfirmPopup('Are you sure you want to reactivate this staff member account?', { title: 'Activate Staff' })) {
                return;
            }

            try {
                const res = await fetch('../../../microfin_backend/api/api_team_manage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        user_id: userId,
                        role_id: roleId,
                        status: 'Active'
                    })
                }).then(r => r.json());

                if (res.status === 'success') {
                    await showAlertPopup(res.message, { title: 'Success', variant: 'success' });
                    window.location.reload();
                } else {
                    await showAlertPopup(res.message || 'Failed to activate staff.', { title: 'Error', variant: 'danger' });
                }
            } catch (err) {
                console.error(err);
                await showAlertPopup('A server error occurred. Please try again.', { title: 'Error', variant: 'danger' });
            }
        }


        const manageForm = document.getElementById('manageStaffForm');
        if (manageForm) {
            manageForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = document.getElementById('btnSubmitManage');
                const ogText = btn.textContent;
                btn.disabled = true;
                btn.innerHTML = '<span class="material-symbols-rounded ms" style="font-size:16px; animation:spin 1s linear infinite;">sync</span> Saving...';

                try {
                    const res = await fetch('../../../microfin_backend/api/api_team_manage.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            user_id: document.getElementById('manage_user_id').value,
                            role_id: document.getElementById('manage_role_id').value,
                            status: document.getElementById('manage_status').value
                        })
                    }).then(r => r.json());

                    if (res.status === 'success') {
                        await showAlertPopup(res.message, { title: 'Success', variant: 'success' });
                        window.location.reload();
                    } else {
                        await showAlertPopup(res.message || 'Failed to update staff.', { title: 'Error', variant: 'danger' });
                    }
                } catch (err) {
                    console.error(err);
                    await showAlertPopup('A server error occurred. Please try again.', { title: 'Error', variant: 'danger' });
                } finally {
                    btn.disabled = false;
                    btn.textContent = ogText;
                }
            });
        }

        // ── Profile Functions ───────────────────────────────────────────
        const profileForm = document.getElementById('profileForm');
        const btnSaveProfile = document.getElementById('btnSaveProfile');
        const profEmail = document.getElementById('prof_email');
        
        // Modal Elements
        const emailModal = document.getElementById('emailChangeModal');
        const newEmailInput = document.getElementById('new_email_input');
        const emailMsg = document.getElementById('email_msg');
        const btnSendOtp = document.getElementById('btnSendOtp');
        const emailStep1 = document.getElementById('emailStep1');
        const emailStep2 = document.getElementById('emailStep2');
        const sentToEmail = document.getElementById('sentToEmail');
        const otpInput = document.getElementById('otp_input');
        const btnVerifyOtp = document.getElementById('btnVerifyOtp');

        let emailDebounceTimer;

        function openEmailChangeModal() {
            if (emailModal) {
                emailModal.classList.add('active');
                emailStep1.style.display = 'block';
                emailStep2.style.display = 'none';
                newEmailInput.value = '';
                newEmailInput.style.borderColor = 'var(--border)';
                emailMsg.textContent = '';
                btnSendOtp.disabled = true;
                otpInput.value = '';
            }
        }

        function closeEmailModal() {
            if (emailModal) {
                emailModal.classList.remove('active');
            }
        }

        // Password change handler (inline form)
        const currentPasswordInput = document.getElementById('current_password');
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordValidationMsg = document.getElementById('password_validation_msg');
        const btnChangePassword = document.getElementById('btnChangePassword');

        function validatePasswordForm() {
            const currentPassword = currentPasswordInput.value.trim();
            const newPassword = newPasswordInput.value.trim();
            const confirmPassword = confirmPasswordInput.value.trim();

            let errors = [];

            if (!currentPassword) {
                errors.push('Current password is required');
            }

            if (!newPassword) {
                errors.push('New password is required');
            } else if (newPassword.length < 8) {
                errors.push('New password must be at least 8 characters');
            }

            if (!confirmPassword) {
                errors.push('Please confirm your new password');
            } else if (newPassword !== confirmPassword) {
                errors.push('Passwords do not match');
            }

            if (errors.length > 0) {
                passwordValidationMsg.style.color = '#f87171';
                passwordValidationMsg.textContent = errors.join('. ');
                btnChangePassword.disabled = true;
                return false;
            }

            passwordValidationMsg.style.color = '#22c55e';
            passwordValidationMsg.textContent = 'Ready to update password';
            btnChangePassword.disabled = false;
            return true;
        }

        // Add real-time validation
        if (currentPasswordInput) {
            currentPasswordInput.addEventListener('input', validatePasswordForm);
        }
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', validatePasswordForm);
        }
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', validatePasswordForm);
        }

        // Password change handler
        if (btnChangePassword) {
            btnChangePassword.addEventListener('click', async () => {
                if (!validatePasswordForm()) return;

                const currentPassword = currentPasswordInput.value.trim();
                const newPassword = newPasswordInput.value.trim();

                btnChangePassword.disabled = true;
                btnChangePassword.textContent = 'Updating...';
                passwordValidationMsg.style.color = 'var(--text-muted)';
                passwordValidationMsg.textContent = 'Verifying current password...';

                try {
                    const res = await fetch('../../../microfin_backend/api/api_profile_password_change.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'change_password',
                            current_password: currentPassword,
                            new_password: newPassword
                        })
                    }).then(r => r.json());

                    if (res.status === 'success') {
                        passwordValidationMsg.style.color = '#22c55e';
                        passwordValidationMsg.textContent = 'Password updated successfully!';
                        btnChangePassword.textContent = 'Updated!';
                        currentPasswordInput.value = '';
                        newPasswordInput.value = '';
                        confirmPasswordInput.value = '';
                        setTimeout(() => {
                            btnChangePassword.textContent = 'Update Password';
                            passwordValidationMsg.textContent = '';
                        }, 2000);
                    } else {
                        passwordValidationMsg.style.color = '#f87171';
                        passwordValidationMsg.textContent = res.message || 'Failed to update password';
                        btnChangePassword.disabled = false;
                        btnChangePassword.textContent = 'Update Password';
                    }
                } catch (err) {
                    passwordValidationMsg.style.color = '#f87171';
                    passwordValidationMsg.textContent = 'Network error: Could not connect to server';
                    btnChangePassword.disabled = false;
                    btnChangePassword.textContent = 'Update Password';
                }
            });
        }

        // Debounced real-time email check
        if (newEmailInput) {
            newEmailInput.addEventListener('input', () => {
                clearTimeout(emailDebounceTimer);
                const emailValue = newEmailInput.value.trim();
                const currentEmail = profEmail ? profEmail.getAttribute('data-current') : '';

                if (!emailValue || !emailValue.includes('@')) {
                    newEmailInput.style.borderColor = 'var(--border)';
                    emailMsg.textContent = '';
                    btnSendOtp.disabled = true;
                    return;
                }

                if (emailValue.toLowerCase() === currentEmail.toLowerCase()) {
                    newEmailInput.style.borderColor = '#ef4444';
                    emailMsg.style.color = '#ef4444';
                    emailMsg.textContent = 'This is already your current email.';
                    btnSendOtp.disabled = true;
                    return;
                }

                emailMsg.style.color = 'var(--muted)';
                emailMsg.textContent = 'Checking availability...';
                btnSendOtp.disabled = true;

                emailDebounceTimer = setTimeout(async () => {
                    try {
                        const res = await fetch('../../../microfin_backend/api/api_profile_email_change.php', {
                            method: 'POST', headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'check_email', email: emailValue })
                        }).then(r => r.json());
                        
                        if (res.status === 'success') {
                            newEmailInput.style.borderColor = '#22c55e';
                            emailMsg.style.color = '#22c55e';
                            emailMsg.textContent = 'Email is available!';
                            btnSendOtp.disabled = false;
                        } else {
                            newEmailInput.style.borderColor = '#ef4444';
                            emailMsg.style.color = '#ef4444';
                            emailMsg.textContent = res.message || 'Email is already taken.';
                            btnSendOtp.disabled = true;
                        }
                    } catch (err) {
                        console.error(err);
                        emailMsg.textContent = 'Error checking email.';
                    }
                }, 500);
            });
        }

        if (btnSendOtp) {
            btnSendOtp.addEventListener('click', async () => {
                const emailValue = newEmailInput.value.trim();
                btnSendOtp.disabled = true;
                let originalText = btnSendOtp.textContent;
                btnSendOtp.innerHTML = '<span class="material-symbols-rounded ms" style="font-size:16px; animation:spin 1s linear infinite;">sync</span> Sending...';

                try {
                    const otpRes = await fetch('../../../microfin_backend/api/api_profile_email_change.php', {
                        method: 'POST', headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'send_otp', email: emailValue })
                    }).then(r => r.json());
                    
                    if (otpRes.status !== 'success') {
                        alert(otpRes.message || 'Unable to send OTP to that email.');
                        btnSendOtp.disabled = false;
                    } else {
                        sentToEmail.textContent = emailValue;
                        emailStep1.style.display = 'none';
                        emailStep2.style.display = 'block';
                        otpInput.focus();
                    }
                } catch (err) {
                    console.error(err);
                    alert('Failed to interact with server.');
                    btnSendOtp.disabled = false;
                } finally {
                    btnSendOtp.innerHTML = originalText;
                }
            });
        }

        if (btnVerifyOtp) {
            btnVerifyOtp.addEventListener('click', async () => {
                const emailValue = newEmailInput.value.trim();
                const code = otpInput.value.trim();
                if (code.length !== 6) {
                    alert('Please enter the 6-digit code.');
                    return;
                }

                btnVerifyOtp.disabled = true;
                let originalText = btnVerifyOtp.textContent;
                btnVerifyOtp.innerHTML = '<span class="material-symbols-rounded ms" style="font-size:16px; animation:spin 1s linear infinite;">sync</span> Verifying...';

                try {
                    const verifyRes = await fetch('../../../microfin_backend/api/api_profile_email_change.php', {
                        method: 'POST', headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'verify_otp', email: emailValue, otp_code: code })
                    }).then(r => r.json());
                    
                    if (verifyRes.status !== 'success') {
                        alert(verifyRes.message || 'Invalid or expired OTP.');
                        btnVerifyOtp.disabled = false;
                    } else {
                        const firstName = document.getElementById('prof_first_name').value.trim();
                        const lastName = document.getElementById('prof_last_name').value.trim();
                        const username = document.getElementById('prof_username').value.trim();
                        const contactNumber = document.getElementById('prof_contact_number').value.trim();

                        const updateRes = await fetch('../../../microfin_backend/api/api_profile_update.php', {
                            method: 'POST', headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ 
                                first_name: firstName, 
                                last_name: lastName, 
                                username: username,
                                phone_number: contactNumber,
                                email: emailValue 
                            })
                        }).then(r => r.json());

                        if (updateRes.status === 'success') {
                            alert('Email successfully changed and verified!');
                            window.location.reload();
                        } else {
                            alert(updateRes.message || 'Verification succeeded, but failed to commit profile.');
                        }
                    }
                } catch (err) {
                    console.error(err);
                    alert('Failed to interact with server.');
                    btnVerifyOtp.disabled = false;
                } finally {
                    btnVerifyOtp.innerHTML = originalText;
                }
            });
        }

        if (profileForm) {
            profileForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const firstName = document.getElementById('prof_first_name').value.trim();
                const lastName = document.getElementById('prof_last_name').value.trim();
                const username = document.getElementById('prof_username').value.trim();
                const contactNumber = document.getElementById('prof_contact_number').value.trim();
                const currentEmailVal = profEmail ? profEmail.getAttribute('data-current') : '';
                
                if (!firstName || !lastName || !username) {
                    alert("First Name, Last Name, and Username are required.");
                    return;
                }

                btnSaveProfile.disabled = true;
                let oldText = btnSaveProfile.innerHTML;
                btnSaveProfile.innerHTML = '<span class="material-symbols-rounded ms" style="font-size:16px; animation:spin 1s linear infinite;">sync</span> Saving...';

                try {
                    const updateRes = await fetch('../../../microfin_backend/api/api_profile_update.php', {
                        method: 'POST', headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            first_name: firstName, 
                            last_name: lastName, 
                            username: username,
                            phone_number: contactNumber,
                            email: currentEmailVal 
                        })
                    }).then(r => r.json());

                    if (updateRes.status === 'success') {
                        alert('Profile details updated successfully!');
                        window.location.reload();
                    } else {
                        alert(updateRes.message || 'Failed to update name.');
                    }
                } catch (err) {
                    console.error(err);
                } finally {
                    btnSaveProfile.disabled = false;
                    btnSaveProfile.innerHTML = 'Save Details';
                }
            });
        }

        // ── Client View Modal ─────────────────────────────────────
        async function viewClient(client) {
            currentClient = client; // Store for refresh
            const modal = document.getElementById('clientViewModal');
            if (!modal) return;

            // Populate personal info
            document.getElementById('clientViewName').textContent = (client.first_name || '') + ' ' + (client.middle_name || '') + ' ' + (client.last_name || '') + (client.suffix ? ', ' + client.suffix : '');
            document.getElementById('clientViewDOB').textContent = client.date_of_birth || 'N/A';
            document.getElementById('clientViewGender').textContent = client.gender || 'N/A';
            document.getElementById('clientViewCivilStatus').textContent = client.civil_status || 'N/A';
            document.getElementById('clientViewNationality').textContent = client.nationality || 'N/A';

            // Contact info
            document.getElementById('clientViewEmail').textContent = client.email_address || 'N/A';
            document.getElementById('clientViewPhone').textContent = client.contact_number || 'N/A';

            // Hide alternate contact if none
            const altContactDiv = document.getElementById('clientViewAltContact').parentElement;
            if (client.alternate_contact) {
                altContactDiv.style.display = 'block';
                document.getElementById('clientViewAltContact').textContent = client.alternate_contact;
            } else {
                altContactDiv.style.display = 'none';
            }

            // Present address
            const presentAddr = [client.present_house_no, client.present_street, client.present_barangay, client.present_city, client.present_province, client.present_postal_code].filter(Boolean).join(', ');
            document.getElementById('clientViewPresentAddress').textContent = presentAddr || 'N/A';

            // Permanent address - hide if same as present
            const permAddr = [client.permanent_house_no, client.permanent_street, client.permanent_barangay, client.permanent_city, client.permanent_province, client.permanent_postal_code].filter(Boolean).join(', ');
            const permAddrDiv = document.getElementById('clientViewPermanentAddress').parentElement;
            const sameAsPresentDiv = document.getElementById('clientViewSameAsPresent').parentElement;

            if (client.same_as_present || permAddr === presentAddr) {
                permAddrDiv.style.display = 'none';
                sameAsPresentDiv.style.display = 'none';
            } else {
                permAddrDiv.style.display = 'block';
                sameAsPresentDiv.style.display = 'block';
                document.getElementById('clientViewPermanentAddress').textContent = permAddr || 'N/A';
                document.getElementById('clientViewSameAsPresent').textContent = client.same_as_present ? 'Yes' : 'No';
            }

            // Employment info
            document.getElementById('clientViewEmployment').textContent = client.employment_status || 'N/A';
            document.getElementById('clientViewOccupation').textContent = client.occupation || 'N/A';
            document.getElementById('clientViewEmployer').textContent = client.employer_name || 'N/A';
            document.getElementById('clientViewEmployerContact').textContent = client.employer_contact || 'N/A';
            document.getElementById('clientViewIncome').textContent = '₱' + (parseFloat(client.monthly_income || 0).toLocaleString('en-PH', {minimumFractionDigits: 2}));
            document.getElementById('clientViewOtherIncome').textContent = client.other_income_source || 'N/A';
            document.getElementById('clientViewOtherIncomeAmount').textContent = client.other_income_amount ? '₱' + parseFloat(client.other_income_amount).toLocaleString('en-PH', {minimumFractionDigits: 2}) : 'N/A';

            // Comaker info - hide if none
            const comakerSection = document.getElementById('clientViewComakerName').closest('div').parentElement;
            if (client.comaker_name) {
                comakerSection.style.display = 'grid';
                document.getElementById('clientViewComakerName').textContent = client.comaker_name;
                document.getElementById('clientViewComakerRelationship').textContent = client.comaker_relationship || 'N/A';
                document.getElementById('clientViewComakerContact').textContent = client.comaker_contact || 'N/A';
                document.getElementById('clientViewComakerIncome').textContent = client.comaker_income ? '₱' + parseFloat(client.comaker_income).toLocaleString('en-PH', {minimumFractionDigits: 2}) : 'N/A';
            } else {
                comakerSection.style.display = 'none';
            }

            // ID info
            document.getElementById('clientViewIdType').textContent = client.id_type || 'N/A';
            document.getElementById('clientViewIdNumber').textContent = 'Loading...';

            // Credit info
            document.getElementById('clientViewCreditLimit').textContent = '₱' + (parseFloat(client.credit_limit || 0).toLocaleString('en-PH', {minimumFractionDigits: 2}));
            document.getElementById('clientViewCreditTier').textContent = client.credit_limit_tier || 'N/A';

            // Policy metadata (credit limit calculation) - format nicely
            const policyMeta = client.policy_metadata ? JSON.parse(client.policy_metadata) : null;
            const policyMetaDiv = document.getElementById('clientViewPolicyMetadata');
            if (policyMeta) {
                let html = '<div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">';
                
                if (policyMeta.potential_limit !== undefined) {
                    html += `<div><strong>Potential Limit:</strong></div><div>₱${parseFloat(policyMeta.potential_limit).toLocaleString('en-PH', {minimumFractionDigits: 2})}</div>`;
                }
                if (policyMeta.starting_score !== undefined) {
                    html += `<div><strong>Starting Score:</strong></div><div>${policyMeta.starting_score}</div>`;
                }
                if (policyMeta.score_band) {
                    html += `<div><strong>Score Band:</strong></div><div>${policyMeta.score_band}</div>`;
                }
                if (policyMeta.income_at_submission !== undefined) {
                    html += `<div><strong>Income at Submission:</strong></div><div>₱${parseFloat(policyMeta.income_at_submission).toLocaleString('en-PH', {minimumFractionDigits: 2})}</div>`;
                }
                if (policyMeta.limit_calculation && policyMeta.limit_calculation.reason) {
                    html += `<div><strong>Calculation:</strong></div><div>${policyMeta.limit_calculation.reason}</div>`;
                }
                if (policyMeta.timestamp) {
                    html += `<div><strong>Calculated At:</strong></div><div>${policyMeta.timestamp}</div>`;
                }
                
                html += '</div>';
                policyMetaDiv.innerHTML = html;
            } else {
                policyMetaDiv.textContent = 'No credit limit calculation data available.';
            }

            // Status info
            document.getElementById('clientViewStatus').textContent = client.client_status || 'N/A';
            document.getElementById('clientViewVerification').textContent = client.document_verification_status || 'N/A';

            // Hide approve button if client is already approved
            const approveBtn = document.getElementById('approveClientBtn');
            if (client.client_status === 'Approved' || client.document_verification_status === 'Approved') {
                approveBtn.style.display = 'none';
            } else {
                approveBtn.style.display = 'inline-block';
            }

            // Hide verification rejection reason if none
            const rejectionReasonDiv = document.getElementById('clientViewVerificationReason').parentElement;
            if (client.verification_rejection_reason) {
                rejectionReasonDiv.style.display = 'block';
                document.getElementById('clientViewVerificationReason').textContent = client.verification_rejection_reason;
            } else {
                rejectionReasonDiv.style.display = 'none';
            }

            // Dates
            document.getElementById('clientViewRegistration').textContent = client.registration_date || 'N/A';
            document.getElementById('clientViewCreated').textContent = client.created_at || 'N/A';
            document.getElementById('clientViewLoans').textContent = client.total_loans || 0;

            // Fetch documents
            try {
                const docsRes = await fetch('api/api_get_client_details.php?client_id=' + client.client_id);
                const docsData = await docsRes.json();
                
                // Update ID number
                document.getElementById('clientViewIdNumber').textContent = docsData.id_number || 'N/A';
                
                const docsDiv = document.getElementById('clientViewDocuments');
                
                // Hardcoded document types
                const docTypes = [
                    { key: 'proof_of_legitimacy', label: 'Proof of Legitimacy' },
                    { key: 'proof_of_income', label: 'Proof of Income' },
                    { key: 'proof_of_billing', label: 'Proof of Billing' },
                    { key: 'scanned_id', label: 'Scanned ID' }
                ];
                
                let tableHtml = `
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:var(--bg-body);">
                                <th style="padding:10px 12px; text-align:left; font-size:0.75rem; font-weight:600; color:var(--muted);">Document Type</th>
                                <th style="padding:10px 12px; text-align:center; font-size:0.75rem; font-weight:600; color:var(--muted);">Status</th>
                                <th style="padding:10px 12px; text-align:center; font-size:0.75rem; font-weight:600; color:var(--muted);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                docTypes.forEach(docType => {
                    // Find matching document by file_path
                    const matchedDoc = docsData.documents?.find(doc => 
                        doc.file_path && doc.file_path.includes(docType.key)
                    );
                    
                    const status = matchedDoc?.verification_status || 'Pending';
                    const filePath = matchedDoc?.file_path || '';
                    const docId = matchedDoc?.client_document_id || '';
                    
                    let statusBadge = '';
                    if (status === 'Verified') {
                        statusBadge = '<span style="display:inline-block; padding:4px 8px; border-radius:4px; font-size:0.7rem; font-weight:600; background:#10b981; color:white;">Verified</span>';
                    } else if (status === 'Rejected') {
                        statusBadge = '<span style="display:inline-block; padding:4px 8px; border-radius:4px; font-size:0.7rem; font-weight:600; background:#ef4444; color:white;">Rejected</span>';
                    } else {
                        statusBadge = '<span style="display:inline-block; padding:4px 8px; border-radius:4px; font-size:0.7rem; font-weight:600; background:#f59e0b; color:white;">Pending</span>';
                    }
                    
                    tableHtml += `
                        <tr style="border-top:1px solid var(--border);">
                            <td style="padding:10px 12px; font-size:0.85rem;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span class="material-symbols-rounded ms" style="font-size:18px; color:var(--muted);">description</span>
                                    ${docType.label}
                                </div>
                            </td>
                            <td style="padding:10px 12px; text-align:center;">${statusBadge}</td>
                            <td style="padding:10px 12px; text-align:center;">
                                ${filePath ? `
                                    <div style="display:flex; justify-content:center; gap:6px;">
                                        <button class="btn btn-sm btn-outline" onclick="viewDocument('${escapeHtml(filePath)}')" style="padding:6px 10px; font-size:0.75rem; display:flex; align-items:center; gap:4px;" title="View Document">
                                            <span class="material-symbols-rounded ms" style="font-size:16px;">visibility</span>
                                            View
                                        </button>
                                        ${status !== 'Verified' ? `
                                        <button class="btn btn-sm" style="padding:6px 10px; font-size:0.75rem; background:#10b981; color:white; border:none; display:flex; align-items:center; gap:4px; transition:all 0.2s;" onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10b981'" onclick="verifyDocument('${client.client_id}', '${docId}', '${escapeHtml(filePath)}')" title="Verify Document">
                                            <span class="material-symbols-rounded ms" style="font-size:16px;">check_circle</span>
                                            Verify
                                        </button>
                                        ` : ''}
                                        <button class="btn btn-sm" style="padding:6px 10px; font-size:0.75rem; background:#ef4444; color:white; border:none; display:flex; align-items:center; gap:4px; transition:all 0.2s;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'" onclick="rejectDocument('${client.client_id}', '${docId}', '${escapeHtml(filePath)}')" title="Reject Document">
                                            <span class="material-symbols-rounded ms" style="font-size:16px;">cancel</span>
                                            Reject
                                        </button>
                                    </div>
                                ` : '<span style="color:var(--muted); font-size:0.85rem;">—</span>'}
                            </td>
                        </tr>
                    `;
                });
                
                tableHtml += '</tbody></table>';
                docsDiv.innerHTML = tableHtml;
                
                // Check if all documents are verified to enable Approve button
                const approveBtn = document.getElementById('approveClientBtn');
                const allVerified = docsData.documents && docsData.documents.length > 0 && 
                    docsData.documents.every(doc => doc.verification_status === 'Verified');
                
                if (allVerified) {
                    approveBtn.disabled = false;
                    approveBtn.style.opacity = '1';
                    approveBtn.style.cursor = 'pointer';
                } else {
                    approveBtn.disabled = true;
                    approveBtn.style.opacity = '0.5';
                    approveBtn.style.cursor = 'not-allowed';
                }
            } catch (err) {
                console.error('Documents error:', err);
                document.getElementById('clientViewDocuments').textContent = 'Failed to load documents: ' + err.message;
                document.getElementById('clientViewIdNumber').textContent = 'N/A';
            }

            modal.classList.add('open');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function viewDocument(filePath) {
            console.log('Viewing document:', filePath);
            if (!filePath) {
                showToast('File path not available', 'error');
                return;
            }
            // Use proxy endpoint to serve file with session
            const proxyUrl = 'api/api_view_document.php?path=' + encodeURIComponent(filePath);
            window.open(proxyUrl, '_blank');
        }

        async function verifyDocument(clientId, docId, filePath) {
            showConfirmModal('Verify Document', 'Are you sure you want to verify this document?', async () => {
                try {
                    const res = await fetch('api/api_verify_document.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            client_document_id: docId,
                            action: 'verify'
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast('Document verified successfully', 'success');
                        viewClient(currentClient);
                    } else {
                        showToast('Failed to verify: ' + data.message, 'error');
                    }
                } catch (err) {
                    showToast('Error: ' + err.message, 'error');
                }
            });
        }

        async function rejectDocument(clientId, docId, filePath) {
            showInputModal('Reject Document', 'Please enter rejection reason:', async (reason) => {
                if (!reason || reason.trim() === '') {
                    showToast('Rejection reason is required', 'error');
                    return false;
                }
                
                try {
                    const res = await fetch('api/api_verify_document.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            client_document_id: docId,
                            action: 'reject',
                            reason: reason
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast('Document rejected successfully', 'success');
                        viewClient(currentClient);
                    } else {
                        showToast('Failed to reject: ' + data.message, 'error');
                    }
                } catch (err) {
                    showToast('Error: ' + err.message, 'error');
                }
                return true;
            });
        }

        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                border-radius: 8px;
                color: white;
                font-size: 0.9rem;
                font-weight: 500;
                z-index: 10000;
                animation: slideIn 0.3s ease;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            `;
            
            if (type === 'success') {
                toast.style.background = '#10b981';
            } else if (type === 'error') {
                toast.style.background = '#ef4444';
            } else {
                toast.style.background = '#3b82f6';
            }
            
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        function showConfirmModal(title, message, onConfirm) {
            const modal = document.createElement('div');
            modal.className = 'modal-backdrop top open';
            modal.style.cssText = 'z-index: 10000;';
            modal.innerHTML = `
                <div class="modal" style="width: 100%; max-width: 400px; background:var(--bg-card); border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
                    <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid var(--border);">
                        <h3 style="margin:0; font-size:1.1rem;">${title}</h3>
                        <span class="material-symbols-rounded ms" style="cursor:pointer; color:var(--muted);" onclick="this.closest('.modal-backdrop').remove()">close</span>
                    </div>
                    <div class="modal-body" style="padding: 24px;">
                        <p style="margin:0; color:var(--text);">${message}</p>
                    </div>
                    <div style="padding: 16px 24px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:12px;">
                        <button class="btn btn-outline" onclick="this.closest('.modal-backdrop').remove()" style="padding:8px 16px;">Cancel</button>
                        <button class="btn" style="padding:8px 16px; background:var(--brand); color:white; border:none;" id="confirmBtn">Confirm</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            modal.querySelector('#confirmBtn').onclick = () => {
                modal.remove();
                onConfirm();
            };
        }

        function showInputModal(title, placeholder, onConfirm) {
            const modal = document.createElement('div');
            modal.className = 'modal-backdrop top open';
            modal.style.cssText = 'z-index: 10000;';
            modal.innerHTML = `
                <div class="modal" style="width: 100%; max-width: 400px; background:var(--bg-card); border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
                    <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid var(--border);">
                        <h3 style="margin:0; font-size:1.1rem;">${title}</h3>
                        <span class="material-symbols-rounded ms" style="cursor:pointer; color:var(--muted);" onclick="this.closest('.modal-backdrop').remove()">close</span>
                    </div>
                    <div class="modal-body" style="padding: 24px;">
                        <input type="text" id="modalInput" placeholder="${placeholder}" style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:6px; font-size:0.9rem; background:var(--bg-body); color:var(--text);">
                    </div>
                    <div style="padding: 16px 24px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:12px;">
                        <button class="btn btn-outline" onclick="this.closest('.modal-backdrop').remove()" style="padding:8px 16px;">Cancel</button>
                        <button class="btn" style="padding:8px 16px; background:var(--brand); color:white; border:none;" id="confirmBtn">Confirm</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            const input = modal.querySelector('#modalInput');
            input.focus();
            
            modal.querySelector('#confirmBtn').onclick = () => {
                const value = input.value;
                const shouldClose = onConfirm(value);
                if (shouldClose !== false) {
                    modal.remove();
                }
            };
            
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    modal.querySelector('#confirmBtn').click();
                }
            });
        }

        // Add toast animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);

        async function approveClient() {
            showConfirmModal('Approve Client', 'Are you sure you want to approve this client? This will update their document verification status and credit limit.', async () => {
                try {
                    const res = await fetch('api/api_approve_client.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            client_id: currentClient.client_id
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast('Client approved successfully', 'success');
                        closeModal('clientViewModal');
                        // Refresh the client table
                        location.reload();
                    } else {
                        showToast('Failed to approve: ' + data.message, 'error');
                    }
                } catch (err) {
                    showToast('Error: ' + err.message, 'error');
                }
            });
        }

        let currentClient = null;
    </script>

    <?php
    // Helper for PHP-rendered badges
    function statusBadgePHP($s)
    {
        $map = [
            'Active' => 'badge-green',
            'Approved' => 'badge-green',
            'Posted' => 'badge-green',
            'Fully Paid' => 'badge-blue',
            'Under Review' => 'badge-blue',
            'For Approval' => 'badge-purple',
            'Credit Investigation' => 'badge-purple',
            'Document Verification' => 'badge-purple',
            'Overdue' => 'badge-red',
            'Rejected' => 'badge-red',
            'Blacklisted' => 'badge-red',
            'Cancelled' => 'badge-gray',
            'Withdrawn' => 'badge-gray',
            'Inactive' => 'badge-gray',
            'Draft' => 'badge-amber',
            'Submitted' => 'badge-amber',
        ];
        $cls = $map[$s] ?? 'badge-gray';
        return '<span class="badge ' . $cls . '">' . htmlspecialchars($s) . '</span>';
    }
    ?>

    <!-- ── CLIENT VIEW MODAL ── -->
    <div class="modal-backdrop top" id="clientViewModal">
        <div class="modal" style="width: 100%; max-width: 800px; max-height: 90vh; background:var(--bg-card); border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.1); overflow-y: auto;">
            <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid var(--border); position:sticky; top:0; background:var(--bg-card); z-index:10;">
                <h3 style="margin:0; font-size:1.2rem;">Client Details</h3>
                <span class="material-symbols-rounded ms" style="cursor:pointer; color:var(--muted);" onclick="closeModal('clientViewModal')">close</span>
            </div>
            <div class="modal-body" style="padding: 24px;">
                <!-- Personal Information -->
                <h4 style="margin:0 0 16px 0; font-size:0.95rem; font-weight:600; color:var(--brand);">Personal Information</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Full Name</label>
                        <div id="clientViewName" style="font-size:0.95rem; font-weight:600; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Date of Birth</label>
                        <div id="clientViewDOB" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Gender</label>
                        <div id="clientViewGender" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Civil Status</label>
                        <div id="clientViewCivilStatus" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Nationality</label>
                        <div id="clientViewNationality" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                </div>

                <!-- Contact Information -->
                <h4 style="margin:0 0 16px 0; font-size:0.95rem; font-weight:600; color:var(--brand);">Contact Information</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Email Address</label>
                        <div id="clientViewEmail" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Contact Number</label>
                        <div id="clientViewPhone" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Alternate Contact</label>
                        <div id="clientViewAltContact" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                </div>

                <!-- Address Information -->
                <h4 style="margin:0 0 16px 0; font-size:0.95rem; font-weight:600; color:var(--brand);">Address Information</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                    <div style="grid-column: span 2;">
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Present Address</label>
                        <div id="clientViewPresentAddress" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div style="grid-column: span 2;">
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Permanent Address</label>
                        <div id="clientViewPermanentAddress" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Same as Present</label>
                        <div id="clientViewSameAsPresent" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                </div>

                <!-- Employment Information -->
                <h4 style="margin:0 0 16px 0; font-size:0.95rem; font-weight:600; color:var(--brand);">Employment Information</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Employment Status</label>
                        <div id="clientViewEmployment" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Occupation</label>
                        <div id="clientViewOccupation" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Employer Name</label>
                        <div id="clientViewEmployer" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Employer Contact</label>
                        <div id="clientViewEmployerContact" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Monthly Income</label>
                        <div id="clientViewIncome" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Other Income Source</label>
                        <div id="clientViewOtherIncome" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Other Income Amount</label>
                        <div id="clientViewOtherIncomeAmount" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                </div>

                <!-- Comaker Information -->
                <h4 style="margin:0 0 16px 0; font-size:0.95rem; font-weight:600; color:var(--brand);">Comaker Information</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Comaker Name</label>
                        <div id="clientViewComakerName" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Relationship</label>
                        <div id="clientViewComakerRelationship" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Comaker Contact</label>
                        <div id="clientViewComakerContact" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Comaker Income</label>
                        <div id="clientViewComakerIncome" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                </div>

                <!-- ID Information -->
                <h4 style="margin:0 0 16px 0; font-size:0.95rem; font-weight:600; color:var(--brand);">ID Information</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">ID Type</label>
                        <div id="clientViewIdType" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">ID Number</label>
                        <div id="clientViewIdNumber" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                </div>

                <!-- Credit Information -->
                <h4 style="margin:0 0 16px 0; font-size:0.95rem; font-weight:600; color:var(--brand);">Credit Information</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Credit Limit</label>
                        <div id="clientViewCreditLimit" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Credit Tier</label>
                        <div id="clientViewCreditTier" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                </div>

                <!-- Policy Metadata (Credit Limit Calculation) -->
                <h4 style="margin:0 0 16px 0; font-size:0.95rem; font-weight:600; color:var(--brand);">Credit Limit Calculation (Policy Metadata)</h4>
                <div style="margin-bottom: 24px;">
                    <div id="clientViewPolicyMetadata" style="font-size:0.85rem; color:var(--text);">—</div>
                </div>

                <!-- Status Information -->
                <h4 style="margin:0 0 16px 0; font-size:0.95rem; font-weight:600; color:var(--brand);">Status Information</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Client Status</label>
                        <div id="clientViewStatus" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Verification Status</label>
                        <div id="clientViewVerification" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                    <div style="grid-column: span 2;">
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Verification Rejection Reason</label>
                        <div id="clientViewVerificationReason" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                </div>

                <!-- Documents -->
                <h4 style="margin:0 0 16px 0; font-size:0.95rem; font-weight:600; color:var(--brand);">Documents</h4>
                <div style="margin-bottom: 24px;">
                    <div id="clientViewDocuments" style="font-size:0.9rem; color:var(--text);">Loading...</div>
                </div>

                <!-- Dates -->
                <h4 style="margin:0 0 16px 0; font-size:0.95rem; font-weight:600; color:var(--brand);">Dates</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Registration Date</label>
                        <div id="clientViewRegistration" style="font-size:0.85rem; color:var(--muted);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Created At</label>
                        <div id="clientViewCreated" style="font-size:0.85rem; color:var(--muted);">—</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:600; color:var(--muted); margin-bottom:4px;">Total Loans</label>
                        <div id="clientViewLoans" style="font-size:0.9rem; color:var(--text);">—</div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('clientViewModal')" style="padding: 10px 20px;">Close</button>
                    <button type="button" class="btn" id="approveClientBtn" onclick="approveClient()" style="padding: 10px 20px; background:var(--brand); color:white; border:none; opacity:0.5; cursor:not-allowed;" disabled>Approve</button>
                </div>
            </div>
        </div>
    </div>

</body>

</html>

