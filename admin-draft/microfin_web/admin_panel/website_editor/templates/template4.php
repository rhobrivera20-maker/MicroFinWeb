<?php
/**
 * Template 4 — Split Screen Creative
 * 
 * This template powers both the live editor canvas (contenteditable) AND the public site.
 * When loaded inside the editor (admin_panel/website_editor/index.php), JS enables contenteditable.
 * When loaded via site.php, the contenteditable attributes are stripped by output buffering.
 * 
 * Expected variables (set by calling page):
 *   $primary, $border_color, $border_radius, $shadow,
 *   $text_heading_color, $text_body_color, $btn_bg_color, $btn_text_color,
 *   $logo, $company_name, $display_name, $short_name,
 *   $hero_title, $hero_subtitle, $hero_badge_text, $display_image,
 *   $about_body, $download_description, $footer_desc,
 *   $contact_address, $contact_phone, $contact_email, $contact_hours,
 *   $services (array), $loan_products (array), $sec_styles (array),
 *   $show_services, $show_stats, $show_loan_calc, $show_about, $show_download,
 *   $e (htmlspecialchars helper), getBgStyle() function
 */

// Ensure all required variables are defined with defaults
$primary = $primary ?? '#0f4c81';
$border_color = $border_color ?? '#e2e8f0';
$border_radius = $border_radius ?? '4';
$shadow = $shadow ?? '0.1';
$font_family = $font_family ?? 'Inter';
$text_heading_color = $text_heading_color ?? '#0a1628';
$text_body_color = $text_body_color ?? '#4a5568';
$btn_bg_color = $btn_bg_color ?? $primary;
$btn_text_color = $btn_text_color ?? '#ffffff';
$logo = $logo ?? '';
$company_name = $company_name ?? 'MicroFin';
$short_name = $short_name ?? '';
$display_name = $short_name ?: $company_name;
$hero_title = $hero_title ?? 'Bold Moves. Smart Loans.';
$hero_subtitle = $hero_subtitle ?? 'Financing that keeps up with your ambition. No waiting, no hassle.';
$hero_badge_text = $hero_badge_text ?? 'Fast Approval';
$about_body = $about_body ?? 'We cut through the complexity to give you straightforward lending.';
$download_description = $download_description ?? 'Get the app that puts your loans in your pocket.';
$contact_address = $contact_address ?? '789 Innovation Drive';
$contact_phone = $contact_phone ?? '0900-555-1234';
$contact_email = $contact_email ?? 'team@microfin.os';
$contact_hours = $contact_hours ?? '24/7 Support';
$footer_desc = $footer_desc ?? 'Financing for the bold and ambitious.';
$hero_image = $hero_image ?? '';
$display_image = $hero_image ?: 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=600&h=800&q=80';
$services = $services ?? [];
$show_services = $show_services ?? true;
$show_stats = $show_stats ?? true;
$show_loan_calc = $show_loan_calc ?? true;
$show_about = $show_about ?? true;
$show_download = $show_download ?? true;
$sec_styles = $sec_styles ?? [];
$tenant_id = $tenant_id ?? '';
$data = $data ?? [];
$site_slug = $site_slug ?? '';
$tenant_slug = $tenant_slug ?? '';
$tenant_referral_code = $tenant_referral_code ?? '';
$tenant_reference_qr_url = $tenant_reference_qr_url ?? '';
$download_href = $download_href ?? '';
$e = $e ?? function($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); };
$is_editor_context = $is_editor_context ?? false;
$is_demo_preview = $is_demo_preview ?? false;

$loan_products = $loan_products ?? [];
$loan_calc_products = is_array($loan_products) ? array_values($loan_products) : [];
$loan_calc_default = $loan_calc_products[0] ?? [
    'product_name' => 'Demo Personal Loan',
    'product_type' => 'Personal Loan',
    'interest_rate' => 2.5,
    'interest_type' => 'Flat',
    'min_amount' => 1000,
    'max_amount' => 50000,
    'min_term_months' => 1,
    'max_term_months' => 12,
    'processing_fee_percentage' => 5,
    'insurance_fee_percentage' => 0,
    'service_charge' => 0,
    'documentary_stamp' => 0,
];

$loan_calc_step = static function (float $min, float $max): int {
    $range = max(0, $max - $min);
    if ($range <= 20000) return 50;
    if ($range <= 100000) return 100;
    if ($range <= 500000) return 500;
    return 1000;
};

