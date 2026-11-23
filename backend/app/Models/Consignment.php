<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Titip jual (konsinyasi). Motor milik penitip, BUKAN persediaan showroom.
 * Saat laku: showroom berutang consignor_price kepada penitip,
 * selisih harga jual - consignor_price menjadi pendapatan komisi titip jual.
 */
class Consignment extends Model
{
    public const DITITIPKAN = 'dititipkan';
    public const TERJUAL = 'terjual';
    public const LUNAS = 'lunas';
    public const DIKEMBALIKAN = 'dikembalikan';

    protected $fillable = [
        'consignment_number', 'motorcycle_id', 'consignor_id', 'user_id', 'received_date',
        'consignor_price', 'status', 'returned_at', 'paid_at', 'paid_amount', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date', 'returned_at' => 'date', 'paid_at' => 'date',
            'consignor_price' => 'decimal:2', 'paid_amount' => 'decimal:2',
        ];
    }

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    public function consignor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'consignor_id');
    }
}
