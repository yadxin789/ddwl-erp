<?php

namespace App\Support;

/**
 * P3-3 TOTP（RFC 6238）：不引入第三方包，SHA1 / 6 位 / 30 秒，允许前后 1 个时间窗。
 * 密钥用 Base32 展示（认证器 App 通用格式）。
 */
class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    public function provisioningUri(string $secret, string $account, string $issuer = 'DDWL-ERP'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    public function verify(string $secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        $code = preg_replace('/[^0-9]/', '', $code);
        if (strlen($code) !== 6 || $secret === '') {
            return false;
        }

        $key = $this->base32Decode($secret);
        if ($key === '') {
            return false;
        }

        $slice = intdiv($timestamp ?? time(), 30);
        for ($i = -$window; $i <= $window; $i++) {
            $otp = $this->codeAt($key, $slice + $i);
            if (hash_equals($otp, $code)) {
                return true;
            }
        }

        return false;
    }

    public function codeAt(string $key, int $slice): string
    {
        $hash = hash_hmac('sha1', pack('J', $slice), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** 8 组恢复码，格式 XXXX-XXXX。 */
    public function recoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(4)));
            $codes[] = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
        }

        return $codes;
    }

    public function normalizeRecovery(string $code): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
        if (strlen($code) === 8) {
            return substr($code, 0, 4) . '-' . substr($code, 4, 4);
        }

        return $code;
    }

    public function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $out;
    }

    public function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(rtrim(str_replace([' ', '='], '', $encoded), '='));
        $bits = '';
        foreach (str_split($encoded) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                return '';
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
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
