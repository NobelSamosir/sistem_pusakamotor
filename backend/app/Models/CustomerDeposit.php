<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uang muka / tanda jadi pelanggan. Unit boleh belum ada (dicari dulu ke showroom lain). */
class CustomerDeposit extends Model
{
    public const AKTIF = 'aktif';
    public const DIPAKAI = 'dipakai';
    public const DIKEMBALIKAN = 'dikembalikan';
    public const HANGUS = 'hangus';

    protected $fillable = [
        'deposit_number', 'motorcycle_id', 'unit_description', 'customer_id', 'user_id', 'deposit_date', 'amount',
        'status', 'sale_id', 'closed_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['deposit_date' => 'date', 'closed_at' => 'date', 'amount' => 'decimal:2'];
    }

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
