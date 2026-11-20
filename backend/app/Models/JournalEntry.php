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
