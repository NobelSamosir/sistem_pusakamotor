<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    // Kode akun Tabel 3.8
    public const KAS = '1101';
    public const PERSEDIAAN = '1201';
    public const MODAL = '3101';
    public const PENDAPATAN = '4101';
    public const HPP = '5101';
    public const BEBAN_OPERASIONAL = '6101';

    protected $fillable = ['account_code', 'account_name', 'account_type', 'normal_balance', 'parent_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
