<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    // Nilai reference_type
    public const REF_PURCHASE = 'purchase';
    public const REF_VEHICLE_COST = 'vehicle_cost';
    public const REF_SALE = 'sale';
    public const REF_OPERATING_EXPENSE = 'operating_expense';
    public const REF_MANUAL = 'manual';
    public const REF_PRIVE = 'prive';
    public const REF_LEASING_DISBURSEMENT = 'leasing_disbursement';
    public const REF_LEASING_COMMISSION = 'leasing_commission';
    public const REF_DEPOSIT = 'deposit';
    public const REF_CONSIGNMENT_PAYOUT = 'consignment_payout';
    public const REF_SUPPLY_PURCHASE = 'supply_purchase';
    public const REF_DOCUMENT_SERVICE = 'document_service';

    protected $fillable = ['journal_number', 'entry_date', 'description', 'reference_type', 'reference_id', 'user_id'];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
