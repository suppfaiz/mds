<?php
/**
 * Lightweight, dependency-free TOTP (Time-based One-Time Password) Helper
 * Compatible with Google Authenticator, Authy, etc.
 */

if (!class_exists('TOTPHelper')) {
    class TOTPHelper {
        private static $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

        /**
         * Generate a random Base32 secret key (16 characters)
         */
        public static function generateSecret() {
            $secret = '';
            for ($i = 0; $i < 16; $i++) {
                $secret .= self::$base32Chars[random_int(0, 31)];
            }
            return $secret;
        }

        /**
         * Base32 decode function
         */
        private static function base32Decode($secret) {
            if (empty($secret)) {
                return '';
            }

            $secret = strtoupper($secret);
            $secret = str_replace('=', '', $secret);
            
            $buf = '';
            $val = 0;
            $bits = 0;

            for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
                $char = $secret[$i];
                $pos = strpos(self::$base32Chars, $char);
                if ($pos === false) {
                    continue; // Skip invalid characters
                }

                $val = ($val << 5) | $pos;
                $bits += 5;

                if ($bits >= 8) {
                    $bits -= 8;
                    $buf .= chr(($val >> $bits) & 255);
                }
            }
            return $buf;
        }

        /**
         * Calculate TOTP code for a secret and time slice
         */
        public static function getCode($secret, $timeSlice = null) {
            if ($timeSlice === null) {
                $timeSlice = floor(time() / 30);
            }

            $secretKey = self::base32Decode($secret);
            
            // Pack time slice into binary (64-bit integer, big-endian)
            // Pack into 32-bit blocks (N*) for compatibility
            $timeBin = pack('N*', 0, $timeSlice);

            // Generate HMAC-SHA1 hash
            $hash = hash_hmac('sha1', $timeBin, $secretKey, true);

            // Dynamic truncation to extract 4 bytes
            $offset = ord($hash[19]) & 0xf;
            $binary = ((ord($hash[$offset]) & 0x7f) << 24) |
                      ((ord($hash[$offset + 1]) & 0xff) << 16) |
                      ((ord($hash[$offset + 2]) & 0xff) << 8) |
                      (ord($hash[$offset + 3]) & 0xff);

            // Modulo 1,000,000 to get a 6-digit code
            $otp = $binary % 1000000;
            return str_pad($otp, 6, '0', STR_PAD_LEFT);
        }

        /**
         * Verify a 6-digit TOTP code with time-drift tolerance (default 1 window = ±30s)
         */
        public static function verifyCode($secret, $code, $discrepancy = 1) {
            $currentTimeSlice = floor(time() / 30);
            
            // Normalize spaces/dashes in user input
            $code = str_replace([' ', '-'], '', $code);
            
            if (strlen($code) !== 6 || !ctype_digit($code)) {
                return false;
            }
            
            for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
                $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
                if (hash_equals($calculatedCode, $code)) {
                    return true;
                }
            }
            
            return false;
        }

        /**
         * Generate otpauth URL for QR Code scanner
         */
        public static function getQRUrl($username, $secret, $issuer = 'Master Data Sekolah') {
            $username = rawurlencode($username);
            $issuer = rawurlencode($issuer);
            return "otpauth://totp/{$issuer}:{$username}?secret={$secret}&issuer={$issuer}";
        }
    }
}
