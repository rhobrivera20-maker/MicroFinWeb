<?php
/**
 * staff_admin_email_helper.php
 * Helper functions for checking if an email is used by staff or admin accounts
 */

require_once '../../microfin_backend/config/db_connect.php';

/**
 * Check if an email is currently being used by an Employee or Admin user
 *
 * @param PDO $pdo Database connection handle
 * @param string $email Email address to check
 * @param int|string|null $tenant_id Optional tenant ID to scope the check. If null, checks across all tenants.
 * @return bool Returns true if email is used by Employee or Admin, false otherwise
 */
function is_email_used_by_staff_or_admin(PDO $pdo, string $email, $tenant_id = null): bool
{
    if (empty($email)) {
        return false;
    }

    $sql = 'SELECT COUNT(*) FROM users WHERE email = ? AND user_type IN (?, ?)';
    $params = [$email, 'Employee', 'Admin'];

    if ($tenant_id !== null) {
        $sql .= ' AND tenant_id = ?';
        $params[] = $tenant_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn() > 0;
}
