<?php

namespace App\Services;

use App\Models\Motorcycle;
use Brick\Math\BigDecimal;

/**
 * HPP unit metode identifikasi khusus:
 * HPP = harga beli + jumlah biaya kendaraan yang hpp_included = true.
 */
class HppService
{
    public function forMotorcycle(Motorcycle $motorcycle): BigDecimal
    {
        $purchasePrice = Money::of($motorcycle->purchase()->value('purchase_price'));
        $costs = Money::of($motorcycle->costs()->where('hpp_included', true)->sum('amount'));

        return $purchasePrice->plus($costs);
    }
}
