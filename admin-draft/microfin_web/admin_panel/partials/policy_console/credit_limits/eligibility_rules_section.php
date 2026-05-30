<?php
$pccl_eligibility = isset($policy_console_eligibility_rules) && is_array($policy_console_eligibility_rules)
    ? $policy_console_eligibility_rules
    : [];

$pccl_age = $pccl_eligibility['age_restrictions'] ?? [];
$pccl_emp = $pccl_eligibility['employment_status'] ?? [];
$pccl_income = $pccl_eligibility['minimum_income'] ?? [];
$pccl_guarantor = $pccl_eligibility['guarantor_required'] ?? [];
$pccl_collateral = $pccl_eligibility['collateral_required'] ?? [];

$pccl_render_toggle = static function (string $label, string $helpText, string $name, $value): string {
    $isOn = !empty($value) && !in_array($value, ['0', 0, false, 'false'], true);
    $isOnClass = $isOn ? 'is-on' : '';
    $ariaPressed = $isOn ? 'true' : 'false';
    $labelState = $isOn ? 'On' : 'Off';
    $help = '<span class="policy-help" tabindex="0" role="button" aria-label="More info" data-help="' . htmlspecialchars($helpText, ENT_QUOTES, 'UTF-8') . '">!</span>';
    return '
        <div class="policy-decision-rule-header">
            <div class="policy-decision-rule-label">
                <strong>' . htmlspecialchars($label) . '</strong>
                ' . $help . '
            </div>
            <div class="policy-inline-toggle-row__control" style="transform: scale(0.85); margin: 0;">
                <input type="hidden" name="' . $name . '" value="' . ($isOn ? '1' : '0') . '" data-policy-toggle-input="' . $name . '">
                <button type="button" class="policy-toggle-button ' . $isOnClass . '" data-policy-toggle-button="' . $name . '" aria-pressed="' . $ariaPressed . '" aria-label="' . htmlspecialchars($label) . '">
                    <span class="policy-toggle-button__track"><span class="policy-toggle-button__thumb"></span></span>
                    <span class="policy-toggle-button__label" data-policy-toggle-label>' . $labelState . '</span>
                </button>
            </div>
        </div>
    ';
};

