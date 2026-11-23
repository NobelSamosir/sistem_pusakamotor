<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    protected $fillable = [
        'sale_number', 'motorcycle_id', 'customer_id', 'user_id', 'sale_date',
        'sale_price', 'hpp_amount', 'gross_profit', 'payment_method', 'notes',
        'sale_type', 'leasing_company', 'down_payment', 'financed_amount', 'disbursement_status',
        'disbursed_at', 'leasing_commission', 'commission_received_at', 'deposit_applied',
        
    ];

    public const TUNAI = 'tunai';
    public const KREDIT = 'kredit';

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'sale_price' => 'decimal:2',
            'hpp_amount' => 'decimal:2',
            'gross_profit' => 'decimal:2',
            'down_payment' => 'decimal:2',
            'financed_amount' => 'decimal:2',
            'disbursed_at' => 'date',
            'leasing_commission' => 'decimal:2',
            'commission_received_at' => 'date',
            'deposit_applied' => 'decimal:2',
        ];
    }

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    public function deposit(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CustomerDeposit::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
