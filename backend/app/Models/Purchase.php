<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends Model
{
    protected $fillable = [
        'purchase_number', 'motorcycle_id', 'supplier_id', 'user_id',
        'purchase_date', 'purchase_price', 'payment_method', 'notes',
    ];

    protected function casts(): array
    {
        return ['purchase_date' => 'date', 'purchase_price' => 'decimal:2'];
    }

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
