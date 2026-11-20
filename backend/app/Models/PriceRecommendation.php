<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceRecommendation extends Model
{
    protected $fillable = [
        'recommendation_type', 'motorcycle_id', 'price_prediction_id', 'user_id', 'target_markup',
        'hpp', 'target_price', 'offer_price', 'estimated_service_cost', 'max_purchase_price',
        'decision_status', 'recommended_price', 'expected_profit', 'expected_margin', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'target_markup' => 'decimal:4',
            'hpp' => 'decimal:2',
            'target_price' => 'decimal:2',
            'offer_price' => 'decimal:2',
            'estimated_service_cost' => 'decimal:2',
            'max_purchase_price' => 'decimal:2',
            'recommended_price' => 'decimal:2',
            'expected_profit' => 'decimal:2',
            'expected_margin' => 'decimal:4',
        ];
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(PricePrediction::class, 'price_prediction_id');
    }

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }
}
