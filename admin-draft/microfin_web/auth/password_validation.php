<?php
/**
 * Reusable Password Validation Functions
 * 
 * Provides common password validation logic that can be used across
 * different authentication contexts (password change, password reset, etc.).
 */

if (!function_exists('validate_password_requirements')) {
    /**
     * Validates password meets minimum requirements
     * 
     * @param string $password The password to validate
     * @param int $minLength Minimum required length (default: 8)
     * @return array Returns ['valid' => bool, 'message' => string]
     */
    function validate_password_requirements(string $password, int $minLength = 8): array
    {
        if (strlen($password) < $minLength) {
            return [
                'valid' => false,
                'message' => "Password must be at least {$minLength} characters."
            ];
        }

        return ['valid' => true, 'message' => ''];
    }
}

if (!function_exists('validate_password_not_same_as_old')) {
    /**
     * Validates that new password is different from old password
     * 
     * @param string $newPassword The new password to validate
     * @param string $oldPasswordHash The hashed old password from database
     * @return array Returns ['valid' => bool, 'message' => string]
     */
    function validate_password_not_same_as_old(string $newPassword, string $oldPasswordHash): array
    {
        if (password_verify($newPassword, $oldPasswordHash)) {
            return [
                'valid' => false,
                'message' => 'New password must be different from your current password.'
            ];
        }

        return ['valid' => true, 'message' => ''];
    }
}

if (!function_exists('validate_current_password')) {
    /**
     * Validates that the provided current password matches the stored hash
     * 
     * @param string $currentPassword The current password provided by user
     * @param string $storedPasswordHash The hashed password from database
     * @return array Returns ['valid' => bool, 'message' => string]
     */
    function validate_current_password(string $currentPassword, string $storedPasswordHash): array
    {
        if (!password_verify($currentPassword, $storedPasswordHash)) {
            return [
                'valid' => false,
                'message' => 'Current password is incorrect.'
            ];
        }

        return ['valid' => true, 'message' => ''];
    }
}

if (!function_exists('validate_password_change')) {
    /**
     * Complete validation for password change operation
     * Combines all password change validations in one call
     * 
     * @param string $currentPassword The current password provided by user
     * @param string $newPassword The new password to set
     * @param string $storedPasswordHash The hashed password from database
     * @param int $minLength Minimum required length (default: 8)
     * @return array Returns ['valid' => bool, 'message' => string]
     */
    function validate_password_change(string $currentPassword, string $newPassword, string $storedPasswordHash, int $minLength = 8): array
    {
        // Validate current password matches
        $currentValidation = validate_current_password($currentPassword, $storedPasswordHash);
        if (!$currentValidation['valid']) {
            return $currentValidation;
        }

        // Validate new password meets requirements
        $requirementsValidation = validate_password_requirements($newPassword, $minLength);
        if (!$requirementsValidation['valid']) {
            return $requirementsValidation;
        }

        // Validate new password is different from old
        $notSameValidation = validate_password_not_same_as_old($newPassword, $storedPasswordHash);
        if (!$notSameValidation['valid']) {
            return $notSameValidation;
        }

        return ['valid' => true, 'message' => ''];
    }
}
