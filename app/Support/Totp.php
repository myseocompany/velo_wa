<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal RFC 6238 TOTP implementation (no external dependencies).
 * Generates and validates 6-digit codes using HMAC-SHA1.
 */
final class Totp
{
    private const DIGITS   = 6;
    private const PERIOD   = 30;
    private const WINDOW   = 1; // ±1 period for clock skew

    /** Generate a random base32-encoded secret (160-bit / 20 bytes). */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /**
     * Generate the current TOTP code for a given secret.
     * Useful for seeding / testing.
     */
    public static function currentCode(string $secret): string
    {
        return self::codeAt($secret, (int) floor(time() / self::PERIOD));
    }

    /** Validate a code against the secret, allowing ±WINDOW intervals. */
    public static function verify(string $secret, string $code): bool
    {
        $counter = (int) floor(time() / self::PERIOD);

        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            if (hash_equals(self::codeAt($secret, $counter + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a otpauth:// URI for QR code scanning.
     * $issuer  — app name displayed in authenticator
     * $account — usually the admin's email
     */
    public static function otpauthUri(string $secret, string $account, string $issuer = 'AriCRM'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($account),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD,
        );
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private static function codeAt(string $secret, int $counter): string
    {
        $key     = self::base32Decode($secret);
        $payload = pack('N*', 0) . pack('N*', $counter);
        $hash    = hash_hmac('sha1', $payload, $key, true);
        $offset  = ord($hash[19]) & 0x0F;
        $code    = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
            (ord($hash[$offset + 3])  & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output   = '';
        $buffer   = 0;
        $bits     = 0;

        foreach (str_split($data) as $char) {
            $buffer = ($buffer << 8) | ord($char);
            $bits  += 8;
            while ($bits >= 5) {
                $bits  -= 5;
                $output .= $alphabet[($buffer >> $bits) & 0x1F];
            }
        }

        if ($bits > 0) {
            $output .= $alphabet[($buffer << (5 - $bits)) & 0x1F];
        }

        return $output;
    }

    private static function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data     = strtoupper((string) preg_replace('/\s/', '', $data));
        $output   = '';
        $buffer   = 0;
        $bits     = 0;

        foreach (str_split($data) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $pos;
            $bits  += 5;
            if ($bits >= 8) {
                $bits  -= 8;
                $output .= chr(($buffer >> $bits) & 0xFF);
            }
        }

        return $output;
    }
}
