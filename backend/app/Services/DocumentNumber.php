<?php

namespace App\Services;

/**
 * Pembuat nomor dokumen berurutan per tahun, contoh PB-2026-0001.
 */
final class DocumentNumber
{
    public static function next(string $modelClass, string $column, string $prefix, string $date): string
    {
        $year = substr($date, 0, 4);
        $base = "{$prefix}-{$year}-";

        $last = $modelClass::where($column, 'like', $base.'%')->orderByDesc($column)->value($column);
        $next = $last ? ((int) substr($last, strlen($base))) + 1 : 1;

        return $base.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
