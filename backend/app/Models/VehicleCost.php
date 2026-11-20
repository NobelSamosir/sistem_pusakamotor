<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleCost extends Model
{
    protected $fillable = [
        'motorcycle_id', 'user_id', 'cost_date', 'cost_category',
        'description', 'amount', 'hpp_included',
    ];

    protected function casts(): array
    {
        return ['cost_date' => 'date', 'amount' => 'decimal:2', 'hpp_included' => 'boolean'];
    }

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }
}
