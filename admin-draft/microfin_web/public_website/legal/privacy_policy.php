<?php
// Privacy Policy modal partial — included by demo.php
// To update the Privacy Policy content, edit this file only.
?>
<div id="pp-modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index:9999; overflow-y:auto; padding:40px 20px;">
    <div style="background:var(--card-bg); border:1px solid var(--card-border); border-radius:18px; max-width:680px; margin:0 auto; padding:40px; color:var(--text-muted); line-height:1.7; box-shadow: var(--card-shadow);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
            <h2 style="margin:0; font-size:1.4rem; color:var(--text-dark); font-weight: 800;">Privacy Policy</h2>
            <button id="close-pp-modal" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.8rem; line-height:1; transition: color 0.2s;">&times;</button>
        </div>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:24px;">Last Updated: <?php echo date('F d, Y'); ?> &mdash; MicroFin Platform</p>

        <h3 style="color:var(--text-dark); font-size:1rem; margin:20px 0 8px;">1. Information We Collect</h3>
        <p style="font-size:0.9rem;">To process your application and provide our services, we collect the following:</p>
        <ul style="font-size:0.9rem; padding-left:20px; margin-top:8px;">
            <li><strong>Personal Identity:</strong> Full name, date of birth, and suffix of the primary contact person.</li>
            <li><strong>Contact Information:</strong> Business email address and mobile/phone numbers.</li>
            <li><strong>Institution Details:</strong> Name of the institution, location, and desired platform slug.</li>
            <li><strong>Legitimacy Documents:</strong> Scanned copies of DTI/SEC registrations, BIR Certificate of Registration, and Business Permits.</li>
        </ul>

        <h3 style="color:var(--text-dark); font-size:1rem; margin:24px 0 8px;">2. How We Use Your Data</h3>
        <p style="font-size:0.9rem;">Your data is used exclusively for:</p>
        <ul style="font-size:0.9rem; padding-left:20px; margin-top:8px;">
            <li>Verifying the legitimacy of your institution before granting platform access.</li>
            <li>Creating your isolated tenant environment and administrative accounts.</li>
            <li>Processing subscription payments and billing-related communications.</li>
            <li>Providing technical support and critical system updates.</li>
        </ul>

        <h3 style="color:var(--text-dark); font-size:1rem; margin:24px 0 8px;">3. Data Security &amp; Isolation</h3>
        <p style="font-size:0.9rem;">MicroFin is built on an isolated architecture. Your institutional data is logically separated from other tenants. We employ AES-256 encryption for sensitive data at rest and TLS/SSL for data in transit.</p>

        <h3 style="color:var(--text-dark); font-size:1rem; margin:24px 0 8px;">4. Data Disclosure</h3>
        <p style="font-size:0.9rem;">We do not sell, trade, or otherwise transfer your personally identifiable information to outside parties. This does not include trusted third parties who assist us in operating our platform (e.g., payment gateways), so long as those parties agree to keep this information confidential.</p>

        <h3 style="color:var(--text-dark); font-size:1rem; margin:24px 0 8px;">5. Your Rights</h3>
        <p style="font-size:0.9rem;">In accordance with local data privacy regulations, you have the right to access, correct, or request the deletion of your personal data. You may contact MicroFin support for any privacy-related inquiries.</p>

        <div style="margin-top:32px; text-align:right; border-top: 1px solid var(--card-border); padding-top: 24px;">
            <button id="close-pp-modal-btn" style="background:linear-gradient(135deg,var(--primary),var(--purple-core)); color:#fff; border:none; border-radius:999px; padding:12px 28px; font-weight:600; cursor:pointer; box-shadow: 0 12px 24px -18px rgba(var(--primary-rgb), 0.38);">Got it</button>
        </div>
    </div>
</div>
