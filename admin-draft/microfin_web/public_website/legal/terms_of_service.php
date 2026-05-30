<?php
// Terms of Service modal partial — included by demo.php
// To update TOS content, edit this file only.
?>
<div id="tos-modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index:9999; overflow-y:auto; padding:40px 20px;">
    <div style="background:var(--card-bg); border:1px solid var(--card-border); border-radius:18px; max-width:680px; margin:0 auto; padding:40px; color:var(--text-muted); line-height:1.7; box-shadow: var(--card-shadow);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
            <h2 style="margin:0; font-size:1.4rem; color:var(--text-dark); font-weight: 800;">Terms of Service &amp; Refund Policy</h2>
            <button id="close-tos-modal" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.8rem; line-height:1; transition: color 0.2s;">&times;</button>
        </div>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:24px;">Effective Date: <?php echo date('F d, Y'); ?> &mdash; MicroFin Platform</p>

        <h3 style="color:var(--text-dark); font-size:1rem; margin:20px 0 8px;">1. Acceptance of Terms</h3>
        <p style="font-size:0.9rem;">By submitting an application to use the MicroFin platform, you agree to be bound by these Terms of Service. If you do not agree, do not proceed with your application.</p>

        <h3 style="color:var(--text-dark); font-size:1rem; margin:24px 0 8px;">2. Subscription &amp; Payment Rules</h3>
        <p style="font-size:0.9rem;">Upon approval and completion of your billing setup, the following payment rules apply:</p>
        <ul style="font-size:0.9rem; padding-left:20px; margin-top:8px;">
            <li><strong>Initial Activation Charge:</strong> Your first charge is the full monthly subscription fee paid immediately when your account is activated.</li>
            <li><strong>Recurring Billing:</strong> After activation, your subscription renews automatically every 30 days using your saved payment method.</li>
            <li><strong>Automatic Deduction:</strong> Payments are automatically charged to your registered payment method. It is your responsibility to ensure sufficient funds are available.</li>
            <li><strong>Late Payment:</strong> Failure to complete payment may result in suspension of your tenant account until the outstanding balance is settled.</li>
            <li><strong>Plan Changes:</strong> Upgrades or downgrades follow the subscription change settings applied to your account.</li>
            <li><strong>Billing Disputes:</strong> Any billing disputes must be raised within 30 days of the charge date by contacting MicroFin support.</li>
        </ul>

        <h3 style="color:var(--danger); font-size:1rem; margin:24px 0 8px;">3. Refund Policy</h3>
        <p style="font-size:0.9rem;">All payments are non-refundable for the current billing period. This includes, but is not limited to:</p>
        <ul style="font-size:0.9rem; padding-left:20px; margin-top:8px;">
            <li>Initial activation charges upon account activation.</li>
            <li>Monthly recurring subscription fees, regardless of usage during the billing period.</li>
            <li>Fees charged during any period prior to account suspension or cancellation.</li>
            <li>Any charges already billed before cancellation or deactivation.</li>
        </ul>
        <p style="font-size:0.9rem; margin-top:8px;">We encourage you to evaluate the platform thoroughly during any trial or demo period before committing to a paid subscription.</p>

        <h3 style="color:var(--text-dark); font-size:1rem; margin:24px 0 8px;">4. Account Termination</h3>
        <p style="font-size:0.9rem;">MicroFin reserves the right to terminate or suspend any account that violates these terms, fails to pay subscription fees, or engages in fraudulent activity. Termination does not entitle the tenant to a refund of any previously paid fees.</p>

        <h3 style="color:var(--text-dark); font-size:1rem; margin:24px 0 8px;">5. Data &amp; Privacy</h3>
        <p style="font-size:0.9rem;">Your data is stored in an isolated tenant environment. MicroFin will not share or sell your data to third parties. Card details are encrypted using AES-256 and CVV is never stored. All transactions are logged for compliance and audit purposes.</p>

        <div style="margin-top:32px; text-align:right; border-top: 1px solid var(--card-border); padding-top: 24px;">
            <button id="close-tos-modal-btn" style="background:linear-gradient(135deg,var(--primary),var(--purple-core)); color:#fff; border:none; border-radius:999px; padding:12px 28px; font-weight:600; cursor:pointer; box-shadow: 0 12px 24px -18px rgba(var(--primary-rgb), 0.38);">I Understand</button>
        </div>
    </div>
</div>
