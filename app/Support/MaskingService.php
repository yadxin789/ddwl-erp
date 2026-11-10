<?php

namespace App\Support;

/**
 * P2-04 敏感字段掩码（Q6：本期不做导出，只做展示层脱敏）
 *
 * 统一口径："列表/详情/日志"都用这里，避免各页面自己截字符串导致规则不一致。
 *  - 手机号：138****0000（保留前 3 + 后 4）
 *  - 银行账号：****1234（只保留后 4）
 *  - 薪资金额：****（不显示任何位）
 */
class MaskingService
{
    public static function phone(?string $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '-';
        }

        $digits = preg_replace('/\D/', '', $raw);
        $len = strlen($digits);
        if ($len === 0) {
            return str_repeat('*', mb_strlen($raw));
        }
        // 带国家码的手机号（+86 138...）按 11 位手机号处理
        if ($len === 13 && str_starts_with($digits, '86')) {
            $digits = substr($digits, 2);
            $len = 11;
        }
        if ($len <= 7) {
            return '****';
        }

        return substr($digits, 0, 3) . '****' . substr($digits, -4);
    }

    public static function bankAccount(?string $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '-';
        }

        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === '' || strlen($digits) <= 4) {
            return '****';
        }

        return '****' . substr($digits, -4);
    }

    public static function salary(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return '****';
    }

    /** 通用入口：按字段类型分派，便于日志/导出前统一处理 */
    public static function mask(?string $value, string $type): string
    {
        return match ($type) {
            'phone', 'mobile' => self::phone($value),
            'bank', 'bank_account' => self::bankAccount($value),
            default => $value === null || $value === '' ? '-' : '****',
        };
    }
}
