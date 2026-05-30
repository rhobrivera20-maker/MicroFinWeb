# Tutorial Implementation Plan - Audit Logs Approach

## Overview
Implement an interactive guided tutorial for first-time admin users using audit_logs table to detect first login.

## First-Time Login Detection
Instead of checking `setup_completed` flag, use audit_logs table:
- Query `audit_logs` table for the current `user_id`
- If no records exist for that user_id, it's their first login
- Trigger tutorial automatically on first login

## Database Schema Reference

### audit_logs table (for first-login detection)
```sql
CREATE TABLE `audit_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `tenant_id` varchar(50) DEFAULT NULL,
  `action_type` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_tenant_date` (`tenant_id`,`created_at`)
)
```

### branding table (for tutorial styling)
```sql
CREATE TABLE `branding` (
  `branding_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(50) NOT NULL,
  `font_family` varchar(50) DEFAULT 'Inter',
  `theme_primary_color` varchar(10) DEFAULT '#dc2626',
  `theme_secondary_color` varchar(10) DEFAULT '#991b1b',
  `theme_text_main` varchar(10) DEFAULT '#0f172a',
  `theme_text_muted` varchar(10) DEFAULT '#64748b',
  `theme_bg_body` varchar(10) DEFAULT '#f8fafc',
  `theme_bg_card` varchar(10) DEFAULT '#ffffff',
  `theme_border_color` varchar(10) DEFAULT '#e2e8f0',
  -- ... other fields
  PRIMARY KEY (`branding_id`)
)
```

## Implementation Steps

### Phase 1: Backend Logic (admin.php)
1. Add debug flag variable at top of file: `$tutorial_debug_mode = false;`
   - `false` = debug mode (always show tutorial, skip audit_logs check)
   - `true` = production mode (check audit_logs for first-time login)
2. If `$tutorial_debug_mode === false`, inject JavaScript flag directly: `<script>window.startTutorial = true;</script>`
3. If `$tutorial_debug_mode === true`:
   - Query audit_logs table for current user_id
   - If count = 0, inject JavaScript flag: `<script>window.startTutorial = true;</script>`
4. The first login action itself will create an audit_log entry, so tutorial only shows once in production mode

### Phase 2: Frontend Files (to be created in this folder)
1. **tutorial.js** - Main tutorial logic using a lightweight library (Shepherd.js or Intro.js)
2. **tutorial.css** - Styling for tutorial tooltips matching MicroFin branding
3. **complete_tutorial.php** - AJAX endpoint to mark tutorial as completed (optional, can use localStorage)

### Phase 3: Tutorial Steps
Based on original plan, adapt these steps:
1. **Welcome Message** (Center Screen) - "Welcome to MicroFin! Let's take a quick tour."
2. **Sidebar Navigation** - Highlight left sidebar, explain modules access
3. **Settings / Profile** - Highlight top-right profile icon
4. **Action Buttons** - Highlight main dashboard CTAs (Add Client, New Loan)
5. **Completion** - "You're all set!" with option to end tour

### Phase 4: Integration
1. Add `data-tutorial-step="X"` attributes to HTML elements in admin.php
2. Conditionally include tutorial.js/css based on first-login detection
3. **Inject branding colors from database** into tutorial JavaScript:
   - Query `branding` table for current tenant_id
   - Pass these values to JavaScript: `theme_primary_color`, `theme_secondary_color`, `font_family`, `theme_text_main`, `theme_bg_card`
   - Use these colors for tutorial tooltips, buttons, and overlay
4. Add transparent dark overlay for focus (using `theme_primary_color` with opacity)

## Technical Considerations
- **Library Choice**: Shepherd.js (modern, customizable) or Intro.js (lightweight)
- **Branding**: Use tenant's theme colors from database
- **Persistence**: Since audit_logs will have entry after first login, tutorial won't show again
- **Mobile**: Ensure tutorial works on mobile devices (admin panel is responsive)

## Files to Create
- `tutorial.js` - Tutorial orchestration
- `tutorial.css` - Tutorial styling
- `complete_tutorial.php` - Optional completion endpoint

## Files to Modify
- `admin.php` - Add first-login check and data attributes
