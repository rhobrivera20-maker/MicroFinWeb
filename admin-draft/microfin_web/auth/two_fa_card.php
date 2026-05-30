<?php
/**
 * Reusable Two-Factor Authentication card + modal partial.
 *
 * Caller must define $two_fa_enabled (bool) before including. Optional:
 *   - $two_fa_endpoint  : path to two_fa_endpoint.php (defaults to '/microfin_web/auth/two_fa_endpoint.php')
 *   - $two_fa_card_class: extra CSS class for the wrapper card.
 *
 * The partial renders a self-contained card + a hidden modal and includes its own
 * CSS/JS so it can be dropped into any profile page (admin, staff, super admin).
 */

if (!isset($two_fa_enabled)) {
    $two_fa_enabled = false;
}
$two_fa_enabled = (bool) $two_fa_enabled;

if (!isset($two_fa_endpoint) || !is_string($two_fa_endpoint) || $two_fa_endpoint === '') {
    // Default to absolute URL relative to web root.
    $two_fa_endpoint = '/microfin_web/auth/two_fa_endpoint.php';
}
$two_fa_card_class = isset($two_fa_card_class) ? (string) $two_fa_card_class : '';
?>
<div class="mf-2fa-card <?php echo htmlspecialchars($two_fa_card_class); ?>" id="mf-2fa-card" data-enabled="<?php echo $two_fa_enabled ? '1' : '0'; ?>">
    <div class="mf-2fa-head">
        <div class="mf-2fa-icon"><span class="material-symbols-rounded">encrypted</span></div>
        <div class="mf-2fa-head-text">
            <h3>Two-Factor Authentication</h3>
            <p>Add an extra layer of security to your account using an authenticator app like Google Authenticator, Microsoft Authenticator, or Authy.</p>
        </div>
    </div>

    <div class="mf-2fa-status-row">
        <div class="mf-2fa-status-pill" id="mf-2fa-status-pill">
            <span class="mf-2fa-dot"></span>
            <span id="mf-2fa-status-label"><?php echo $two_fa_enabled ? 'Enabled' : 'Disabled'; ?></span>
        </div>
        <label class="mf-2fa-switch">
            <input type="checkbox" id="mf-2fa-toggle" <?php echo $two_fa_enabled ? 'checked' : ''; ?>>
            <span class="mf-2fa-slider"></span>
        </label>
    </div>

    <div class="mf-2fa-body" id="mf-2fa-body-text">
        <?php if ($two_fa_enabled): ?>
            Two-factor authentication is currently <strong>active</strong>. You'll be asked for a 6-digit code from your authenticator app every time you sign in. Toggle off to disable (you'll need your password and a code).
        <?php else: ?>
            Two-factor authentication is currently <strong>off</strong>. Toggle on to start the setup wizard. You'll scan a QR code with your authenticator app and confirm a 6-digit code.
        <?php endif; ?>
    </div>
</div>