$loan_calc_money = static function (float $amount): string {
    return '&#8369;' . number_format(round($amount), 0);
};

$loan_calc_min_amount = max(0, (float)($loan_calc_default['min_amount'] ?? 1000));
$loan_calc_max_amount = max($loan_calc_min_amount, (float)($loan_calc_default['max_amount'] ?? 50000));
$loan_calc_amount_step = $loan_calc_step($loan_calc_min_amount, $loan_calc_max_amount);
$loan_calc_amount_value = $loan_calc_min_amount;
if ($loan_calc_max_amount > $loan_calc_min_amount) {
    $loan_calc_amount_value = round((($loan_calc_min_amount + $loan_calc_max_amount) / 2) / $loan_calc_amount_step) * $loan_calc_amount_step;
    $loan_calc_amount_value = min($loan_calc_max_amount, max($loan_calc_min_amount, $loan_calc_amount_value));
}

$loan_calc_min_term = max(1, (int)($loan_calc_default['min_term_months'] ?? 1));
$loan_calc_max_term = max($loan_calc_min_term, (int)($loan_calc_default['max_term_months'] ?? 12));
$loan_calc_term_value = max($loan_calc_min_term, min($loan_calc_max_term, (int)round(($loan_calc_min_term + $loan_calc_max_term) / 2)));

$loan_calc_rate = max(0, (float)($loan_calc_default['interest_rate'] ?? 2.5)) / 100;
$loan_calc_type = (string)($loan_calc_default['interest_type'] ?? 'Flat');
$loan_calc_processing = max(0, (float)($loan_calc_default['processing_fee_percentage'] ?? 5));
$loan_calc_insurance = max(0, (float)($loan_calc_default['insurance_fee_percentage'] ?? 0));
$loan_calc_service_charge = max(0, (float)($loan_calc_default['service_charge'] ?? 0));
$loan_calc_doc_stamp = max(0, (float)($loan_calc_default['documentary_stamp'] ?? 0));

$loan_calc_monthly = 0.0;
$loan_calc_interest_total = 0.0;
$loan_calc_total = 0.0;

if ($loan_calc_type === 'Diminishing') {
    $loan_calc_monthly = $loan_calc_rate > 0
        ? $loan_calc_amount_value * ($loan_calc_rate * pow(1 + $loan_calc_rate, $loan_calc_term_value)) / (pow(1 + $loan_calc_rate, $loan_calc_term_value) - 1)
        : ($loan_calc_amount_value / max(1, $loan_calc_term_value));
    $loan_calc_total = $loan_calc_monthly * $loan_calc_term_value;
    $loan_calc_interest_total = $loan_calc_total - $loan_calc_amount_value;
} else {
    $loan_calc_interest_total = $loan_calc_amount_value * $loan_calc_rate * $loan_calc_term_value;
    $loan_calc_total = $loan_calc_amount_value + $loan_calc_interest_total;
    $loan_calc_monthly = $loan_calc_total / max(1, $loan_calc_term_value);
}

$loan_calc_fee_total = ($loan_calc_amount_value * ($loan_calc_processing / 100))
    + ($loan_calc_amount_value * ($loan_calc_insurance / 100))
    + $loan_calc_service_charge
    + $loan_calc_doc_stamp;
