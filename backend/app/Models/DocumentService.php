<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pendapatan di luar jual beli motor: pengurusan surat, pengantaran pinjaman, bonus tahunan leasing. */
class DocumentService extends Model
{
    public const TYPES = ['perpanjang_stnk', 'pajak_tahunan', 'balik_nama', 'mutasi', 'pengantaran_pinjaman', 'bonus_leasing', 'lainnya'];

    protected $fillable = ['service_number', 'customer_id', 'user_id', 'service_date', 'service_type',
        'license_plate', 'description', 'amount_received', 'amount_paid', 'fee'];

    protected function casts(): array
    {
        return ['service_date' => 'date', 'amount_received' => 'decimal:2', 'amount_paid' => 'decimal:2', 'fee' => 'decimal:2'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