<!-- Setup modal -->
<div class="mf-2fa-modal" id="mf-2fa-setup-modal" hidden onclick="if(event.target===this)document.getElementById('mf-2fa-setup-modal').hidden=true;">
    <div class="mf-2fa-dialog" role="dialog" aria-modal="true" onclick="event.stopPropagation();">
        <button type="button" class="mf-2fa-close-btn" data-2fa-close aria-label="Close">
            <span class="material-symbols-rounded">close</span>
        </button>
        <h3>Enable Two-Factor Authentication</h3>

        <div class="mf-2fa-step" data-step="1">
            <p>Step 1 of 3 — Scan this QR code with your authenticator app.</p>
            <div class="mf-2fa-qr-wrap" id="mf-2fa-qr-wrap">
                <div class="mf-2fa-spinner"></div>
            </div>
            <div class="mf-2fa-secret-row">
                <span>Or enter this key manually:</span>
                <code id="mf-2fa-secret-text"></code>
            </div>
            <div class="mf-2fa-modal-actions">
                <button type="button" class="mf-2fa-btn-ghost" data-2fa-close>Cancel</button>
                <button type="button" class="mf-2fa-btn-primary" id="mf-2fa-step1-next">I've added it — Next</button>
            </div>
        </div>

        <div class="mf-2fa-step" data-step="2" hidden>
            <p>Step 2 of 3 — Save these recovery codes somewhere safe. Each can be used once if you lose access to your authenticator.</p>
            <div class="mf-2fa-recovery-grid" id="mf-2fa-recovery-grid"></div>
            <button type="button" class="mf-2fa-btn-ghost mf-2fa-copy-btn" id="mf-2fa-copy-recovery">
                <span class="material-symbols-rounded">content_copy</span> Copy all
            </button>
            <label class="mf-2fa-check">
                <input type="checkbox" id="mf-2fa-saved-recovery"> I have saved my recovery codes
            </label>
            <div class="mf-2fa-modal-actions">
                <button type="button" class="mf-2fa-btn-ghost" data-2fa-back>Back</button>
                <button type="button" class="mf-2fa-btn-primary" id="mf-2fa-step2-next" disabled>Continue</button>
            </div>
        </div>

        <div class="mf-2fa-step" data-step="3" hidden>
            <p>Step 3 of 3 — Enter the current 6-digit code shown in your authenticator app to finish.</p>
            <input type="text" class="mf-2fa-code-input" id="mf-2fa-confirm-code" inputmode="numeric" maxlength="6" placeholder="123456" autocomplete="one-time-code">
            <div class="mf-2fa-error" id="mf-2fa-confirm-error"></div>
            <div class="mf-2fa-modal-actions">
                <button type="button" class="mf-2fa-btn-ghost" data-2fa-back>Back</button>
                <button type="button" class="mf-2fa-btn-primary" id="mf-2fa-confirm-btn">Activate 2FA</button>
            </div>
        </div>

        <div class="mf-2fa-step" data-step="done" hidden>
            <div class="mf-2fa-success-icon"><span class="material-symbols-rounded">verified</span></div>
            <h4 style="text-align:center; margin: 4px 0 4px;">2FA is now active</h4>
            <p style="text-align:center;">You'll be asked for a code every time you sign in.</p>
            <div class="mf-2fa-modal-actions" style="justify-content:center;">
                <button type="button" class="mf-2fa-btn-primary" data-2fa-close>Done</button>
            </div>
        </div>
    </div>
</div>

<!-- Disable modal -->
<div class="mf-2fa-modal" id="mf-2fa-disable-modal" hidden onclick="if(event.target===this)document.getElementById('mf-2fa-disable-modal').hidden=true;">
    <div class="mf-2fa-dialog" role="dialog" aria-modal="true" onclick="event.stopPropagation();">
        <button type="button" class="mf-2fa-close-btn" data-2fa-close aria-label="Close">
            <span class="material-symbols-rounded">close</span>
        </button>
        <h3>Disable Two-Factor Authentication</h3>
        <p>Confirm your password and a current 2FA code (or a recovery code) to turn 2FA off.</p>
        <label class="mf-2fa-label">Current Password</label>
        <input type="password" id="mf-2fa-disable-password" class="mf-2fa-text-input" autocomplete="current-password">
        <label class="mf-2fa-label">2FA Code or Recovery Code</label>
        <input type="text" id="mf-2fa-disable-code" class="mf-2fa-text-input" placeholder="123456 or xxxx-xxxx" autocomplete="off">
        <div class="mf-2fa-error" id="mf-2fa-disable-error"></div>
        <div class="mf-2fa-modal-actions">
            <button type="button" class="mf-2fa-btn-ghost" data-2fa-close>Cancel</button>
            <button type="button" class="mf-2fa-btn-danger" id="mf-2fa-disable-btn">Disable 2FA</button>
        </div>
    </div>
</div>

