<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Hitungan uang dengan aritmetika desimal (bukan float),
 * supaya tidak muncul selisih seperti 15.117.949,999999998.
 */
final class Money
{
    public static function of(string|int|float|null $value): BigDecimal
    {
        return BigDecimal::of((string) ($value ?? 0))->toScale(2, RoundingMode::HALF_UP);
    }

    public static function str(BigDecimal $value): string
    {
        return (string) $value->toScale(2, RoundingMode::HALF_UP);
    }
}
