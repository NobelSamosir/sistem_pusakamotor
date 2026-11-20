<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Motorcycle extends Model
{
    public const STATUS_TERSEDIA = 'tersedia';
    public const STATUS_PERBAIKAN = 'perbaikan';
    public const STATUS_TERJUAL = 'terjual';

    protected $fillable = [
        'vehicle_code', 'frame_number', 'engine_number', 'license_plate', 'bpkb_number',
        'brand', 'model', 'year', 'transmission', 'engine_capacity', 'body_type',
        'mileage', 'ownership_status', 'has_bpkb', 'has_stnk', 'has_faktur', 'has_buku_servis',
        'color', 'condition_notes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'mileage' => 'integer',
            'has_bpkb' => 'boolean',
            'has_stnk' => 'boolean',
            'has_faktur' => 'boolean',
            'has_buku_servis' => 'boolean',
        ];
    }

    public function purchase(): HasOne
    {
        return $this->hasOne(Purchase::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(VehicleCost::class);
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(PricePrediction::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(PriceRecommendation::class);
    }

    public function isSold(): bool
    {
        return $this->status === self::STATUS_TERJUAL;
    }

    /** Nomor rangka/mesin disimpan huruf besar tanpa spasi. */
    public static function normalizeNumber(?string $value): ?string
    {
        return $value === null ? null : strtoupper(preg_replace('/\s+/', '', $value));
    }
}