<style>
    .mf-2fa-card { background: var(--bg-card, #fff); border: 1px solid var(--border-color, #e2e8f0); border-radius: 12px; padding: 18px 20px; margin-top: 16px; }
    .mf-2fa-card.embedded { background: transparent; border: none; border-radius: 0; padding: 0; margin-top: 24px; }
    [data-theme="dark"] .mf-2fa-card { background: var(--card, #1e293b); border-color: var(--border, #334155); }
    [data-theme="dark"] .mf-2fa-card.embedded { background: transparent; border: none; }
    .mf-2fa-head { display: flex; gap: 12px; align-items: center; margin-bottom: 12px; }
    .mf-2fa-icon { width: 40px; height: 40px; border-radius: 10px; background: rgba(220,38,38,0.1); color: #dc2626; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .mf-2fa-icon .material-symbols-rounded { font-size: 22px; }
    .mf-2fa-head h3 { margin: 0; font-size: 1rem; font-weight: 600; }
    .mf-2fa-head p { margin: 2px 0 0; color: var(--muted, #64748b); font-size: 0.8rem; line-height: 1.4; }
    .mf-2fa-status-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; border-radius: 8px; background: var(--bg-body, #f8fafc); border: 1px solid var(--border-color, #e2e8f0); margin-bottom: 10px; }
    [data-theme="dark"] .mf-2fa-status-row { background: var(--bg, #0f172a); border-color: var(--border, #334155); }
    .mf-2fa-status-pill { display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600; }
    .mf-2fa-dot { width: 8px; height: 8px; border-radius: 50%; background: #94a3b8; }
    .mf-2fa-card[data-enabled="1"] .mf-2fa-dot { background: #16a34a; box-shadow: 0 0 0 4px rgba(22,163,74,0.18); }
    .mf-2fa-card[data-enabled="1"] #mf-2fa-status-label { color: #16a34a; }
    .mf-2fa-card[data-enabled="0"] #mf-2fa-status-label { color: var(--muted, #64748b); }
    .mf-2fa-switch { position: relative; display: inline-block; width: 46px; height: 26px; }
    .mf-2fa-switch input { opacity: 0; width: 0; height: 0; }
    .mf-2fa-slider { position: absolute; cursor: pointer; inset: 0; background: #cbd5e1; transition: 0.2s; border-radius: 26px; }
    .mf-2fa-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: 0.2s; }
    .mf-2fa-switch input:checked + .mf-2fa-slider { background: #16a34a; }
    .mf-2fa-switch input:checked + .mf-2fa-slider:before { transform: translateX(20px); }
    .mf-2fa-body { color: var(--muted, #64748b); font-size: 0.8rem; line-height: 1.5; }

    .mf-2fa-modal[hidden] { display: none !important; }
    .mf-2fa-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; z-index: 99999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); backdrop-filter: blur(3px); padding: 20px; box-sizing: border-box; }
    .mf-2fa-dialog { position: relative; z-index: 2; background: var(--bg-card, #fff); color: var(--text, #0f172a); width: 100%; max-width: 420px; max-height: 85vh; overflow-y: auto; border-radius: 12px; padding: 20px; box-shadow: 0 24px 60px rgba(0,0,0,0.35); }
    [data-theme="dark"] .mf-2fa-dialog { background: var(--card, #1e293b); color: var(--text, #f8fafc); }
    .mf-2fa-dialog h3 { margin: 0 0 6px; font-size: 1.15rem; }
    .mf-2fa-dialog p { margin: 0 0 16px; color: var(--muted, #64748b); font-size: 0.9rem; }
    .mf-2fa-close-btn { position: absolute; top: 14px; right: 14px; background: transparent; border: 0; cursor: pointer; color: var(--muted, #64748b); padding: 6px; border-radius: 8px; }
    .mf-2fa-close-btn:hover { background: var(--bg-body, #f1f5f9); }
    .mf-2fa-qr-wrap { display: flex; align-items: center; justify-content: center; padding: 16px; background: #fff; border-radius: 12px; min-height: 240px; }
    .mf-2fa-qr-wrap img { max-width: 220px; max-height: 220px; }
    .mf-2fa-secret-row { margin: 14px 0 18px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; font-size: 0.82rem; color: var(--muted, #64748b); }
    .mf-2fa-secret-row code { background: var(--bg-body, #f1f5f9); padding: 6px 10px; border-radius: 6px; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; color: var(--text, #0f172a); letter-spacing: 0.05em; word-break: break-all; }
    [data-theme="dark"] .mf-2fa-secret-row code { background: var(--bg, #0f172a); color: var(--text, #f8fafc); }
    .mf-2fa-recovery-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; padding: 14px; background: var(--bg-body, #f8fafc); border-radius: 10px; margin-bottom: 12px; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; }
    [data-theme="dark"] .mf-2fa-recovery-grid { background: var(--bg, #0f172a); }
    .mf-2fa-recovery-grid span { padding: 6px 8px; background: var(--bg-card, #fff); border-radius: 6px; text-align: center; }
    [data-theme="dark"] .mf-2fa-recovery-grid span { background: var(--card, #1e293b); }
    .mf-2fa-copy-btn { display: inline-flex; align-items: center; gap: 6px; }
    .mf-2fa-check { display: flex; align-items: center; gap: 8px; margin: 12px 0 0; font-size: 0.88rem; color: var(--text, #0f172a); cursor: pointer; }
    .mf-2fa-modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
    .mf-2fa-btn-primary, .mf-2fa-btn-ghost, .mf-2fa-btn-danger { padding: 10px 18px; border-radius: 8px; font-weight: 600; font-size: 0.88rem; cursor: pointer; border: 1px solid transparent; font-family: inherit; }
    .mf-2fa-btn-primary { background: #dc2626; color: #fff; border-color: #dc2626; }
    .mf-2fa-btn-primary:hover:not(:disabled) { background: #b91c1c; }
    .mf-2fa-btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
    .mf-2fa-btn-ghost { background: transparent; color: var(--text, #0f172a); border-color: var(--border-color, #e2e8f0); }
    [data-theme="dark"] .mf-2fa-btn-ghost { color: var(--text, #f8fafc); border-color: var(--border, #334155); }
    .mf-2fa-btn-danger { background: #dc2626; color: #fff; border-color: #dc2626; }
    .mf-2fa-btn-danger:hover:not(:disabled) { background: #b91c1c; }
    .mf-2fa-code-input, .mf-2fa-text-input { width: 100%; padding: 12px 14px; border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; background: var(--bg-body, #f8fafc); color: var(--text, #0f172a); font-size: 1rem; box-sizing: border-box; font-family: inherit; }
    [data-theme="dark"] .mf-2fa-code-input, [data-theme="dark"] .mf-2fa-text-input { background: var(--bg, #0f172a); border-color: var(--border, #334155); color: var(--text, #f8fafc); }
    .mf-2fa-code-input { letter-spacing: 0.3em; text-align: center; font-size: 1.2rem; font-family: 'JetBrains Mono', monospace; }
    .mf-2fa-code-input:focus, .mf-2fa-text-input:focus { outline: none; border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,0.15); }
    .mf-2fa-label { display: block; font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted, #64748b); margin: 12px 0 6px; }
    .mf-2fa-error { color: #dc2626; font-size: 0.85rem; font-weight: 500; min-height: 18px; margin-top: 8px; }
    .mf-2fa-spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #dc2626; border-radius: 50%; animation: mf2faSpin 0.8s linear infinite; }
    @keyframes mf2faSpin { to { transform: rotate(360deg); } }
    .mf-2fa-success-icon { display: flex; justify-content: center; color: #16a34a; margin: 8px 0; }
    .mf-2fa-success-icon span { font-size: 56px; }
</style>

<script>
(function () {
    if (window.__mf2faInit) return;
    window.__mf2faInit = true;

    var endpoint = <?php echo json_encode($two_fa_endpoint); ?>;
    var card = document.getElementById('mf-2fa-card');
    var toggle = document.getElementById('mf-2fa-toggle');
    var statusLabel = document.getElementById('mf-2fa-status-label');
    var bodyText = document.getElementById('mf-2fa-body-text');
    var setupModal = document.getElementById('mf-2fa-setup-modal');
    var disableModal = document.getElementById('mf-2fa-disable-modal');

    function setEnabledState(on) {
        card.dataset.enabled = on ? '1' : '0';
        toggle.checked = !!on;
        statusLabel.textContent = on ? 'Enabled' : 'Disabled';
        bodyText.innerHTML = on
            ? "Two-factor authentication is currently <strong>active</strong>. You'll be asked for a 6-digit code from your authenticator app every time you sign in. Toggle off to disable (you'll need your password and a code)."
            : "Two-factor authentication is currently <strong>off</strong>. Toggle on to start the setup wizard. You'll scan a QR code with your authenticator app and confirm a 6-digit code.";
    }

    function openModal(m) {
        // Move modal to body to escape any parent stacking contexts
        if (m.parentElement !== document.body) {
            document.body.appendChild(m);
        }
        // Check if we're in super admin and temporarily disable zoom
        var bodyZoom = getComputedStyle(document.body).zoom || document.body.style.zoom;
        if (bodyZoom === '0.9' || bodyZoom === '0.90') {
            m.dataset.originalZoom = bodyZoom;
            document.body.style.zoom = '1';
        }
        m.hidden = false;
        document.body.style.overflow = 'hidden';
        // Focus first focusable element in modal
        setTimeout(function() {
            var focusable = m.querySelector('input:not([type="hidden"]), button:not([hidden])');
            if (focusable) focusable.focus();
        }, 50);
        // Add focus trap
        m.addEventListener('keydown', trapFocus);
    }
    function closeModal(m) {
        m.hidden = true;
        document.body.style.overflow = '';
        // Restore original zoom if it was stored
        if (m.dataset.originalZoom) {
            document.body.style.zoom = m.dataset.originalZoom;
            delete m.dataset.originalZoom;
        }
        m.removeEventListener('keydown', trapFocus);
    }

    function trapFocus(e) {
        if (e.key !== 'Tab') return;
        var modal = e.currentTarget;
        var focusableElements = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        var firstElement = focusableElements[0];
        var lastElement = focusableElements[focusableElements.length - 1];
        if (e.shiftKey) {
            if (document.activeElement === firstElement) {
                lastElement.focus();
                e.preventDefault();
            }
        } else {
            if (document.activeElement === lastElement) {
                firstElement.focus();
                e.preventDefault();
            }
        }
    }

    function showStep(modal, step) {
        modal.querySelectorAll('.mf-2fa-step').forEach(function (el) {
            el.hidden = el.dataset.step !== step;
        });
    }

    function api(action, data) {
        var fd = new FormData();
        fd.append('action', action);
        Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
        return fetch(endpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    // Toggle handler
    toggle.addEventListener('change', function () {
        var wantsOn = toggle.checked;
        var currentlyOn = card.dataset.enabled === '1';
        toggle.checked = currentlyOn; // revert until confirmed
        if (wantsOn && !currentlyOn) startSetup();
        else if (!wantsOn && currentlyOn) startDisable();
    });

    // SETUP FLOW
    var setupSecret = null, setupRecovery = [];

    function startSetup() {
        showStep(setupModal, '1');
        document.getElementById('mf-2fa-qr-wrap').innerHTML = '<div class="mf-2fa-spinner"></div>';
        document.getElementById('mf-2fa-secret-text').textContent = '';
        document.getElementById('mf-2fa-saved-recovery').checked = false;
        document.getElementById('mf-2fa-step2-next').disabled = true;
        document.getElementById('mf-2fa-confirm-code').value = '';
        document.getElementById('mf-2fa-confirm-error').textContent = '';
        openModal(setupModal);

        api('2fa_setup_init', {}).then(function (res) {
            if (res.status !== 'success') {
                alert(res.message || 'Could not start 2FA setup.');
                closeModal(setupModal);
                return;
            }
            setupSecret = res.secret;
            setupRecovery = res.recovery_codes || [];
            var qr = document.createElement('img');
            qr.src = res.qr_url;
            qr.alt = '2FA QR code';
            qr.onload = function () {};
            document.getElementById('mf-2fa-qr-wrap').innerHTML = '';
            document.getElementById('mf-2fa-qr-wrap').appendChild(qr);
            document.getElementById('mf-2fa-secret-text').textContent = res.secret;

            var grid = document.getElementById('mf-2fa-recovery-grid');
            grid.innerHTML = '';
            setupRecovery.forEach(function (c) {
                var s = document.createElement('span');
                s.textContent = c;
                grid.appendChild(s);
            });
        });
    }

    document.getElementById('mf-2fa-step1-next').addEventListener('click', function () {
        showStep(setupModal, '2');
    });

    document.getElementById('mf-2fa-saved-recovery').addEventListener('change', function (e) {
        document.getElementById('mf-2fa-step2-next').disabled = !e.target.checked;
    });

    document.getElementById('mf-2fa-step2-next').addEventListener('click', function () {
        showStep(setupModal, '3');
        setTimeout(function () { document.getElementById('mf-2fa-confirm-code').focus(); }, 50);
    });

    document.getElementById('mf-2fa-copy-recovery').addEventListener('click', function () {
        var text = setupRecovery.join('\n');
        if (navigator.clipboard) navigator.clipboard.writeText(text);
        else {
            var ta = document.createElement('textarea');
            ta.value = text; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(ta);
        }
        var btn = this;
        var original = btn.innerHTML;
        btn.innerHTML = '<span class="material-symbols-rounded">check</span> Copied';
        setTimeout(function () { btn.innerHTML = original; }, 1600);
    });

    document.getElementById('mf-2fa-confirm-btn').addEventListener('click', function () {
        var code = document.getElementById('mf-2fa-confirm-code').value.trim();
        var err = document.getElementById('mf-2fa-confirm-error');
        err.textContent = '';
        if (!/^\d{6}$/.test(code)) { err.textContent = 'Enter the 6-digit code from your authenticator app.'; return; }
        var btn = this; btn.disabled = true; btn.textContent = 'Verifying...';
        api('2fa_setup_confirm', { code: code }).then(function (res) {
            btn.disabled = false; btn.textContent = 'Activate 2FA';
            if (res.status !== 'success') { err.textContent = res.message || 'Verification failed.'; return; }
            setEnabledState(true);
            showStep(setupModal, 'done');
        });
    });

    // DISABLE FLOW
    function startDisable() {
        document.getElementById('mf-2fa-disable-password').value = '';
        document.getElementById('mf-2fa-disable-code').value = '';
        document.getElementById('mf-2fa-disable-error').textContent = '';
        openModal(disableModal);
        setTimeout(function () { document.getElementById('mf-2fa-disable-password').focus(); }, 50);
    }

    document.getElementById('mf-2fa-disable-btn').addEventListener('click', function () {
        var pwd = document.getElementById('mf-2fa-disable-password').value;
        var code = document.getElementById('mf-2fa-disable-code').value.trim();
        var err = document.getElementById('mf-2fa-disable-error');
        err.textContent = '';
        if (!pwd) { err.textContent = 'Enter your current password.'; return; }
        if (!code) { err.textContent = 'Enter your 2FA code or a recovery code.'; return; }
        var btn = this; btn.disabled = true; btn.textContent = 'Disabling...';
        api('2fa_disable', { password: pwd, code: code }).then(function (res) {
            btn.disabled = false; btn.textContent = 'Disable 2FA';
            if (res.status !== 'success') { err.textContent = res.message || 'Could not disable 2FA.'; return; }
            setEnabledState(false);
            closeModal(disableModal);
        });
    });

    // Back/close handlers
    document.querySelectorAll('[data-2fa-close]').forEach(function (el) {
        el.addEventListener('click', function () {
            closeModal(setupModal); closeModal(disableModal);
        });
    });
    document.querySelectorAll('[data-2fa-back]').forEach(function (el) {
        el.addEventListener('click', function () {
            var current = setupModal.querySelector('.mf-2fa-step:not([hidden])');
            var n = current ? current.dataset.step : '1';
            if (n === '2') showStep(setupModal, '1');
            else if (n === '3') showStep(setupModal, '2');
        });
    });
})();
</script>
