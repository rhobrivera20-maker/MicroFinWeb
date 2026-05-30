<?php
/**
 * Template 5 — Asymmetric Creative
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
$primary = $primary ?? '#f59e0b';
$secondary = $secondary ?? '#10b981';
$border_color = $border_color ?? '#fde68a';
$border_radius = $border_radius ?? '20';
$shadow = $shadow ?? '0.15';
$font_family = $font_family ?? 'Inter';
$text_heading_color = $text_heading_color ?? '#1c1917';
$text_body_color = $text_body_color ?? '#78716c';
$btn_bg_color = $btn_bg_color ?? $primary;
$btn_text_color = $btn_text_color ?? '#ffffff';
$logo = $logo ?? '';
$company_name = $company_name ?? 'MicroFin';
$short_name = $short_name ?? '';
$display_name = $short_name ?: $company_name;
$hero_title = $hero_title ?? 'Loans That Feel Like You';
$hero_subtitle = $hero_subtitle ?? 'Personalized financing that adapts to your life, not the other way around.';
$hero_badge_text = $hero_badge_text ?? 'Personalized';
$about_body = $about_body ?? 'We believe loans should be as unique as the people who need them.';
$download_description = $download_description ?? 'Your financial life, beautifully organized in one app.';
$contact_address = $contact_address ?? '345 Creative Lane';
$contact_phone = $contact_phone ?? '0900-888-7777';
$contact_email = $contact_email ?? 'hello@microfin.os';
$contact_hours = $contact_hours ?? 'Mon-Sun: 24/7';
$footer_desc = $footer_desc ?? 'Making finance personal again.';
$hero_image = $hero_image ?? '';
$display_image = $hero_image ?: 'https://images.unsplash.com/photo-1559526324-4b87b5e36e44?auto=format&fit=crop&w=600&h=800&q=80';
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
        --brand: <?php echo $primary ?? '#f59e0b'; ?>;
        --brand-rgb: <?php echo isset($primary) ? implode(', ', array_map('hexdec', str_split(ltrim($primary, '#'), 2))) : '245, 158, 11'; ?>;
        --secondary: <?php echo $secondary ?? '#10b981'; ?>;
        --secondary-rgb: <?php echo isset($secondary) ? implode(', ', array_map('hexdec', str_split(ltrim($secondary, '#'), 2))) : '16, 185, 129'; ?>;
        --border-clr: <?php echo $border_color ?? '#fde68a'; ?>;
        --radius: <?php echo $border_radius ?? '20'; ?>px;
        --shadow: 0 8px 32px rgba(0, 0, 0, <?php echo $shadow ?? '0.1'; ?>);
        --text-heading: <?php echo $text_heading_color ?? '#1c1917'; ?>;
        --text-body: <?php echo $text_body_color ?? '#78716c'; ?>;
        --btn-bg: <?php echo $btn_bg_color ?? ($primary ?? '#f59e0b'); ?>;
        --btn-text: <?php echo $btn_text_color ?? '#ffffff'; ?>;
        --bg-cream: #fffbf0;
        --bg-dark: #1c1917;
        --bg-darker: #0c0a09;
    }

    * {
        box-sizing: border-box;
    }

    body {
        font-family: var(--font-family, 'Inter'), -apple-system, BlinkMacSystemFont, sans-serif;
        color: var(--text-heading);
        background: var(--bg-cream);
        margin: 0;
        padding: 0;
    }

    .tpl5-wrapper {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .tpl5-nav {
        background: var(--bg-cream);
        border-bottom: 1px solid var(--border-clr);
        padding: 1rem 0;
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .tpl5-nav-inner {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .tpl5-nav-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .tpl5-nav-brand-text {
        font-weight: 700;
        font-size: 1.25rem;
        color: #92400e;
    }

    .tpl5-nav-links {
        display: flex;
        gap: 2rem;
    }

    .tpl5-nav-link {
        color: var(--text-body);
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s;
    }

    .tpl5-nav-link:hover {
        color: var(--brand);
    }

    .tpl5-btn {
        background: var(--btn-bg);
        color: var(--btn-text);
        padding: 0.75rem 1.75rem;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.2s;
        border: none;
        font-size: 0.875rem;
    }

    .tpl5-btn:hover {
        background: #d97706;
        transform: translateY(-2px);
    }

    .tpl5-hero {
        background: var(--bg-cream);
        padding: 5rem 0;
    }

    .tpl5-hero-content {
        max-width: 680px;
        margin: 0 auto;
        text-align: center;
    }

    .tpl5-badge {
        display: inline-block;
        background: #fef3c7;
        color: #92400e;
        padding: 0.5rem 1.25rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.875rem;
        margin-bottom: 1.5rem;
        border: none;
    }

    .tpl5-title {
        font-size: 3rem;
        font-weight: 800;
        line-height: 1.2;
        margin-bottom: 1rem;
        color: var(--text-heading);
    }

    .tpl5-title-underline {
        text-decoration: underline;
        text-decoration-color: var(--brand);
        text-decoration-thickness: 3px;
        text-underline-offset: 6px;
    }

    .tpl5-subtitle {
        font-size: 1.125rem;
        line-height: 1.6;
        color: var(--text-body);
        margin-bottom: 2rem;
    }

    .tpl5-hero-illustration {
        width: 100%;
        max-width: 400px;
        height: 300px;
        margin: 2rem auto;
        position: relative;
        border-radius: 32px;
        background: #fef3c7;
        overflow: hidden;
    }

    .tpl5-circle {
        position: absolute;
        border-radius: 50%;
        opacity: 0.8;
    }

    .tpl5-circle-1 {
        width: 120px;
        height: 120px;
        background: var(--brand);
        top: 40px;
        left: 60px;
    }

    .tpl5-circle-2 {
        width: 100px;
        height: 100px;
        background: var(--secondary);
        top: 80px;
        left: 140px;
    }

    .tpl5-circle-3 {
        width: 80px;
        height: 80px;
        background: #fcd34d;
        top: 60px;
        left: 200px;
    }

    .tpl5-avatars {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }

    .tpl5-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--brand);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.875rem;
    }

    .tpl5-avatars-label {
        margin-left: 0.75rem;
        color: var(--text-body);
        font-size: 0.875rem;
    }

    .tpl5-services {
        background: #fff;
        padding: 4rem 0;
    }

    .tpl5-section-header {
        text-align: center;
        margin-bottom: 3rem;
    }

    .tpl5-section-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-heading);
        margin-bottom: 0.5rem;
    }

    .tpl5-services-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
    }

    .tpl5-service-card {
        background: #fff;
        border: 2px solid var(--border-clr);
        border-radius: 20px;
        padding: 2rem;
        position: relative;
        transition: all 0.2s;
    }

    .tpl5-service-card:hover {
        border-color: var(--brand);
        background: var(--bg-cream);
    }

    .tpl5-service-card::after {
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

    .tpl5-service-icon {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: #fef3c7;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        color: var(--brand);
    }

    .tpl5-service-title {
        font-size: 1.125rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--text-heading);
    }

    .tpl5-service-desc {
        color: var(--text-body);
        line-height: 1.5;
        font-size: 0.875rem;
    }

    .tpl5-popular-badge {
        position: absolute;
        top: -10px;
        right: 1rem;
        background: #d1fae5;
        color: #065f46;
        font-size: 0.7rem;
        border-radius: 50px;
        padding: 3px 10px;
        font-weight: 700;
    }

    .tpl5-stats {
        background: var(--bg-dark);
        padding: 3rem 0;
    }

    .tpl5-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0;
    }

    .tpl5-stat-item {
        text-align: center;
        padding: 1.5rem;
        border-right: 1px solid rgba(255, 255, 255, 0.1);
    }

    .tpl5-stat-item:last-child {
        border-right: none;
    }

    .tpl5-stat-number {
        font-size: 2rem;
        font-weight: 800;
        color: var(--brand);
        margin-bottom: 0.25rem;
    }

    .tpl5-stat-label {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.875rem;
    }

    .tpl5-download {
        background: var(--bg-cream);
        padding: 4rem 0;
    }

    .tpl5-download-content {
        max-width: 700px;
        margin: 0 auto;
        text-align: center;
    }

    .tpl5-download-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 1rem;
        color: var(--text-heading);
    }

    .tpl5-download-desc {
        color: var(--text-body);
        margin-bottom: 2rem;
        line-height: 1.6;
    }

    .tpl5-download-btn {
        background: var(--brand);
        color: #fff;
        padding: 1rem 2.5rem;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 600;
        display: inline-block;
    }

    .tpl5-download-btn:hover {
        background: #d97706;
    }

    .shared-app-steps {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        margin-top: 3rem;
    }

    .shared-app-step {
        background: #fff;
        border-radius: 16px;
        border: 2px solid var(--border-clr);
        padding: 1.5rem;
        text-align: center;
    }

    .shared-app-step-number {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: var(--brand);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0 auto 1rem;
    }

    .shared-app-step-label {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        opacity: 0.6;
        margin-bottom: 0.5rem;
        color: var(--brand);
    }

    .shared-app-step-title {
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--text-heading);
    }

    .shared-app-step-copy {
        font-size: 0.875rem;
        line-height: 1.5;
        opacity: 0.7;
        margin: 0;
        color: var(--text-body);
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
        border: 2px dashed var(--brand);
        color: var(--brand);
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .shared-app-qr-toggle-btn:hover {
        background: #fef3c7;
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
        background: #fef3c7;
        border: 2px dashed var(--brand);
        border-radius: 12px;
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
        background: #fff;
        border: 1px solid var(--border-clr);
        font-weight: 600;
        border-radius: 8px;
    }

    .tpl5-footer {
        background: var(--bg-dark);
        color: #fff;
        padding: 3rem 0;
    }

    .tpl5-footer-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
    }

    .tpl5-footer-brand-name {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
        color: var(--brand);
    }

    .tpl5-footer-desc {
        color: rgba(255, 255, 255, 0.5);
        line-height: 1.5;
        font-size: 0.875rem;
    }

    .tpl5-footer-contact-title {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 1rem;
        color: var(--secondary);
    }

    .tpl5-footer-contact-item {
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .tpl5-footer-contact-item i {
        color: var(--secondary);
    }

    .tpl5-footer-bottom {
        border-top: 3px solid var(--brand);
        padding-top: 1.5rem;
        margin-top: 2rem;
    }

    /* Unique Community Features */
    .tpl5-features {
        background: var(--bg-white, #ffffff);
        padding: 5rem 0;
        border-top: 1px solid var(--border-clr);
    }

    .tpl5-features-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 2rem;
        max-width: 1100px;
        margin: 0 auto;
        padding: 0 1.5rem;
    }

    .tpl5-feature-card {
        background: var(--bg-cream);
        border: 2px solid var(--border-clr);
        border-radius: var(--radius);
        padding: 2.5rem;
        transition: all 0.3s ease;
        display: flex;
        gap: 1.5rem;
    }

    .tpl5-feature-card:hover {
        transform: translateY(-5px);
        border-color: var(--secondary);
        box-shadow: var(--shadow);
    }

    .tpl5-feature-card-icon {
        width: 60px;
        height: 60px;
        border-radius: 18px;
        background: rgba(var(--secondary-rgb), 0.1);
        color: var(--secondary);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 28px;
    }

    .tpl5-feature-card-title {
        font-size: 1.25rem;
        font-weight: 750;
        margin-bottom: 0.5rem;
        color: var(--text-heading);
    }

    .tpl5-feature-card-desc {
        font-size: 0.9rem;
        color: var(--text-body);
        line-height: 1.6;
    }

    /* Asymmetric offset for community look */
    .tpl5-features-grid > .tpl5-feature-card:nth-child(even) {
        transform: translateY(1.5rem);
    }

    /* Loan Calculator */
    .tpl5-calc {
        padding: 5rem 0;
        background: var(--bg-cream);
        border-top: 1px solid var(--border-clr);
    }

    .tpl5-calc-card {
        max-width: 840px;
        margin: 0 auto;
        background: var(--bg-white, #ffffff);
        border: 2px solid var(--border-clr);
        border-radius: var(--radius);
        padding: 3rem;
        box-shadow: var(--shadow);
    }

    .tpl5-calc-label {
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--text-heading);
        margin-bottom: 1rem;
        display: block;
    }

    .tpl5-lc-product-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .tpl5-lc-product-btn {
        background: var(--bg-cream);
        border: 2px solid var(--border-clr);
        border-radius: var(--radius);
        padding: 1.25rem;
        text-align: left;
        cursor: pointer;
        transition: all 0.25s ease;
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .tpl5-lc-product-btn:hover {
        background: var(--bg-white, #ffffff);
        border-color: var(--brand);
        transform: scale(1.02);
    }

    .tpl5-lc-product-btn.border-primary {
        background: var(--bg-white, #ffffff) !important;
        border-color: var(--brand) !important;
        border-width: 2px !important;
        box-shadow: var(--shadow);
    }

    .tpl5-lc-product-name {
        font-weight: 800;
        font-size: 1.1rem;
        color: var(--text-heading);
    }

    .tpl5-lc-product-meta {
        font-size: 0.85rem;
        color: var(--text-body);
        line-height: 1.4;
    }

    .tpl5-calc-slider-card {
        background: var(--bg-cream);
        border: 1px solid var(--border-clr);
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .tpl5-calc-slider-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }

    .tpl5-calc-slider-title {
        font-weight: 700;
        font-size: 0.9rem;
        color: var(--text-heading);
    }

    .tpl5-calc-slider-value {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--brand);
    }

    .tpl5-calc-range {
        width: 100%;
        accent-color: var(--brand);
        cursor: pointer;
        height: 8px;
        background: var(--border-clr);
        border-radius: 10px;
        outline: none;
    }

    .tpl5-calc-range-limits {
        display: flex;
        justify-content: space-between;
        font-size: 0.75rem;
        color: var(--text-body);
        margin-top: 0.5rem;
        font-weight: 500;
    }

    .tpl5-calc-results {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        text-align: center;
        margin-top: 2.5rem;
        padding-top: 2rem;
        border-top: 2px solid var(--border-clr);
    }

    .tpl5-calc-res-label {
        font-size: 0.75rem;
        color: var(--text-body);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
        margin-bottom: 0.25rem;
    }

    .tpl5-calc-res-val {
        font-size: 1.4rem;
        font-weight: 800;
        color: var(--secondary);
    }

    /* Testimonials Section */
    .tpl5-testimonials {
        background: var(--bg-white, #ffffff);
        padding: 5rem 0;
        border-top: 1px solid var(--border-clr);
    }

    .tpl5-testimonials-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 1.5rem;
    }

    .tpl5-testimonial-card {
        background: var(--bg-cream);
        border: 2px solid var(--border-clr);
        border-radius: var(--radius);
        padding: 2.5rem;
        transition: all 0.3s ease;
        position: relative;
    }

    .tpl5-testimonial-card:hover {
        transform: translateY(-5px);
        border-color: var(--brand);
        box-shadow: var(--shadow);
    }

    .tpl5-testimonial-quote {
        font-size: 0.95rem;
        color: var(--text-heading);
        line-height: 1.7;
        margin-bottom: 2rem;
        font-style: italic;
        font-weight: 500;
    }

    .tpl5-testimonial-user {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .tpl5-testimonial-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: var(--secondary);
        color: var(--btn-text, #ffffff);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1rem;
    }

    .tpl5-testimonial-info {
        display: flex;
        flex-direction: column;
    }

    .tpl5-testimonial-name {
        font-weight: 800;
        font-size: 0.95rem;
        color: var(--text-heading);
    }

    .tpl5-testimonial-role {
        font-size: 0.8rem;
        color: var(--text-body);
        font-weight: 500;
    }

    @media (max-width: 768px) {
        .tpl5-features-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        .tpl5-features-grid > .tpl5-feature-card:nth-child(even) {
            transform: translateY(0);
        }

        .tpl5-lc-product-grid {
            grid-template-columns: 1fr;
        }
        .tpl5-calc-results {
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .tpl5-testimonials-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        .tpl5-hero-illustration {
            display: none;
        }

        .tpl5-title {
            font-size: 2.2rem;
        }

        .tpl5-nav-links {
            display: none;
        }

        .tpl5-services-grid {
            grid-template-columns: 1fr;
        }

        .tpl5-stats-grid {
            grid-template-columns: 1fr;
        }

        .tpl5-stat-item {
            border-right: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .tpl5-stat-item:last-child {
            border-bottom: none;
        }

        .shared-app-steps {
            grid-template-columns: 1fr;
        }

        .tpl5-footer-grid {
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
<div class="tpl5-wrapper">
    <?php if (!($is_demo_preview ?? false)): ?>
    <nav class="tpl5-nav">
        <div class="container tpl5-nav-inner">
            <div class="tpl5-nav-brand">
                <img id="preview_logo" src="<?php echo $e($logo ?? ''); ?>" style="height: 40px; <?php if (!($logo ?? '')) echo 'display:none;'; ?>">
                <span class="tpl5-nav-brand-text display-short-name" contenteditable="false"><?php echo $e($display_name); ?></span>
            </div>
            <div class="tpl5-nav-links">
                <a href="#sec_services" class="tpl5-nav-link">Services</a>
                <a href="#sec_download" class="tpl5-nav-link">About</a>
                <a href="#sec_download" class="tpl5-nav-link">Contact</a>
            </div>
            <a href="<?php echo $login_href; ?>" class="tpl5-btn" contenteditable="false">Login</a>
        </div>
    </nav>
    <?php endif; ?>

    <section id="sec_hero" class="tpl5-hero editable-section" style="<?php echo getBgStyle('sec_hero', $sec_styles, '#fffbf0'); ?>">
        <div class="container">
            <div class="tpl5-hero-content">
                <span class="tpl5-badge" data-edit="hero_badge_text" contenteditable="true"><?php echo $e($hero_badge_text); ?></span>
                <h1 class="tpl5-title" data-edit="hero_title" contenteditable="true"><?php echo $e($hero_title); ?></h1>
                <p class="tpl5-subtitle" data-edit="hero_subtitle" contenteditable="true"><?php echo $e($hero_subtitle); ?></p>
                <div class="tpl5-hero-illustration">
                    <div class="tpl5-circle tpl5-circle-1"></div>
                    <div class="tpl5-circle tpl5-circle-2"></div>
                    <div class="tpl5-circle tpl5-circle-3"></div>
                </div>
                <div class="tpl5-avatars">
                    <div class="tpl5-avatar">JD</div>
                    <div class="tpl5-avatar">MA</div>
                    <div class="tpl5-avatar">RK</div>
                    <div class="tpl5-avatar">LP</div>
                    <span class="tpl5-avatars-label">+2,400 borrowers trust us</span>
                </div>
            </div>
        </div>
    </section>

    <?php
    $show_services_val = $show_services ?? true;
    if (is_string($show_services_val)) $show_services_val = filter_var($show_services_val, FILTER_VALIDATE_BOOLEAN);
    ?>
    <section id="sec_services" class="tpl5-services editable-section" style="<?php echo getBgStyle('sec_services', $sec_styles, '#fff'); ?> <?php if (!$show_services_val) echo 'display:none;'; ?>">
        <div class="container">
            <div class="tpl5-section-header">
                <h2 class="tpl5-section-title">Our Services</h2>
            </div>
            <div class="tpl5-services-grid">
                <?php foreach (($services ?? []) as $index => $svc): ?>
                    <div class="tpl5-service-card service-col position-relative" data-index="<?php echo $index + 1; ?>">
                        <button class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 delete-card" contenteditable="false">×</button>
                        <?php if ($index === 1): ?>
                        <span class="tpl5-popular-badge">Most Popular</span>
                        <?php endif; ?>
                        <div class="tpl5-service-icon editable-icon-wrap">
                            <i class="ti ti-<?php echo $e($svc['icon'] ?? 'star'); ?> service-icon-text" style="font-size: 24px;"></i>
                        </div>
                        <h3 class="tpl5-service-title service-title" contenteditable="true"><?php echo $e($svc['title'] ?? 'Service Title'); ?></h3>
                        <p class="tpl5-service-desc service-desc" contenteditable="true"><?php echo $e($svc['description'] ?? 'Service description.'); ?></p>
                    </div>
                <?php endforeach; ?>
                <div class="tpl5-service-card" id="add_service_col" contenteditable="false" style="border-style: dashed; cursor: pointer; display: flex; align-items: center; justify-content: center; min-height: 160px;" onclick="addServiceCard()">
                    <div class="text-center" style="color: var(--text-body); opacity: 0.4;">
                        <i class="ti ti-plus" style="font-size: 2rem;"></i>
                        <div class="fw-bold mt-2" style="font-size: 0.875rem;">Add Service</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php
    $show_stats_val = $show_stats ?? true;
    if (is_string($show_stats_val)) $show_stats_val = filter_var($show_stats_val, FILTER_VALIDATE_BOOLEAN);
    ?>
    </section>

    <?php
    $show_features_val = $show_features ?? true;
    if (is_string($show_features_val)) $show_features_val = filter_var($show_features_val, FILTER_VALIDATE_BOOLEAN);
    ?>
    <section id="sec_features" class="tpl5-features editable-section" style="<?php echo getBgStyle('sec_features', $sec_styles, '#ffffff'); ?> <?php if (!$show_features_val) echo 'display:none;'; ?>">
        <div class="container">
            <div class="tpl5-section-header">
                <h2 class="tpl5-section-title">Why Our Community Trusts Us</h2>
            </div>
            <div class="tpl5-features-grid">
                <div class="tpl5-feature-card">
                    <div class="tpl5-feature-card-icon">
                        <i class="ti ti-heart"></i>
                    </div>
                    <div>
                        <h3 class="tpl5-feature-card-title">People-First Terms</h3>
                        <p class="tpl5-feature-card-desc">No hidden charges or aggressive collection practices. We support our community members through flexible repayment plans.</p>
                    </div>
                </div>
                <div class="tpl5-feature-card">
                    <div class="tpl5-feature-card-icon">
                        <i class="ti ti-trending-up"></i>
                    </div>
                    <div>
                        <h3 class="tpl5-feature-card-title">Transparent Interest</h3>
                        <p class="tpl5-feature-card-desc">Clear flat or declining monthly rates starting low, helping you invest in your future without getting trapped under heavy debt.</p>
                    </div>
                </div>
                <div class="tpl5-feature-card">
                    <div class="tpl5-feature-card-icon">
                        <i class="ti ti-clock"></i>
                    </div>
                    <div>
                        <h3 class="tpl5-feature-card-title">Swift Approval</h3>
                        <p class="tpl5-feature-card-desc">Apply online or via the mobile app in minutes. Our community credit checks are fast, empathetic, and respectful.</p>
                    </div>
                </div>
                <div class="tpl5-feature-card">
                    <div class="tpl5-feature-card-icon">
                        <i class="ti ti-shield"></i>
                    </div>
                    <div>
                        <h3 class="tpl5-feature-card-title">Secure & Trusted</h3>
                        <p class="tpl5-feature-card-desc">Fully registered, licensed microfinance partner with state-of-the-art data encryption protecting your personal records.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php
    $show_calc_val = $show_loan_calc ?? true;
    if (is_string($show_calc_val)) $show_calc_val = filter_var($show_calc_val, FILTER_VALIDATE_BOOLEAN);
    ?>
    <section id="sec_calc" class="tpl5-calc editable-section" style="<?php echo getBgStyle('sec_calc', $sec_styles, '#fffbf0'); ?> <?php if (!$show_calc_val) echo 'display:none;'; ?>">
        <div class="container" contenteditable="false">
            <div class="tpl5-section-header">
                <h2 class="tpl5-section-title">Calculate Your Microfinance Loan</h2>
            </div>
            <div class="tpl5-calc-card">
                <div class="mb-4">
                    <label class="tpl5-calc-label">Choose a Loan Product</label>
                    <div class="tpl5-lc-product-grid">
                        <?php foreach (($loan_products ?? []) as $i => $prod): ?>
                            <button type="button" class="tpl5-lc-product-btn lc-product-btn <?php echo $i === 0 ? 'border-primary' : ''; ?>">
                                <div class="tpl5-lc-product-name"><?php echo $e($prod['product_name'] ?? 'Demo Loan'); ?></div>
                                <div class="tpl5-lc-product-meta">
                                    <strong><?php echo $e($prod['interest_rate'] ?? '2.5'); ?>% / mo · <?php echo $e($prod['interest_type'] ?? 'Flat'); ?></strong>
                                    <div>Range: <?php echo $loan_calc_money((float)($prod['min_amount'] ?? 1000)); ?> to <?php echo $loan_calc_money((float)($prod['max_amount'] ?? 50000)); ?></div>
                                    <div>Term: <?php echo (int)($prod['min_term_months'] ?? 1); ?>-<?php echo (int)($prod['max_term_months'] ?? 12); ?> months</div>
                                </div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="tpl5-calc-slider-card">
                    <div class="tpl5-calc-slider-header">
                        <span class="tpl5-calc-slider-title">Loan Amount</span>
                        <span class="tpl5-calc-slider-value" id="lc-amount-display"><?php echo $loan_calc_money($loan_calc_amount_value); ?></span>
                    </div>
                    <input type="range" class="tpl5-calc-range" id="lc-amount-slider" min="<?php echo (int)$loan_calc_min_amount; ?>" max="<?php echo (int)$loan_calc_max_amount; ?>" step="<?php echo (int)$loan_calc_amount_step; ?>" value="<?php echo (int)$loan_calc_amount_value; ?>">
                    <div class="tpl5-calc-range-limits">
                        <span id="lc-min-amount"><?php echo $loan_calc_money($loan_calc_min_amount); ?></span>
                        <span id="lc-max-amount"><?php echo $loan_calc_money($loan_calc_max_amount); ?></span>
                    </div>
                </div>
                <div class="tpl5-calc-slider-card">
                    <div class="tpl5-calc-slider-header">
                        <span class="tpl5-calc-slider-title">Loan Term</span>
                        <span class="tpl5-calc-slider-value" id="lc-term-display"><?php echo (int)$loan_calc_term_value; ?> mo</span>
                    </div>
                    <input type="range" class="tpl5-calc-range" id="lc-term-slider" min="<?php echo (int)$loan_calc_min_term; ?>" max="<?php echo (int)$loan_calc_max_term; ?>" value="<?php echo (int)$loan_calc_term_value; ?>">
                    <div class="tpl5-calc-range-limits">
                        <span id="lc-min-term"><?php echo (int)$loan_calc_min_term; ?> mo</span>
                        <span id="lc-max-term"><?php echo (int)$loan_calc_max_term; ?> mo</span>
                    </div>
                </div>
                <div class="tpl5-calc-results">
                    <div>
                        <div class="tpl5-calc-res-label">Monthly Repayment</div>
                        <div class="tpl5-calc-res-val" id="lc-monthly"><?php echo $loan_calc_money($loan_calc_monthly); ?></div>
                    </div>
                    <div>
                        <div class="tpl5-calc-res-label">Total Interest</div>
                        <div class="tpl5-calc-res-val" id="lc-interest"><?php echo $loan_calc_money($loan_calc_interest_total); ?></div>
                    </div>
                    <div>
                        <div class="tpl5-calc-res-label">Upfront Fees</div>
                        <div class="tpl5-calc-res-val" id="lc-fee"><?php echo $loan_calc_money($loan_calc_fee_total); ?></div>
                    </div>
                    <div>
                        <div class="tpl5-calc-res-label">Total Repayment</div>
                        <div class="tpl5-calc-res-val" id="lc-total"><?php echo $loan_calc_money($loan_calc_total); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php
    $show_stats_val = $show_stats ?? true;
    if (is_string($show_stats_val)) $show_stats_val = filter_var($show_stats_val, FILTER_VALIDATE_BOOLEAN);
    ?>
    <section id="sec_stats" class="tpl5-stats editable-section" style="<?php echo getBgStyle('sec_stats', $sec_styles, '#1c1917'); ?> <?php if (!$show_stats_val) echo 'display:none;'; ?>">
        <div class="container" contenteditable="false">
            <div class="tpl5-stats-grid">
                <div class="tpl5-stat-item">
                    <div class="tpl5-stat-number">5,000+</div>
                    <div class="tpl5-stat-label">Active Members</div>
                </div>
                <div class="tpl5-stat-item">
                    <div class="tpl5-stat-number">98%</div>
                    <div class="tpl5-stat-label">Approval Rate</div>
                </div>
                <div class="tpl5-stat-item">
                    <div class="tpl5-stat-number">24 Hours</div>
                    <div class="tpl5-stat-label">Fast releasing</div>
                </div>
                <div class="tpl5-stat-item">
                    <div class="tpl5-stat-number">4.9★</div>
                    <div class="tpl5-stat-label">Member Rating</div>
                </div>
            </div>
        </div>
    </section>

    <?php
    $show_testimonials_val = $show_testimonials ?? true;
    if (is_string($show_testimonials_val)) $show_testimonials_val = filter_var($show_testimonials_val, FILTER_VALIDATE_BOOLEAN);
    ?>
    <section id="sec_testimonials" class="tpl5-testimonials editable-section" style="<?php echo getBgStyle('sec_testimonials', $sec_styles, '#ffffff'); ?> <?php if (!$show_testimonials_val) echo 'display:none;'; ?>">
        <div class="container">
            <div class="tpl5-section-header">
                <h2 class="tpl5-section-title">Community Success Stories</h2>
            </div>
            <div class="tpl5-testimonials-grid">
                <div class="tpl5-testimonial-card">
                    <p class="tpl5-testimonial-quote">"Thanks to their quick capital releases, I expanded my neighborhood store to a mini-grocery. Honest terms and extremely respectful customer service."</p>
                    <div class="tpl5-testimonial-user">
                        <div class="tpl5-testimonial-avatar">MT</div>
                        <div class="tpl5-testimonial-info">
                            <span class="tpl5-testimonial-name">Maria Teresa</span>
                            <span class="tpl5-testimonial-role">Sari-Sari Store Owner</span>
                        </div>
                    </div>
                </div>
                <div class="tpl5-testimonial-card">
                    <p class="tpl5-testimonial-quote">"Securing a loan here was an absolute breeze. No outrageous conditions or pressure, they treat you like family and work with you through tough times."</p>
                    <div class="tpl5-testimonial-user">
                        <div class="tpl5-testimonial-avatar">RS</div>
                        <div class="tpl5-testimonial-info">
                            <span class="tpl5-testimonial-name">Ramon Santos</span>
                            <span class="tpl5-testimonial-role">Agricultural Farmer</span>
                        </div>
                    </div>
                </div>
                <div class="tpl5-testimonial-card">
                    <p class="tpl5-testimonial-quote">"The mobile app makes checking payments and applying for renewal personal loans extremely convenient. Excellent community lending!"</p>
                    <div class="tpl5-testimonial-user">
                        <div class="tpl5-testimonial-avatar">AL</div>
                        <div class="tpl5-testimonial-info">
                            <span class="tpl5-testimonial-name">Anna Lim</span>
                            <span class="tpl5-testimonial-role">Freelancer / Tutor</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php
    $show_download_val = $show_download ?? true;
    if (is_string($show_download_val)) $show_download_val = filter_var($show_download_val, FILTER_VALIDATE_BOOLEAN);
    ?>
    <section id="sec_download" class="tpl5-download editable-section" style="<?php echo getBgStyle('sec_download', $sec_styles, '#fffbf0'); ?> <?php if (!$show_download_val) echo 'display:none;'; ?>">
        <div class="container">
            <div class="tpl5-download-content">
                <h2 class="tpl5-download-title">Get The App</h2>
                <p class="tpl5-download-desc" data-edit="download_description" contenteditable="true"><?php echo $e($download_description); ?></p>
                <?php if (!($is_demo_preview ?? false)): ?>
                <a href="<?php echo $e($download_href); ?>" class="tpl5-download-btn" contenteditable="false">Download Now</a>
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

    <footer class="tpl5-footer">
        <div class="container">
            <div class="tpl5-footer-grid">
                <div>
                    <h5 class="tpl5-footer-brand-name display-company-name"><?php echo $e($company_name); ?></h5>
                    <p class="tpl5-footer-desc" data-edit="footer_desc" contenteditable="true"><?php echo $e($footer_desc); ?></p>
                </div>
                <div>
                    <h6 class="tpl5-footer-contact-title">Contact</h6>
                    <div class="tpl5-footer-contact-item" data-edit="contact_address" contenteditable="true">
                        <i class="ti ti-map-pin"></i>
                        <?php echo $e($contact_address); ?>
                    </div>
                    <div class="tpl5-footer-contact-item" data-edit="contact_phone" contenteditable="true">
                        <i class="ti ti-phone"></i>
                        <?php echo $e($contact_phone); ?>
                    </div>
                    <div class="tpl5-footer-contact-item" data-edit="contact_email" contenteditable="true">
                        <i class="ti ti-mail"></i>
                        <?php echo $e($contact_email); ?>
                    </div>
                    <div class="tpl5-footer-contact-item" data-edit="contact_hours" contenteditable="true">
                        <i class="ti ti-clock"></i>
                        <?php echo $e($contact_hours); ?>
                    </div>
                </div>
                <div>
                    <h6 class="tpl5-footer-contact-title">Quick Links</h6>
                    <div class="tpl5-footer-contact-item">
                        <a href="#sec_services" style="color: rgba(255,255,255,0.7); text-decoration: none;">Services</a>
                    </div>
                    <div class="tpl5-footer-contact-item">
                        <a href="#sec_download" style="color: rgba(255,255,255,0.7); text-decoration: none;">Download App</a>
                    </div>
                </div>
            </div>
            <div class="tpl5-footer-bottom">
                <div class="container text-center" style="color: rgba(255,255,255,0.4); font-size: 0.875rem;">
                    © <?php echo date('Y'); ?> <?php echo $e($company_name); ?>. All rights reserved.
                </div>
            </div>
        </div>
    </footer>
</div>
