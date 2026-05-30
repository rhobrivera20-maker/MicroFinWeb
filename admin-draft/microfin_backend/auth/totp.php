<?php
/**
 * MicroFin TOTP (RFC 6238) helper for 2-factor authentication.
 * Compatible with Google Authenticator / Microsoft Authenticator / Authy.
 *
 * - 20-byte (160-bit) secret encoded as Base32 (Google Authenticator standard).
 * - 30-second period, 6 digits, SHA1 HMAC.
 * - Verifies with +/- 1 step window for clock drift tolerance.
 * - Recovery codes: 10 random codes (8 hex chars), stored hashed.
 */

if (!function_exists('mf_totp_base32_encode')) {
    function mf_totp_base32_encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        for ($i = 0, $len = strlen($bytes); $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $out .= $alphabet[bindec($chunk)];
        }
        return $out;
    }
}

if (!function_exists('mf_totp_base32_decode')) {
    function mf_totp_base32_decode(string $b32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        $bits = '';
        for ($i = 0, $len = strlen($b32); $i < $len; $i++) {
            $val = strpos($alphabet, $b32[$i]);
            if ($val === false) continue;
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}

if (!function_exists('mf_totp_generate_secret')) {
    /** Returns a Base32-encoded 20-byte secret. */
    function mf_totp_generate_secret(): string
    {
        return mf_totp_base32_encode(random_bytes(20));
    }
}

if (!function_exists('mf_totp_code_at')) {
    function mf_totp_code_at(string $b32Secret, int $timeSlice): string
    {
        $key = mf_totp_base32_decode($b32Secret);
        // Pack timeslice as 8-byte big-endian.
        $bin = "\0\0\0\0" . pack('N', $timeSlice);
        $hash = hash_hmac('sha1', $bin, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;
        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('mf_totp_verify')) {
    /** Verifies a 6-digit TOTP code with +/- $window 30-sec steps tolerance. */
    function mf_totp_verify(string $b32Secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', (string)$code);
        if (!preg_match('/^\d{6}$/', $code)) return false;
        $now = (int) floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(mf_totp_code_at($b32Secret, $now + $i), $code)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('mf_totp_otpauth_uri')) {
    function mf_totp_otpauth_uri(string $b32Secret, string $accountLabel, string $issuer): string
    {
        $label = rawurlencode($issuer . ':' . $accountLabel);
        $params = http_build_query([
            'secret' => $b32Secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ]);
        return 'otpauth://totp/' . $label . '?' . $params;
    }
}

if (!function_exists('mf_totp_qr_image_url')) {
    /** Public QR rendering service. Frontend uses this in <img src>. */
    function mf_totp_qr_image_url(string $otpauthUri, int $size = 220): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
             . '&margin=4&data=' . rawurlencode($otpauthUri);
    }
}

if (!function_exists('mf_totp_generate_recovery_codes')) {
    /** Returns 10 plaintext recovery codes formatted XXXX-XXXX. */
    function mf_totp_generate_recovery_codes(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = strtolower(bin2hex(random_bytes(4))); // 8 hex chars
            $codes[] = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
        }
        return $codes;
    }
}

if (!function_exists('mf_totp_hash_recovery_codes')) {
    /** Returns a JSON-encoded array of password_hash() values for storage. */
    function mf_totp_hash_recovery_codes(array $codes): string
    {
        $hashed = array_map(function ($c) {
            return password_hash(strtolower(trim($c)), PASSWORD_DEFAULT);
        }, $codes);
        return (string) json_encode($hashed);
    }
}

if (!function_exists('mf_totp_consume_recovery_code')) {
    /**
     * Verifies a recovery code against the stored JSON list and removes it if valid.
     * Returns the new JSON string to persist, or null if the code did not match.
     */
    function mf_totp_consume_recovery_code(?string $storedJson, string $code): ?string
    {
        $code = strtolower(trim($code));
        if ($code === '' || $storedJson === null || $storedJson === '') return null;
        $list = json_decode($storedJson, true);
        if (!is_array($list)) return null;
        foreach ($list as $idx => $hash) {
            if (is_string($hash) && password_verify($code, $hash)) {
                array_splice($list, $idx, 1);
                return (string) json_encode(array_values($list));
            }
        }
        return null;
    }
}
