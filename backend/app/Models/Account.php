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
    public const PIUTANG_LEASING = '1102';
    public const PERLENGKAPAN = '1301';
    public const UANG_MUKA = '2101';
    public const UTANG_KONSINYASI = '2102';
    public const PRIVE = '3102';
    public const KOMISI_LEASING = '4201';
    public const KOMISI_TITIP = '4202';
    public const PENDAPATAN_LAIN = '4203';
    public const JASA_SURAT = '4204';

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
