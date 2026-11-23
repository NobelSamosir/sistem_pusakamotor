<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pembelian perlengkapan (bahan habis pakai) seperti cat, dempul, amplas, oli. */
class SupplyPurchase extends Model
{
    protected $fillable = ['user_id', 'purchase_date', 'description', 'vendor', 'amount'];

    protected function casts(): array
    {
        return ['purchase_date' => 'date', 'amount' => 'decimal:2'];
    }
}
