<?php
/**
 * @var callable $policy_console_help
 */
$policy_console_limit_assignment = isset($policy_console_limit_assignment) && is_array($policy_console_limit_assignment)
    ? $policy_console_limit_assignment
    : policy_console_limit_assignment_defaults();

$system_defaults = policy_console_credit_limits_system_defaults();
$default_limit_assignment = $system_defaults['limit_assignment'] ?? [];
$is_limit_assignment_default = ($policy_console_limit_assignment == $default_limit_assignment);
?>

<div style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <div>
            <h5 style="margin: 0; font-size: 14px; font-weight: 600; color: var(--text-color);">Credit Limit Setup</h5>
            <p class="text-muted" style="margin: 4px 0 0 0; font-size: 12px;">Define initial credit limits for new borrowers</p>
        </div>
        <div>
            <?php if ($is_limit_assignment_default): ?>
                <span style="font-size: 11px; padding: 3px 8px; border-radius: 10px; background: var(--bg-surface-secondary); color: var(--text-muted); border: 1px solid var(--border-color);">
                    Default
                </span>
            <?php else: ?>
                <span style="font-size: 11px; padding: 3px 8px; border-radius: 10px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
                    Custom
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="policy-blueprint-grid policy-blueprint-grid--two">
        <label class="policy-field">
            <span class="policy-field-label" style="font-size: 13px;">Limit Type</span>
            <div style="display: flex; gap: 16px; margin-top: 8px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                    <input type="radio" name="pcc_limit_initial_type" value="percentage" <?php echo ($policy_console_limit_assignment['initial_limit_type'] ?? 'percentage') === 'percentage' ? 'checked' : ''; ?> onchange="toggleInitialLimitType()">
                    <span>Percentage of Income</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                    <input type="radio" name="pcc_limit_initial_type" value="flat" <?php echo ($policy_console_limit_assignment['initial_limit_type'] ?? 'percentage') === 'flat' ? 'checked' : ''; ?> onchange="toggleInitialLimitType()">
                    <span>Fixed Amount</span>
                </label>
            </div>
        </label>

        <label class="policy-field" id="initial-limit-percentage-field">
            <span class="policy-field-label" style="font-size: 13px;">Percentage <?php echo $policy_console_help('Max loan % of monthly income for first-time borrowers.'); ?></span>
            <div style="display: flex; align-items: center; gap: 8px;">
                <input type="number" class="form-control" id="pcc_limit_initial_percent_of_income" name="pcc_limit_initial_percent_of_income" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars((string)($policy_console_limit_assignment['initial_limit_percent_of_income'] ?? 40)); ?>" style="font-size: 14px;">
                <span style="font-size: 13px; color: var(--text-muted);">%</span>
            </div>

            <div id="limit_dti_mapping_warning" style="display: none; background: rgba(var(--danger-rgb, 220, 38, 38), 0.08); border-left: 3px solid #dc2626; padding: 8px 10px; border-radius: 4px; font-size: 12px; color: var(--text-muted); line-height: 1.3; margin-top: 8px;">
                <strong style="color: #dc2626;">⚠ Warning:</strong> This % exceeds your DTI limit. Borrowers may be rejected.
            </div>
        </label>

        <label class="policy-field" id="initial-limit-flat-field" style="display: none;">
            <span class="policy-field-label" style="font-size: 13px;">Fixed Amount <?php echo $policy_console_help('Fixed limit for first-time borrowers, regardless of income.'); ?></span>
            <input type="number" class="form-control" id="pcc_limit_initial_flat_amount" name="pcc_limit_initial_flat_amount" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($policy_console_limit_assignment['initial_limit_flat_amount'] ?? 5000)); ?>" style="font-size: 14px;">
        </label>
    </div>
</div>

<script>
function toggleInitialLimitType() {
    const type = document.querySelector('input[name="pcc_limit_initial_type"]:checked').value;
    const percentageField = document.getElementById('initial-limit-percentage-field');
    const flatField = document.getElementById('initial-limit-flat-field');

    if (type === 'percentage') {
        percentageField.style.display = 'block';
        flatField.style.display = 'none';
    } else {
        percentageField.style.display = 'none';
        flatField.style.display = 'block';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleInitialLimitType();
});
</script>

<input
    type="hidden"
    name="pcc_limit_use_default_lending_cap"
    value="<?php echo !empty($policy_console_limit_assignment['use_default_lending_cap']) ? '1' : '0'; ?>"
>
<input
    type="hidden"
    name="pcc_limit_default_lending_cap_amount"
    value="<?php echo htmlspecialchars((string)($policy_console_limit_assignment['default_lending_cap_amount'] ?? 0)); ?>"
>
