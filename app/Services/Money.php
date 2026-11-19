<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;

final class Money
{
    public static function minor(mixed $value): int
    {
        $text = (string) $value;
        if (!preg_match('/^([0-9]{1,16})(?:\.([0-9]{1,2}))?$/D', $text, $parts)) {
            throw new BusinessRuleException('Nominal tidak valid: maksimal dua angka desimal.', 422, 'INVALID_PAYMENT');
        }
        return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    public static function amount(mixed $value): string
    {
        $minor = self::minor($value);
        if ($minor <= 0 || $minor > 999999999999) {
            throw new BusinessRuleException('Nominal di luar kapasitas pembayaran.', 422, 'INVALID_PAYMENT');
        }
        return self::decimal($minor);
    }

    // Core financial columns use DECIMAL(12,2), including order totals.
    public static function addLine(int $total, mixed $price, int $quantity): int
    {
        $unit = self::minor($price);
        $max = 999999999999;
        if ($quantity <= 0 || $total > $max || $unit > intdiv($max - $total, $quantity)) {
            throw new BusinessRuleException('Total melebihi kapasitas nominal transaksi.', 422, 'INVALID_ORDER_TOTAL');
        }
        return $total + $unit * $quantity;
    }

    public static function decimal(int $minor): string
    {
        if ($minor < 0) {
            throw new BusinessRuleException('Nominal negatif tidak valid.', 422, 'INVALID_PAYMENT');
        }
        return intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
