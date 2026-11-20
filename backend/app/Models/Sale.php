<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    protected $fillable = [
        'sale_number', 'motorcycle_id', 'customer_id', 'user_id', 'sale_date',
        'sale_price', 'hpp_amount', 'gross_profit', 'payment_method', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'sale_price' => 'decimal:2',
            'hpp_amount' => 'decimal:2',
            'gross_profit' => 'decimal:2',
        ];
    }

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
