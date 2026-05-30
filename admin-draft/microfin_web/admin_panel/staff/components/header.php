<?php
// Ensure variables are defined for IDE linting
$ui_theme = $ui_theme ?? $_SESSION['ui_theme'] ?? 'light';
$initials = $initials ?? strtoupper(substr($_SESSION['username'] ?? 'U', 0, 2));
?>
<header class="topbar">
    <button class="icon-btn" id="themeToggle" title="Toggle dark mode">
        <span class="material-symbols-rounded ms"><?php echo $ui_theme === 'dark' ? 'light_mode' : 'dark_mode'; ?></span>
    </button>

    <a href="?tab=profile" class="user-chip" style="text-decoration:none;">
        <div class="avatar"><?php echo $initials; ?></div>
        <div>
            <div class="user-chip-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
            <div class="user-chip-role"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Employee'); ?></div>
        </div>
    </a>
</header>
