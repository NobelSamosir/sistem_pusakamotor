<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricePrediction extends Model
{
    protected $fillable = [
        'motorcycle_id', 'ml_model_id', 'user_id', 'input_snapshot',
        'predicted_price', 'lower_bound', 'upper_bound', 'warnings',
    ];

    protected function casts(): array
    {
        return [
            'input_snapshot' => 'array',
            'warnings' => 'array',
            'predicted_price' => 'decimal:2',
            'lower_bound' => 'decimal:2',
            'upper_bound' => 'decimal:2',
        ];
    }

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    public function mlModel(): BelongsTo
    {
        return $this->belongsTo(MlModel::class);
    }
}
