<?php
/**
 * Template 9 — Tech Startup
 * 
 * Enterprise exclusive template with modern tech aesthetic, gradients, and dynamic elements.
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
$primary = $primary ?? '#8B7355';
$earth_secondary = $earth_secondary ?? '#A0826D';
$border_color = $border_color ?? '#D4C4B5';
$border_radius = $border_radius ?? '16';
$shadow = $shadow ?? '0.1';
$font_family = $font_family ?? 'Georgia';
$text_heading_color = $text_heading_color ?? '#4A3B2A';
$text_body_color = $text_body_color ?? '#6B5B4A';
$btn_bg_color = $btn_bg_color ?? $primary;
$btn_text_color = $btn_text_color ?? '#ffffff';
$logo = $logo ?? '';
$logo_bg = $logo_bg ?? ($pageData['logo_bg'] ?? 'transparent');
$company_name = $company_name ?? 'MicroFin';
$short_name = $short_name ?? '';
$display_name = $short_name ?: $company_name;
$hero_title = $hero_title ?? 'Finance Reimagined';
$hero_subtitle = $hero_subtitle ?? 'Next-generation lending powered by technology and innovation.';
$hero_badge_text = $hero_badge_text ?? 'Powered by MicroFin';
$about_body = $about_body ?? 'We leverage cutting-edge technology to deliver fast, smart, and secure financial solutions.';
$download_description = $download_description ?? 'Experience the future of lending with our intelligent mobile app.';
$contact_address = $contact_address ?? 'Tech Hub, Innovation District';
$contact_phone = $contact_phone ?? '0900-666-7777';
$contact_email = $contact_email ?? 'tech@microfin.os';
$contact_hours = $contact_hours ?? '24/7 Online Support';
$footer_desc = $footer_desc ?? 'Innovation in every transaction.';
$hero_image = $hero_image ?? '';
$display_image = $hero_image ?: 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=600&h=800&q=80';
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
        --brand: <?php echo $primary ?? '#8B7355'; ?>;
        --brand-rgb: <?php echo isset($primary) ? implode(', ', array_map('hexdec', str_split(ltrim($primary, '#'), 2))) : '139, 115, 85'; ?>;
        --logo-bg: <?php echo $logo_bg ?? 'transparent'; ?>;
        --earth-secondary: <?php echo $earth_secondary ?? '#A0826D'; ?>;
        --border-clr: <?php echo $border_color ?? '#D4C4B5'; ?>;
        --radius: <?php echo $border_radius ?? '16'; ?>px;
        --shadow: 0 12px 40px rgba(0, 0, 0, <?php echo $shadow ?? '0.1'; ?>);
        --text-heading: <?php echo $text_heading_color ?? '#4A3B2A'; ?>;
        --text-body: <?php echo $text_body_color ?? '#6B5B4A'; ?>;
        --btn-bg: <?php echo $btn_bg_color ?? ($primary ?? '#8B7355'); ?>;
        --btn-text: <?php echo $btn_text_color ?? '#ffffff'; ?>;
        --bg-cream: #F5F0E8;
        --bg-white: #ffffff;
        --text-dark: #4A3B2A;
    }

    * {
        box-sizing: border-box;
    }

    body {
        font-family: var(--font-family, 'Georgia'), 'Times New Roman', serif;
        color: var(--text-heading);
        background: var(--bg-cream);
        margin: 0;
        padding: 0;
    }

    .tpl9-wrapper {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .tpl9-nav {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(12px);
        border-bottom: 1px solid var(--border-clr);
        padding: 1rem 0;
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .tpl9-nav-inner {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .tpl9-nav-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .tpl9-nav-brand-text {
        font-weight: 700;
        font-size: 1.25rem;
        color: var(--brand);
        font-family: var(--font-family, 'Georgia'), serif;
    }

    .tpl9-logo-wrapper {
        background: var(--logo-bg, transparent);
        padding: 4px 8px;
        border-radius: calc(var(--radius) / 2);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background 0.3s ease;
    }

    .tpl9-btn {
        background: var(--btn-bg);
        color: var(--btn-text);
        padding: 0.625rem 1.5rem;
        border-radius: var(--radius);
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
        border: none;
        font-size: 0.875rem;
    }

    .tpl9-btn:hover {
        background: var(--earth-secondary);
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .tpl9-hero {
        padding: 6rem 0;
        background: var(--bg-cream);
        text-align: center;
        position: relative;
    }

    .tpl9-hero-content {
        max-width: 800px;
        margin: 0 auto;
    }

    .tpl9-badge {
        display: inline-block;
        background: var(--brand);
        color: #fff;
        padding: 0.5rem 1.25rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.875rem;
        margin-bottom: 2rem;
    }

    .tpl9-title {
        font-size: 3.5rem;
        font-weight: 700;
        line-height: 1.1;
        margin-bottom: 1.5rem;
        color: var(--text-heading);
        font-family: var(--font-family, 'Georgia'), serif;
    }

    .tpl9-subtitle {
        font-size: 1.25rem;
        line-height: 1.6;
        color: var(--text-body);
        margin-bottom: 2rem;
    }

    .tpl9-services {
        padding: 5rem 0;
        background: var(--bg-white);
    }

    .tpl9-section-header {
        text-align: center;
        margin-bottom: 3rem;
    }

    .tpl9-section-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-heading);
        margin-bottom: 0.5rem;
        font-family: var(--font-family, 'Georgia'), serif;
    }

    .tpl9-services-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 1.5rem;
    }

    .tpl9-service-card {
        background: var(--bg-cream);
        padding: 2rem;
        border-radius: var(--radius);
        border: 1px solid var(--border-clr);
        transition: all 0.3s;
    }

    .tpl9-service-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow);
    }

    .tpl9-service-icon {
        width: 56px;
        height: 56px;
        background: var(--brand);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        color: #fff;
    }

    .tpl9-service-title {
        font-size: 1.125rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--text-dark);
    }

    .tpl9-service-desc {
        color: var(--text-body);
        line-height: 1.5;
        font-size: 0.875rem;
    }

    .tpl9-download {
        padding: 5rem 0;
        background: var(--brand);
        color: #fff;
        text-align: center;
    }

    .tpl9-download-content {
        max-width: 700px;
        margin: 0 auto;
    }

    .tpl9-download-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 1rem;
        font-family: var(--font-family, 'Georgia'), serif;
    }

    .tpl9-download-desc {
        font-size: 1.125rem;
        opacity: 0.9;
        margin-bottom: 2rem;
    }

    .tpl9-download-btn {
        background: #fff;
        color: var(--brand);
        padding: 1rem 2.5rem;
        border-radius: var(--radius);
        text-decoration: none;
        font-weight: 700;
        display: inline-block;
    }

    .tpl9-download-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
    }

    .shared-app-steps {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
        margin-top: 3rem;
    }

    .shared-app-step {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: var(--radius);
        padding: 1.5rem;
        text-align: center;
    }

    .shared-app-step-number {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0 auto 1rem;
    }

    .shared-app-step-label {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        opacity: 0.7;
        margin-bottom: 0.5rem;
        color: #fff;
    }

    .shared-app-step-title {
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .shared-app-step-copy {
        font-size: 0.875rem;
        line-height: 1.5;
        opacity: 0.8;
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
        background: rgba(255,255,255,0.2);
        border: 2px solid rgba(255,255,255,0.3);
        color: #fff;
        padding: 0.75rem 1.5rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        border-radius: var(--radius);
    }

    .shared-app-qr-toggle-btn:hover {
        background: rgba(255,255,255,0.3);
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
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: var(--radius);
    }

    .shared-app-qr-card img {
        width: 140px;
        height: 140px;
        background: #fff;
        padding: 8px;
        border-radius: 8px;
    }

    .shared-app-code {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.25);
        font-weight: 700;
        border-radius: var(--radius);
    }

    .tpl9-footer {
        background: var(--bg-white);
        border-top: 1px solid var(--border-clr);
        padding: 3rem 0;
    }

    .tpl9-footer-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
    }

    .tpl9-footer-brand-name {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
        color: var(--brand);
        font-family: var(--font-family, 'Georgia'), serif;
    }

    .tpl9-footer-desc {
        color: var(--text-body);
        line-height: 1.5;
        font-size: 0.875rem;
    }

    .tpl9-footer-contact-title {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 1rem;
        color: var(--brand);
    }

    .tpl9-footer-contact-item {
        color: var(--text-body);
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .tpl9-footer-contact-item i {
        color: var(--brand);
    }

    /* Stats Section Styles */
    .tpl9-stats {
        padding: 5rem 0;
        background: var(--brand);
        color: var(--btn-text, #ffffff);
        text-align: center;
    }

    .tpl9-stats-title {
        font-size: 2.25rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
        font-family: var(--font-family, 'Georgia'), serif;
        color: var(--btn-text, #ffffff);
    }

    .tpl9-stats-subtitle {
        font-size: 1.125rem;
        opacity: 0.9;
        margin-bottom: 3.5rem;
        color: var(--btn-text, #ffffff);
    }

    .tpl9-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 2rem;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 1.5rem;
    }

    .tpl9-stat-item {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: var(--radius);
        padding: 2rem 1.5rem;
        transition: all 0.3s ease;
    }

    .tpl9-stat-item:hover {
        background: rgba(255, 255, 255, 0.12);
        transform: translateY(-3px);
    }

    .tpl9-stat-value {
        font-size: 2.75rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--btn-text, #ffffff);
        font-family: var(--font-family, 'Georgia'), serif;
    }

    .tpl9-stat-label {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        font-weight: 600;
        opacity: 0.85;
        color: var(--btn-text, #ffffff);
    }

    /* Loan Calculator Styles */
    .tpl9-calc {
        padding: 5rem 0;
        background: var(--bg-cream);
        border-top: 1px solid var(--border-clr);
    }

    .tpl9-calc-container {
        max-width: 820px;
        margin: 0 auto;
        background: var(--bg-white);
        border: 1px solid var(--border-clr);
        border-radius: var(--radius);
        padding: 3rem;
        box-shadow: var(--shadow);
    }

    .tpl9-calc-label {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: var(--text-heading);
        margin-bottom: 1rem;
        display: block;
    }

    .tpl9-lc-product-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .tpl9-lc-product-btn {
        background: var(--bg-cream);
        border: 1px solid var(--border-clr);
        border-radius: var(--radius);
        padding: 1.25rem;
        text-align: left;
        cursor: pointer;
        transition: all 0.3s;
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .tpl9-lc-product-btn:hover {
        background: var(--bg-white);
        border-color: var(--brand);
        box-shadow: var(--shadow);
        transform: translateY(-2px);
    }

    .tpl9-lc-product-btn.border-primary {
        background: var(--bg-white) !important;
        border-color: var(--brand) !important;
        border-width: 2px !important;
    }

    .tpl9-lc-product-name {
        font-weight: 700;
        font-size: 1rem;
        color: var(--text-heading);
        font-family: var(--font-family, 'Georgia'), serif;
    }

    .tpl9-lc-product-meta {
        font-size: 0.825rem;
        color: var(--text-body);
        line-height: 1.4;
    }

    .tpl9-calc-slider-card {
        background: var(--bg-cream);
        border: 1px solid var(--border-clr);
        border-radius: var(--radius);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .tpl9-calc-slider-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }

    .tpl9-calc-slider-title {
        font-weight: 700;
        font-size: 0.875rem;
        color: var(--text-body);
    }

    .tpl9-calc-slider-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--brand);
        font-family: var(--font-family, 'Georgia'), serif;
    }

    .tpl9-calc-range {
        width: 100%;
        accent-color: var(--brand);
        cursor: pointer;
        height: 6px;
        background: var(--border-clr);
        border-radius: 5px;
        outline: none;
    }

    .tpl9-calc-range-limits {
        display: flex;
        justify-content: space-between;
        font-size: 0.75rem;
        color: var(--text-body);
        margin-top: 0.5rem;
    }

    .tpl9-calc-results {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
        text-align: center;
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 1px solid var(--border-clr);
    }

    .tpl9-calc-res-label {
        font-size: 0.75rem;
        color: var(--text-body);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.25rem;
    }

    .tpl9-calc-res-val {
        font-size: 1.35rem;
        font-weight: 700;
        color: var(--text-heading);
        font-family: var(--font-family, 'Georgia'), serif;
    }

    /* About Section Styles */
    .tpl9-about {
        padding: 5.5rem 0;
        background: var(--bg-white);
        border-top: 1px solid var(--border-clr);
        text-align: center;
    }

    .tpl9-about-title {
        font-size: 2.25rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        font-family: var(--font-family, 'Georgia'), serif;
        color: var(--text-heading);
    }

    .tpl9-about-body {
        font-size: 1.15rem;
        line-height: 1.8;
        color: var(--text-body);
        max-width: 800px;
        margin: 0 auto;
    }

    @media (max-width: 768px) {
        .tpl9-title {
            font-size: 2.5rem;
        }

        .tpl9-services-grid {
            grid-template-columns: 1fr;
        }

        .tpl9-stats-grid {
            grid-template-columns: 1fr;
        }

        .tpl9-lc-product-grid {
            grid-template-columns: 1fr;
        }

        .tpl9-calc-results {
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .shared-app-steps {
            grid-template-columns: 1fr;
        }

        .tpl9-footer-grid {
            grid-template-columns: 1fr;
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
<div class="tpl9-wrapper">
    <?php if (!($is_demo_preview ?? false)): ?>
    <nav class="tpl9-nav">
        <div class="container tpl9-nav-inner">
            <div class="tpl9-nav-brand">
                <div class="tpl9-logo-wrapper">
                    <img id="preview_logo" src="<?php echo $e($logo ?? ''); ?>" style="height: 40px; <?php if (!($logo ?? '')) echo 'display:none;'; ?>">
                </div>
                <span class="tpl9-nav-brand-text display-short-name" contenteditable="false"><?php echo $e($display_name); ?></span>
            </div>
            <a href="<?php echo $login_href; ?>" class="tpl9-btn" contenteditable="false">Login</a>
        </div>
    </nav>
    <?php endif; ?>

    <section id="sec_hero" class="tpl9-hero editable-section" style="<?php echo getBgStyle('sec_hero', $sec_styles, 'var(--logo-bg, #F5F0E8)'); ?>">
        <div class="container">
            <div class="tpl9-hero-content">
                <span class="tpl9-badge" data-edit="hero_badge_text" contenteditable="true"><?php echo $e($hero_badge_text); ?></span>
                <h1 class="tpl9-title" data-edit="hero_title" contenteditable="true"><?php echo $e($hero_title); ?></h1>
                <p class="tpl9-subtitle" data-edit="hero_subtitle" contenteditable="true"><?php echo $e($hero_subtitle); ?></p>
            </div>
        </div>
    </section>

    <?php
    $show_services_val = $show_services ?? true;
    if (is_string($show_services_val)) $show_services_val = filter_var($show_services_val, FILTER_VALIDATE_BOOLEAN);
    ?>
    <section id="sec_services" class="tpl9-services editable-section" style="<?php echo getBgStyle('sec_services', $sec_styles, '#ffffff'); ?> <?php if (!$show_services_val) echo 'display:none;'; ?>">
        <div class="container">
            <div class="tpl9-section-header">
                <h2 class="tpl9-section-title">Our Services</h2>
            </div>
            <div class="tpl9-services-grid">
                <?php foreach (($services ?? []) as $index => $svc): ?>
                    <div class="tpl9-service-card service-col position-relative">
                        <button class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 delete-card" contenteditable="false">×</button>
                        <div class="tpl9-service-icon editable-icon-wrap">
                            <i class="ti ti-<?php echo $e($svc['icon'] ?? 'star'); ?> service-icon-text" style="font-size: 24px;"></i>
                        </div>
                        <h3 class="tpl9-service-title service-title" contenteditable="true"><?php echo $e($svc['title'] ?? 'Service Title'); ?></h3>
                        <p class="tpl9-service-desc service-desc" contenteditable="true"><?php echo $e($svc['description'] ?? 'Service description.'); ?></p>
                    </div>
                <?php endforeach; ?>
                <div class="tpl9-service-card" id="add_service_col" contenteditable="false" style="border-style: dashed; cursor: pointer; display: flex; align-items: center; justify-content: center; min-height: 160px;" onclick="addServiceCard()">
                    <div class="text-center" style="color: var(--text-body); opacity: 0.4;">
                        <i class="ti ti-plus" style="font-size: 2rem;"></i>
                        <div class="fw-bold mt-2" style="font-size: 0.875rem;">Add Service</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php
    $show_calc_val = $show_loan_calc ?? true;
    if (is_string($show_calc_val)) $show_calc_val = filter_var($show_calc_val, FILTER_VALIDATE_BOOLEAN);
    ?>
    <section id="sec_calc" class="tpl9-calc editable-section" style="<?php echo getBgStyle('sec_calc', $sec_styles, 'var(--logo-bg, #F5F0E8)'); ?> <?php if (!$show_calc_val) echo 'display:none;'; ?>">
        <div class="container" contenteditable="false">
            <div class="tpl9-section-header">
                <h2 class="tpl9-section-title">Estimate Your Payment</h2>
            </div>
            <div class="tpl9-calc-container">
                <div class="mb-4">
                    <label class="tpl9-calc-label">Select Loan Product</label>
                    <div class="tpl9-lc-product-grid">
                        <?php foreach (($loan_products ?? []) as $i => $prod): ?>
                            <button type="button" class="tpl9-lc-product-btn lc-product-btn <?php echo $i === 0 ? 'border-primary' : ''; ?>">
                                <div class="tpl9-lc-product-name"><?php echo $e($prod['product_name'] ?? 'Demo Loan'); ?></div>
                                <div class="tpl9-lc-product-meta">
                                    <strong><?php echo $e($prod['interest_rate'] ?? '2.5'); ?>% / mo · <?php echo $e($prod['interest_type'] ?? 'Flat'); ?></strong>
                                    <div>Range: <?php echo $loan_calc_money((float)($prod['min_amount'] ?? 1000)); ?> to <?php echo $loan_calc_money((float)($prod['max_amount'] ?? 50000)); ?></div>
                                    <div>Term: <?php echo (int)($prod['min_term_months'] ?? 1); ?>-<?php echo (int)($prod['max_term_months'] ?? 12); ?> months</div>
                                </div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="tpl9-calc-slider-card">
                    <div class="tpl9-calc-slider-header">
                        <span class="tpl9-calc-slider-title">Loan Amount</span>
                        <span class="tpl9-calc-slider-value" id="lc-amount-display"><?php echo $loan_calc_money($loan_calc_amount_value); ?></span>
                    </div>
                    <input type="range" class="tpl9-calc-range" id="lc-amount-slider" min="<?php echo (int)$loan_calc_min_amount; ?>" max="<?php echo (int)$loan_calc_max_amount; ?>" step="<?php echo (int)$loan_calc_amount_step; ?>" value="<?php echo (int)$loan_calc_amount_value; ?>">
                    <div class="tpl9-calc-range-limits">
                        <span id="lc-min-amount"><?php echo $loan_calc_money($loan_calc_min_amount); ?></span>
                        <span id="lc-max-amount"><?php echo $loan_calc_money($loan_calc_max_amount); ?></span>
                    </div>
                </div>
                <div class="tpl9-calc-slider-card">
                    <div class="tpl9-calc-slider-header">
                        <span class="tpl9-calc-slider-title">Loan Term</span>
                        <span class="tpl9-calc-slider-value" id="lc-term-display"><?php echo (int)$loan_calc_term_value; ?> mo</span>
                    </div>
                    <input type="range" class="tpl9-calc-range" id="lc-term-slider" min="<?php echo (int)$loan_calc_min_term; ?>" max="<?php echo (int)$loan_calc_max_term; ?>" value="<?php echo (int)$loan_calc_term_value; ?>">
                    <div class="tpl9-calc-range-limits">
                        <span id="lc-min-term"><?php echo (int)$loan_calc_min_term; ?> mo</span>
                        <span id="lc-max-term"><?php echo (int)$loan_calc_max_term; ?> mo</span>
                    </div>
                </div>
                <div class="tpl9-calc-results">
                    <div>
                        <div class="tpl9-calc-res-label">Monthly Payment</div>
                        <div class="tpl9-calc-res-val" id="lc-monthly"><?php echo $loan_calc_money($loan_calc_monthly); ?></div>
                    </div>
                    <div>
                        <div class="tpl9-calc-res-label">Total Interest</div>
                        <div class="tpl9-calc-res-val" id="lc-interest"><?php echo $loan_calc_money($loan_calc_interest_total); ?></div>
                    </div>
                    <div>
                        <div class="tpl9-calc-res-label">Upfront Fees</div>
                        <div class="tpl9-calc-res-val" id="lc-fee"><?php echo $loan_calc_money($loan_calc_fee_total); ?></div>
                    </div>
                    <div>
                        <div class="tpl9-calc-res-label">Total Repayment</div>
                        <div class="tpl9-calc-res-val" id="lc-total"><?php echo $loan_calc_money($loan_calc_total); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php
    $show_stats_val = $show_stats ?? true;
    if (is_string($show_stats_val)) $show_stats_val = filter_var($show_stats_val, FILTER_VALIDATE_BOOLEAN);
    ?>
    <section id="sec_stats" class="tpl9-stats editable-section" style="<?php echo getBgStyle('sec_stats', $sec_styles, 'var(--brand, #8B7355)'); ?> <?php if (!$show_stats_val) echo 'display:none;'; ?>">
        <div class="container" contenteditable="false">
            <h2 class="tpl9-stats-title">Our Impact</h2>
            <p class="tpl9-stats-subtitle">Numbers that show our commitment to your growth.</p>
            <div class="tpl9-stats-grid">
                <div class="tpl9-stat-item">
                    <div class="tpl9-stat-value">1,500+</div>
                    <div class="tpl9-stat-label">Active Members</div>
                </div>
                <div class="tpl9-stat-item">
                    <div class="tpl9-stat-value">3,200+</div>
                    <div class="tpl9-stat-label">Loans Funded</div>
                </div>
                <div class="tpl9-stat-item">
                    <div class="tpl9-stat-value">98%</div>
                    <div class="tpl9-stat-label">Satisfaction Rate</div>
                </div>
                <div class="tpl9-stat-item">
                    <div class="tpl9-stat-value">24 Hours</div>
                    <div class="tpl9-stat-label">Average Approval</div>
                </div>
            </div>
        </div>
    </section>

    <?php
    $show_about_val = $show_about ?? true;
    if (is_string($show_about_val)) $show_about_val = filter_var($show_about_val, FILTER_VALIDATE_BOOLEAN);
    ?>
    <section id="sec_about" class="tpl9-about editable-section" style="<?php echo getBgStyle('sec_about', $sec_styles, '#ffffff'); ?> <?php if (!$show_about_val) echo 'display:none;'; ?>">
        <div class="container">
            <h2 class="tpl9-about-title">Who We Are</h2>
            <div class="tpl9-about-body" data-edit="about_body" contenteditable="true">
                <?php echo $e($about_body); ?>
            </div>
        </div>
    </section>

    <?php
    $show_download_val = $show_download ?? true;
    if (is_string($show_download_val)) $show_download_val = filter_var($show_download_val, FILTER_VALIDATE_BOOLEAN);
    ?>
    <section id="sec_download" class="tpl9-download editable-section" style="<?php echo getBgStyle('sec_download', $sec_styles, 'var(--brand, #8B7355)'); ?> <?php if (!$show_download_val) echo 'display:none;'; ?>">
        <div class="container">
            <div class="tpl9-download-content">
                <h2 class="tpl9-download-title">Get The App</h2>
                <p class="tpl9-download-desc" data-edit="download_description" contenteditable="true"><?php echo $e($download_description); ?></p>
                <?php if (!($is_demo_preview ?? false)): ?>
                <a href="<?php echo $e($download_href); ?>" class="tpl9-download-btn" contenteditable="false">Download Now</a>
                <?php endif; ?>
                <div class="shared-app-steps">
                    <div class="shared-app-step">
                        <div class="shared-app-step-number">1</div>
                        <div class="shared-app-step-label">Step 1</div>
                        <div class="shared-app-step-title">Install the MicroFin app</div>
                        <p class="shared-app-step-copy">The download button installs the company-branded MicroFin mobile app.</p>
                    </div>
                    <div class="shared-app-step">
                        <div class="shared-app-step-number">2</div>
                        <div class="shared-app-step-label">Step 2</div>
                        <div class="shared-app-step-title">Open Create Account and use the QR button</div>
                        <p class="shared-app-step-copy">That button reveals this institution QR so the app can unlock the form with the correct <strong>@<?php echo $e($tenant_slug ?? $site_slug ?? 'tenant'); ?></strong> suffix.</p>
                    </div>
                    <div class="shared-app-step">
                        <div class="shared-app-step-number">3</div>
                        <div class="shared-app-step-label">Step 3</div>
                        <div class="shared-app-step-title">Use the referral code if scanning is unavailable</div>
                        <p class="shared-app-step-copy">Manual fallback code: <strong><?php echo $e($tenant_referral_code_value !== '' ? $tenant_referral_code_value : ($tenant_slug ?? $site_slug ?? '')); ?></strong></p>
                    </div>
                </div>
                <details class="shared-app-qr-toggle" contenteditable="false">
                    <summary class="shared-app-qr-toggle-btn">Show Registration QR</summary>
                    <div class="shared-app-qr-panel">
                        <div class="shared-app-qr-card">
                            <?php if ($tenant_qr_url !== ''): ?>
                                <img src="<?php echo $e($tenant_qr_url); ?>" alt="Tenant registration QR code">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center text-center" style="width:140px;height:140px;background:#fff;padding:15px;">
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
    </section>

    <footer class="tpl9-footer">
        <div class="container">
            <div class="tpl9-footer-grid">
                <div>
                    <h5 class="tpl9-footer-brand-name display-company-name"><?php echo $e($company_name); ?></h5>
                    <p class="tpl9-footer-desc" data-edit="footer_desc" contenteditable="true"><?php echo $e($footer_desc); ?></p>
                </div>
                <div>
                    <h6 class="tpl9-footer-contact-title">Contact</h6>
                    <div class="tpl9-footer-contact-item" data-edit="contact_address" contenteditable="true">
                        <i class="ti ti-map-pin"></i>
                        <?php echo $e($contact_address); ?>
                    </div>
                    <div class="tpl9-footer-contact-item" data-edit="contact_phone" contenteditable="true">
                        <i class="ti ti-phone"></i>
                        <?php echo $e($contact_phone); ?>
                    </div>
                    <div class="tpl9-footer-contact-item" data-edit="contact_email" contenteditable="true">
                        <i class="ti ti-mail"></i>
                        <?php echo $e($contact_email); ?>
                    </div>
                    <div class="tpl9-footer-contact-item" data-edit="contact_hours" contenteditable="true">
                        <i class="ti ti-clock"></i>
                        <?php echo $e($contact_hours); ?>
                    </div>
                </div>
                <div>
                    <h6 class="tpl9-footer-contact-title">Quick Links</h6>
                    <div class="tpl9-footer-contact-item">
                        <a href="#sec_services" style="color: var(--text-body); text-decoration: none;">Services</a>
                    </div>
                    <div class="tpl9-footer-contact-item">
                        <a href="#sec_download" style="color: var(--text-body); text-decoration: none;">Download App</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</div>
