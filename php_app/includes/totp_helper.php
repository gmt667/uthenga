<?php
/**
 * Uthenga — TOTP Helper
 * Pure PHP implementation of RFC 6238 Google Authenticator compatible two-factor authentication.
 * No external dependencies required.
 */

class TotpHelper {
    private static $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generates a random Base32 secret key.
     */
    public static function generateSecret(int $length = 16): string {
        $secret = '';
        $charsLen = strlen(self::$base32Chars);
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Chars[random_int(0, $charsLen - 1)];
        }
        return $secret;
    }

    /**
     * Returns the Google Authenticator QR Code URL using api.qrserver.com
     */
    public static function getQrCodeUrl(string $label, string $secret, string $issuer = 'Uthenga'): string {
        $otpauthUrl = 'otpauth://totp/' . rawurlencode($label) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer);
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($otpauthUrl);
    }

    /**
     * Verifies the 6-digit TOTP code against the secret key.
     */
    public static function verifyCode(string $secret, string $code, int $discrepancy = 1): bool {
        // Strip any spaces from the code
        $code = str_replace(' ', '', $code);
        if (!preg_match('/^[0-9]{6}$/', $code)) {
            return false;
        }

        $currentTimeSlice = floor(time() / 30);
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generates the 6-digit TOTP code for a specific time slice.
     */
    private static function getCode(string $secret, int $timeSlice): string {
        $secretKey = self::base32Decode($secret);
        if ($secretKey === '') {
            return '';
        }
        // Pack time slice into binary string (64-bit big-endian)
        $timeBin = pack('N*', 0) . pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $timeBin, $secretKey, true);
        $offset = ord($hmac[19]) & 0xf;
        $hashPart = (ord($hmac[$offset]) & 0x7f) << 24
            | (ord($hmac[$offset + 1]) & 0xff) << 16
            | (ord($hmac[$offset + 2]) & 0xff) << 8
            | (ord($hmac[$offset + 3]) & 0xff);
        $otp = $hashPart % 1000000;
        return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Decodes a Base32 encoded string.
     */
    private static function base32Decode(string $base32): string {
        $base32 = strtoupper($base32);
        // Remove padding '=' if present
        $base32 = rtrim($base32, '=');
        if ($base32 === '' || !preg_match('/^[A-Z2-7]+$/', $base32)) {
            return '';
        }
        $val = 0;
        $valLen = 0;
        $binary = '';
        $charMap = array_flip(str_split(self::$base32Chars));
        for ($i = 0; $i < strlen($base32); $i++) {
            if (!isset($charMap[$base32[$i]])) {
                return '';
            }
            $val = ($val << 5) | $charMap[$base32[$i]];
            $valLen += 5;
            if ($valLen >= 8) {
                $valLen -= 8;
                $binary .= chr(($val >> $valLen) & 0xFF);
            }
        }
        return $binary;
    }
}