?>
<style>
    :root {
        --brand: <?php echo $primary ?? '#0f4c81'; ?>;
        --brand-rgb: <?php echo isset($primary) ? implode(', ', array_map('hexdec', str_split(ltrim($primary, '#'), 2))) : '15, 76, 129'; ?>;
        --accent: #00b4d8;
        --accent-rgb: 0, 180, 216;
        --border-clr: <?php echo $border_color ?? '#e2e8f0'; ?>;
        --radius: <?php echo $border_radius ?? '4'; ?>px;
        --shadow: 0 4px 24px rgba(0, 0, 0, <?php echo $shadow ?? '0.1'; ?>);
        --text-heading: <?php echo $text_heading_color ?? '#0a1628'; ?>;
        --text-body: <?php echo $text_body_color ?? '#4a5568'; ?>;
        --btn-bg: <?php echo $btn_bg_color ?? ($primary ?? '#0f4c81'); ?>;
        --btn-text: <?php echo $btn_text_color ?? '#ffffff'; ?>;
        --bg-surface: #f0f4f8;
        --bg-dark: #0a1628;
        --bg-darker: #060e1a;
    }

    * {
        box-sizing: border-box;
    }

    body {
        font-family: var(--font-family, 'Inter'), -apple-system, BlinkMacSystemFont, sans-serif;
        color: var(--text-heading);
        background: var(--bg-surface);
        margin: 0;
        padding: 0;
    }

    .tpl4-wrapper {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .tpl4-nav {
        background: #fff;
        border-bottom: 2px solid var(--brand);
        padding: 1rem 0;
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .tpl4-nav-inner {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .tpl4-nav-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .tpl4-nav-brand-text {
        font-weight: 700;
        font-size: 1.25rem;
        color: var(--brand);
    }

    .tpl4-nav-tabs {
        display: flex;
        gap: 0.5rem;
    }

    .tpl4-nav-tab {
        padding: 0.5rem 1rem;
        background: var(--bg-surface);
        color: var(--text-body);
        text-decoration: none;
        border-radius: 4px;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
    }

    .tpl4-nav-tab:hover,
    .tpl4-nav-tab.active {
        background: var(--brand);
        color: #fff;
    }

    .tpl4-btn {
        background: var(--btn-bg);
        color: var(--btn-text);
        padding: 0.625rem 1.5rem;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.2s;
        border: none;
        font-size: 0.875rem;
    }

    .tpl4-btn:hover {
        background: #0a3d6b;
    }

    .tpl4-hero {
        background: var(--bg-dark);
        padding: 5rem 0;
    }

    .tpl4-hero-grid {
        display: grid;
        grid-template-columns: 58% 42%;
        gap: 3rem;
        align-items: center;
    }

    .tpl4-hero-left {
        color: #fff;
    }

    .tpl4-badge {
        display: inline-block;
        background: rgba(0, 180, 216, 0.15);
        color: var(--accent);
        border: 1px solid rgba(0, 180, 216, 0.3);
        padding: 0.5rem 1rem;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 1.5rem;
    }

    .tpl4-title {
        font-size: 3rem;
        font-weight: 700;
        line-height: 1.2;
        margin-bottom: 1rem;
        color: #fff;
    }

    .tpl4-subtitle {
        font-size: 1.125rem;
        line-height: 1.6;
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 2rem;
        max-width: 480px;
    }

    .tpl4-hero-ctas {
        display: flex;
        gap: 1rem;
    }

    .tpl4-hero-cta {
        padding: 0.75rem 1.5rem;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.875rem;
    }

    .tpl4-hero-cta-primary {
        background: var(--accent);
        color: var(--bg-dark);
    }

    .tpl4-hero-cta-secondary {
        background: transparent;
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .tpl4-hero-right {
        display: flex;
        align-items: center;
    }

    .tpl4-dashboard {
        background: #111d2e;
        border-radius: 4px;
        padding: 1.5rem;
        width: 100%;
    }

    .tpl4-dashboard-row {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .tpl4-dashboard-row:last-child {
        margin-bottom: 0;
    }

    .tpl4-dashboard-label {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.75rem;
        font-weight: 500;
        min-width: 80px;
    }

    .tpl4-dashboard-bar {
        flex: 1;
        height: 8px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 2px;
        overflow: hidden;
    }

    .tpl4-dashboard-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--accent), var(--brand));
        border-radius: 2px;
        width: 0;
        animation: tpl4-bar-grow 1.5s ease forwards;
    }

    .tpl4-dashboard-value {
        color: #fff;
        font-size: 0.875rem;
        font-weight: 600;
        min-width: 60px;
        text-align: right;
    }

    @keyframes tpl4-bar-grow {
        from { width: 0; }
        to { width: var(--target-width); }
    }

    .tpl4-hero-line {
        height: 4px;
        background: var(--accent);
        width: 100%;
        margin-top: 3rem;
    }

    .tpl4-ticker {
        background: var(--bg-dark);
        overflow: hidden;
        padding: 0.75rem 0;
    }

    .tpl4-ticker-inner {
        display: inline-flex;
        white-space: nowrap;
        animation: tpl4-ticker 20s linear infinite;
    }

    .tpl4-ticker-item {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.875rem;
        margin-right: 3rem;
    }

    @keyframes tpl4-ticker {
        from { transform: translateX(0); }
        to { transform: translateX(-50%); }
    }

    .tpl4-services {
        background: var(--bg-surface);
        padding: 4rem 0;
    }

    .tpl4-section-header {
        margin-bottom: 2.5rem;
    }

    .tpl4-section-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--brand);
        margin-bottom: 0.5rem;
    }

    .tpl4-section-underline {
        display: block;
        width: 40px;
        height: 3px;
        background: var(--accent);
    }

    .tpl4-services-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
    }

    .tpl4-service-card {
        background: #fff;
        border: 1px solid var(--border-clr);
        border-radius: 4px;
        padding: 1.5rem;
        position: relative;
        transition: all 0.2s;
    }

    .tpl4-service-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        height: 100%;
        width: 3px;
        background: var(--accent);
        opacity: 0;
        transition: opacity 0.2s;
    }

    .tpl4-service-card:hover {
        box-shadow: 0 0 0 2px var(--accent);
    }

    .tpl4-service-card:hover::before {
        opacity: 1;
    }

    .tpl4-service-card::after {
        content: attr(data-index);
        position: absolute;
        top: 0.5rem;
        right: 0.75rem;
        font-size: 3rem;
        font-weight: 900;
        color: var(--brand);
        opacity: 0.04;
        line-height: 1;
    }

    .tpl4-service-icon {
        width: 36px;
        height: 36px;
        background: var(--bg-surface);
        border-radius: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        color: var(--brand);
    }

    .tpl4-service-title {
        font-size: 1.125rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--text-heading);
    }

    .tpl4-service-desc {
        color: var(--text-body);
        line-height: 1.5;
        font-size: 0.875rem;
    }

    .tpl4-download {
        background: var(--bg-dark);
        color: #fff;
        padding: 4rem 0;
    }

    .tpl4-download-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 3rem;
    }

    .tpl4-download-left {
        padding-right: 2rem;
    }

    .tpl4-download-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }

    .tpl4-download-desc {
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 1.5rem;
        line-height: 1.6;
    }

    .tpl4-download-btn {
        background: var(--accent);
        color: var(--bg-dark);
        padding: 0.875rem 2rem;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 600;
        display: inline-block;
    }

    .tpl4-download-btn:hover {
        background: #00a0c0;
    }

    .tpl4-download-right {
        border-left: 1px solid rgba(255, 255, 255, 0.1);
        padding-left: 2rem;
    }

    .shared-app-step {
        padding-left: 1rem;
        border-left: 4px solid var(--accent);
        margin-bottom: 1.5rem;
    }

    .shared-app-step:last-child {
        margin-bottom: 0;
    }

    .shared-app-step-label {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        opacity: 0.6;
        margin-bottom: 0.5rem;
        color: var(--accent);
    }

    .shared-app-step-title {
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .shared-app-step-copy {
        font-size: 0.875rem;
        line-height: 1.5;
        opacity: 0.7;
        margin: 0;
    }

    .shared-app-qr-toggle {
        margin-top: 2rem;
    }

    .shared-app-qr-toggle summary {
        list-style: none;
        cursor: pointer;
    }

    .shared-app-qr-toggle summary::-webkit-details-marker {
        display: none;
    }

    .shared-app-qr-toggle-btn {
        background: transparent;
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: #fff;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        border-radius: 4px;
    }

    .shared-app-qr-toggle-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: #fff;
    }

    .shared-app-qr-panel {
        margin-top: 1.5rem;
    }

    .shared-app-qr-card {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
        padding: 1.5rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 4px;
    }

    .shared-app-qr-card img {
        width: 140px;
        height: 140px;
        background: #fff;
        padding: 8px;
    }

    .shared-app-code {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        font-weight: 600;
        border-radius: 4px;
    }

    .tpl4-footer {
        background: var(--bg-darker);
        color: #fff;
        padding: 3rem 0;
    }

    .tpl4-footer-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
    }

    .tpl4-footer-brand {
        border-right: 1px solid rgba(0, 180, 216, 0.2);
        padding-right: 2rem;
    }

    .tpl4-footer-brand-name {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
    }

    .tpl4-footer-desc {
        color: rgba(255, 255, 255, 0.5);
        line-height: 1.5;
        font-size: 0.875rem;
    }

    .tpl4-footer-contact-title {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 1rem;
        color: var(--accent);
    }

    .tpl4-footer-contact-item {
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .tpl4-footer-contact-item i {
        color: var(--accent);
    }

    .tpl4-footer-bottom {
        background: var(--bg-dark);
        border-top: 1px solid var(--accent);
        padding: 1rem 0;
        margin-top: 2rem;
    }

    @media (max-width: 768px) {
        .tpl4-hero-grid {
            grid-template-columns: 1fr;
        }

        .tpl4-hero-right {
            display: none;
        }

        .tpl4-title {
            font-size: 2.2rem;
        }

        .tpl4-services-grid {
            grid-template-columns: 1fr;
        }

        .tpl4-download-grid {
            grid-template-columns: 1fr;
        }

        .tpl4-download-right {
            border-left: none;
            padding-left: 0;
            margin-top: 2rem;
        }

        .tpl4-footer-grid {
            grid-template-columns: 1fr;
        }

        .tpl4-footer-brand {
            border-right: none;
            padding-right: 0;
        }

        .tpl4-nav-tabs {
            display: none;
        }
    }
</style>

<?php 
$tid_val = $tenant_id ?? ($data['tenant_id'] ?? '');
$is_editor = isset($is_editor_context)
    ? (bool)$is_editor_context
    : (strpos($_SERVER['PHP_SELF'], 'editor') !== false || strpos($_SERVER['PHP_SELF'], 'setup') !== false);
$script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
if (strpos($script, '/admin_panel/website_editor/') !== false) {
    $platform_base = dirname(dirname(dirname($script)));
} elseif (strpos($script, '/public_website/') !== false) {
    $platform_base = dirname(dirname($script));
} else {
    $platform_base = dirname($script);
}
$platform_base = rtrim(str_replace('\\', '/', (string)$platform_base), '/');
$login_query = 'tenant=' . urlencode($tid_val);
if (!$is_editor) {
    $login_query .= '&from_site=1';
}
$login_href = $platform_base . '/tenant_login/login.php?' . $login_query;
$download_identifier = (string)($site_slug ?? $tenant_slug ?? $tid_val);
$download_href = $download_href ?? ($platform_base . '/public_website/index.php?route=get-app&bank_id=' . urlencode($download_identifier));
$tenant_referral_code_value = trim((string)($tenant_referral_code ?? $tenant_slug ?? $site_slug ?? ''));
$tenant_qr_url = trim((string)($tenant_reference_qr_url ?? ''));
?>
<div class="tpl4-wrapper">
    <?php if (!($is_demo_preview ?? false)): ?>
    <nav class="tpl4-nav">
        <div class="container tpl4-nav-inner">
            <div class="tpl4-nav-brand">
                <img id="preview_logo" src="<?php echo $e($logo ?? ''); ?>" style="height: 40px; <?php if (!($logo ?? '')) echo 'display:none;'; ?>">
                <span class="tpl4-nav-brand-text display-short-name" contenteditable="false"><?php echo $e($display_name); ?></span>
            </div>
            <div class="tpl4-nav-tabs">
                <a href="#sec_services" class="tpl4-nav-tab">Services</a>
                <a href="#sec_download" class="tpl4-nav-tab">About</a>
                <a href="#sec_download" class="tpl4-nav-tab">Contact</a>
            </div>
            <a href="<?php echo $login_href; ?>" class="tpl4-btn" contenteditable="false">Login</a>
        </div>
    </nav>
    <?php endif; ?>

    <section id="sec_hero" class="tpl4-hero editable-section" style="<?php echo getBgStyle('sec_hero', $sec_styles, ''); ?>">
        <div class="container">
            <div class="tpl4-hero-grid">
                <div class="tpl4-hero-left">
                    <span class="tpl4-badge" data-edit="hero_badge_text" contenteditable="true"><?php echo $e($hero_badge_text); ?></span>
                    <h1 class="tpl4-title" data-edit="hero_title" contenteditable="true"><?php echo $e($hero_title); ?></h1>
                    <p class="tpl4-subtitle" data-edit="hero_subtitle" contenteditable="true"><?php echo $e($hero_subtitle); ?></p>
                    <div class="tpl4-hero-ctas">
                        <a href="#sec_download" class="tpl4-hero-cta tpl4-hero-cta-primary">Get Started</a>
                        <a href="#sec_services" class="tpl4-hero-cta tpl4-hero-cta-secondary">Learn More</a>
                    </div>
                </div>
                <div class="tpl4-hero-right">
                    <div class="tpl4-dashboard">
                        <div class="tpl4-dashboard-row">
                            <span class="tpl4-dashboard-label">APPROVAL RATE</span>
                            <div class="tpl4-dashboard-bar">
                                <div class="tpl4-dashboard-bar-fill" style="--target-width: 92%;"></div>
                            </div>
                            <span class="tpl4-dashboard-value">92%</span>
                        </div>
                        <div class="tpl4-dashboard-row">
                            <span class="tpl4-dashboard-label">DISBURSEMENT</span>
                            <div class="tpl4-dashboard-bar">
                                <div class="tpl4-dashboard-bar-fill" style="--target-width: 78%;"></div>
                            </div>
                            <span class="tpl4-dashboard-value">78%</span>
                        </div>
                        <div class="tpl4-dashboard-row">
                            <span class="tpl4-dashboard-label">SATISFACTION</span>
                            <div class="tpl4-dashboard-bar">
                                <div class="tpl4-dashboard-bar-fill" style="--target-width: 95%;"></div>
                            </div>
                            <span class="tpl4-dashboard-value">95%</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tpl4-hero-line"></div>
        </div>
    </section>

    <div class="tpl4-ticker">
        <div class="container">
            <div class="tpl4-ticker-inner">
                <span class="tpl4-ticker-item">Fast Approval</span>
                <span class="tpl4-ticker-item">Secure</span>
                <span class="tpl4-ticker-item">Transparent</span>
                <span class="tpl4-ticker-item">Trusted</span>
                <span class="tpl4-ticker-item">Fast Approval</span>
                <span class="tpl4-ticker-item">Secure</span>
                <span class="tpl4-ticker-item">Transparent</span>
                <span class="tpl4-ticker-item">Trusted</span>
            </div>
        </div>
    </div>

    <?php
    $show_services_val = $show_services ?? true;
    if (is_string($show_services_val)) $show_services_val = filter_var($show_services_val, FILTER_VALIDATE_BOOLEAN);
    ?>
    <section id="sec_services" class="tpl4-services editable-section" style="<?php echo getBgStyle('sec_services', $sec_styles, '#f0f4f8'); ?> <?php if (!$show_services_val) echo 'display:none;'; ?>">
        <div class="container">
            <div class="tpl4-section-header">
                <h2 class="tpl4-section-title">Our Services</h2>
                <span class="tpl4-section-underline"></span>
            </div>
            <div class="tpl4-services-grid">
                <?php foreach (($services ?? []) as $index => $svc): ?>
                    <div class="tpl4-service-card service-col position-relative" data-index="<?php echo $index + 1; ?>">
                        <button class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 delete-card" contenteditable="false">×</button>
                        <div class="tpl4-service-icon editable-icon-wrap">
                            <i class="ti ti-<?php echo $e($svc['icon'] ?? 'star'); ?> service-icon-text" style="font-size: 20px;"></i>
                        </div>
                        <h3 class="tpl4-service-title service-title" contenteditable="true"><?php echo $e($svc['title'] ?? 'Service Title'); ?></h3>
                        <p class="tpl4-service-desc service-desc" contenteditable="true"><?php echo $e($svc['description'] ?? 'Service description.'); ?></p>
                    </div>
                <?php endforeach; ?>
                <div class="tpl4-service-card" id="add_service_col" contenteditable="false" style="border-style: dashed; cursor: pointer; display: flex; align-items: center; justify-content: center; min-height: 140px;" onclick="addServiceCard()">
                    <div class="text-center" style="color: var(--text-body); opacity: 0.4;">
                        <i class="ti ti-plus" style="font-size: 2rem;"></i>
                        <div class="fw-bold mt-2" style="font-size: 0.875rem;">Add Service</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php
    $show_download_val = $show_download ?? true;
    if (is_string($show_download_val)) $show_download_val = filter_var($show_download_val, FILTER_VALIDATE_BOOLEAN);
    ?>
    <section id="sec_download" class="tpl4-download editable-section" style="<?php echo getBgStyle('sec_download', $sec_styles, '#0a1628'); ?> <?php if (!$show_download_val) echo 'display:none;'; ?>">
        <div class="container">
            <div class="tpl4-download-grid">
                <div class="tpl4-download-left">
                    <h2 class="tpl4-download-title">Get The App</h2>
                    <p class="tpl4-download-desc" data-edit="download_description" contenteditable="true"><?php echo $e($download_description); ?></p>
                    <?php if (!($is_demo_preview ?? false)): ?>
                    <a href="<?php echo $e($download_href); ?>" class="tpl4-download-btn" contenteditable="false">Download Now</a>
                    <?php endif; ?>
                </div>
                <div class="tpl4-download-right">
                    <div class="shared-app-step">
                        <div class="shared-app-step-label">Step 1</div>
                        <div class="shared-app-step-title">Install the MicroFin app</div>
                        <p class="shared-app-step-copy">The download button installs the company-branded MicroFin mobile app.</p>
                    </div>
                    <div class="shared-app-step">
                        <div class="shared-app-step-label">Step 2</div>
                        <div class="shared-app-step-title">Open Create Account and use the QR button</div>
                        <p class="shared-app-step-copy">That button reveals this institution QR so the app can unlock the form with the correct <strong>@<?php echo $e($tenant_slug ?? $site_slug ?? 'tenant'); ?></strong> suffix.</p>
                    </div>
                    <div class="shared-app-step">
                        <div class="shared-app-step-label">Step 3</div>
                        <div class="shared-app-step-title">Use the referral code if scanning is unavailable</div>
                        <p class="shared-app-step-copy">Manual fallback code: <strong><?php echo $e($tenant_referral_code_value !== '' ? $tenant_referral_code_value : ($tenant_slug ?? $site_slug ?? '')); ?></strong></p>
                    </div>
                    <details class="shared-app-qr-toggle" contenteditable="false">
                        <summary class="shared-app-qr-toggle-btn">Show Registration QR</summary>
                        <div class="shared-app-qr-panel">
                            <div class="shared-app-qr-card">
                                <?php if ($tenant_qr_url !== ''): ?>
                                    <img src="<?php echo $e($tenant_qr_url); ?>" alt="Tenant registration QR code">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center text-center" style="width:140px;height:140px;background:rgba(255,255,255,0.05);color:#fff;padding:15px;">
                                        Publish the site to generate the tenant QR code.
                                    </div>
                                <?php endif; ?>
                                <div class="shared-app-code">
                                    <i class="ti ti-ticket"></i>
                                    Referral Code: <?php echo $e($tenant_referral_code_value !== '' ? $tenant_referral_code_value : ($tenant_slug ?? $site_slug ?? '')); ?>
                                </div>
                            </div>
                        </div>
                    </details>
                </div>
            </div>
        </div>
    </section>

    <footer class="tpl4-footer">
        <div class="container">
            <div class="tpl4-footer-grid">
                <div class="tpl4-footer-brand">
                    <h5 class="tpl4-footer-brand-name display-company-name"><?php echo $e($company_name); ?></h5>
                    <p class="tpl4-footer-desc" data-edit="footer_desc" contenteditable="true"><?php echo $e($footer_desc); ?></p>
                </div>
                <div>
                    <h6 class="tpl4-footer-contact-title">Contact</h6>
                    <div class="tpl4-footer-contact-item" data-edit="contact_address" contenteditable="true">
                        <i class="ti ti-map-pin"></i>
                        <?php echo $e($contact_address); ?>
                    </div>
                    <div class="tpl4-footer-contact-item" data-edit="contact_phone" contenteditable="true">
                        <i class="ti ti-phone"></i>
                        <?php echo $e($contact_phone); ?>
                    </div>
                    <div class="tpl4-footer-contact-item" data-edit="contact_email" contenteditable="true">
                        <i class="ti ti-mail"></i>
                        <?php echo $e($contact_email); ?>
                    </div>
                    <div class="tpl4-footer-contact-item" data-edit="contact_hours" contenteditable="true">
                        <i class="ti ti-clock"></i>
                        <?php echo $e($contact_hours); ?>
                    </div>
                </div>
                <div>
                    <h6 class="tpl4-footer-contact-title">Quick Links</h6>
                    <div class="tpl4-footer-contact-item">
                        <a href="#sec_services" style="color: rgba(255,255,255,0.7); text-decoration: none;">Services</a>
                    </div>
                    <div class="tpl4-footer-contact-item">
                        <a href="#sec_download" style="color: rgba(255,255,255,0.7); text-decoration: none;">Download App</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="tpl4-footer-bottom">
            <div class="container text-center" style="color: rgba(255,255,255,0.4); font-size: 0.875rem;">
                © <?php echo date('Y'); ?> <?php echo $e($company_name); ?>. All rights reserved.
            </div>
        </div>
    </footer>
</div>