$pccl_employment_options = [
    'full_time' => 'Full Time',
    'part_time' => 'Part Time',
    'contract' => 'Contract',
    'freelancer' => 'Freelancer / Gig',
    'self_employed' => 'Self Employed',
    'casual' => 'Casual / Seasonal',
    'retired' => 'Retired / Pensioner',
    'student' => 'Student',
    'unemployed' => 'Unemployed',
];
$pccl_selected_statuses = is_array($pccl_emp['eligible_statuses'] ?? null) ? $pccl_emp['eligible_statuses'] : [];
?>
<style>
.pccl-rule-list { display: flex; flex-direction: column; }
.pccl-rule-item { padding: 16px 20px; border-bottom: 1px solid var(--border-color); background-color: var(--bg-card); }
.pccl-rule-item:last-child { border-bottom: none; }
.pccl-input-group { display: flex; flex-wrap: wrap; gap: 20px; padding-top: 4px; padding-bottom: 4px; transition: opacity 0.2s ease; }
.pccl-input-group.is-visually-disabled { opacity: 0.35; pointer-events: none; filter: grayscale(1); }
.pccl-field { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; }
.pccl-field-label { font-size: 14px; font-weight: 400; color: var(--text-main); white-space: nowrap; }
.pccl-field .form-control { background-color: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 6px; padding: 6px 10px; font-size: 14px; min-width: 100px; max-width: 180px; }
.pccl-pill-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.pccl-pill-label { cursor: pointer; display: inline-block; }
.pccl-pill-label input[type="checkbox"] { display: none; }
.pccl-pill-button { display: inline-block; padding: 8px 16px; border-radius: 20px; background-color: var(--bg-body); color: var(--text-muted); font-size: 13px; font-weight: 500; border: 1px solid var(--border-color); transition: all 0.2s ease; user-select: none; opacity: 0.8; }
.pccl-pill-label input[type="checkbox"]:checked + .pccl-pill-button { background-color: var(--primary-color); color: #ffffff; border-color: var(--primary-color); opacity: 1; }
</style>

<div class="policy-blueprint-panel" id="policy-eligibility-rules-panel">
    <div class="policy-blueprint-panel-head">
        <strong>Eligibility Rules</strong>
        <p class="text-muted" style="font-size: 13px; margin: 4px 0 0;">Define minimum eligibility criteria evaluated during client verification and loan application.</p>
    </div>
    <div class="policy-blueprint-panel-body" style="padding: 0;">
        <div class="pccl-rule-list">

            <!-- Age Restrictions -->
            <div class="pccl-rule-item">
                <?php echo $pccl_render_toggle('Age Restrictions', 'Controls demographic age eligibility.', 'pccl_age_enabled', $pccl_age['enabled'] ?? false); ?>
                <div class="pccl-input-group toggle-group-pccl_age_enabled">
                    <div class="pccl-field">
                        <span class="pccl-field-label">Minimum Age</span>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="number" class="form-control" name="pccl_min_age" value="<?php echo htmlspecialchars((string)($pccl_age['min_age'] ?? '')); ?>" placeholder="18" min="0" max="120">
                            <span class="text-muted" style="font-size: 13px;">years</span>
                        </div>
                    </div>
                    <div class="pccl-field">
                        <span class="pccl-field-label">Maximum Age</span>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="number" class="form-control" name="pccl_max_age" value="<?php echo htmlspecialchars((string)($pccl_age['max_age'] ?? '')); ?>" placeholder="65" min="0" max="120">
                            <span class="text-muted" style="font-size: 13px;">years</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employment Status -->
            <div class="pccl-rule-item">
                <?php echo $pccl_render_toggle('Employment Status', 'Which employment statuses are allowed.', 'pccl_employment_status_enabled', $pccl_emp['enabled'] ?? false); ?>
                <div class="pccl-input-group toggle-group-pccl_employment_status_enabled">
                    <div class="pccl-field" style="width: 100%;">
                        <span class="pccl-field-label">Eligible Statuses</span>
                        <div class="pccl-pill-list">
                            <?php foreach ($pccl_employment_options as $val => $text): ?>
                                <label class="pccl-pill-label">
                                    <input type="checkbox" name="pccl_eligible_statuses[]" value="<?php echo htmlspecialchars($val); ?>" <?php echo in_array($val, $pccl_selected_statuses, true) ? 'checked' : ''; ?>>
                                    <span class="pccl-pill-button"><?php echo htmlspecialchars($text); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Minimum Income -->
            <div class="pccl-rule-item">
                <?php echo $pccl_render_toggle('Minimum Income', 'Minimum gross monthly income requirement.', 'pccl_income_enabled', $pccl_income['enabled'] ?? false); ?>
                <div class="pccl-input-group toggle-group-pccl_income_enabled">
                    <div class="pccl-field">
                        <span class="pccl-field-label">Monthly Gross Income</span>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="text-muted" style="font-size: 14px;">&#8369;</span>
                            <input type="number" step="0.01" class="form-control" name="pccl_min_monthly_income" value="<?php echo htmlspecialchars((string)($pccl_income['min_monthly_income'] ?? '')); ?>" placeholder="0.00" min="0">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Guarantor Required (Hidden from UI - Backend logic preserved) -->
            <!--
            <div class="pccl-rule-item">
                <?php echo $pccl_render_toggle('Guarantor Required', 'Require a guarantor for high-exposure loans.', 'pccl_guarantor_enabled', $pccl_guarantor['enabled'] ?? false); ?>
                <div class="pccl-input-group toggle-group-pccl_guarantor_enabled">
                    <div class="pccl-field">
                        <span class="pccl-field-label">Guarantor required for loans above:</span>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="text-muted" style="font-size: 14px;">&#8369;</span>
                            <input type="number" step="0.01" class="form-control" name="pccl_guarantor_amount" value="<?php echo htmlspecialchars((string)($pccl_guarantor['required_above_amount'] ?? '')); ?>" placeholder="0.00" min="0" <?php echo empty($pccl_guarantor['enabled']) ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                </div>
            </div>
            -->

            <!-- Collateral Required (Hidden from UI - Backend logic preserved) -->
            <!--
            <div class="pccl-rule-item">
                <?php echo $pccl_render_toggle('Collateral Required', 'Require collateral documents for secured high-value loans.', 'pccl_collateral_enabled', $pccl_collateral['enabled'] ?? false); ?>
                <div class="pccl-input-group toggle-group-pccl_collateral_enabled">
                    <div class="pccl-field">
                        <span class="pccl-field-label">Collateral required for loans above:</span>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="text-muted" style="font-size: 14px;">&#8369;</span>
                            <input type="number" step="0.01" class="form-control" name="pccl_collateral_amount" value="<?php echo htmlspecialchars((string)($pccl_collateral['required_above_amount'] ?? '')); ?>" placeholder="0.00" min="0" <?php echo empty($pccl_collateral['enabled']) ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                </div>
            </div>
            -->

        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('policy-console-credit-limits-form');
    if (!form) return;
    var pcclToggles = form.querySelectorAll('[data-policy-toggle-button^="pccl_"]');
    pcclToggles.forEach(function (btn) {
        var name = btn.getAttribute('data-policy-toggle-button');
        var group = form.querySelector('.toggle-group-' + name);
        if (!group) return;
        var sync = function () {
            var isOff = !btn.classList.contains('is-on');
            group.classList.toggle('is-visually-disabled', isOff);
            // also disable inputs in the group so they don't submit when off (optional)
        };
        var observer = new MutationObserver(sync);
        observer.observe(btn, { attributes: true, attributeFilter: ['class'] });
        sync();
    });
})();
</script>
