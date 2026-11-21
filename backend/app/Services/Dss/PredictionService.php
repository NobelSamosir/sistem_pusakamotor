<?php

namespace App\Services\Dss;

use App\Exceptions\BusinessRuleException;
use App\Models\MlModel;
use App\Models\PricePrediction;
use App\Services\Money;

/** Memanggil model lalu menyimpan setiap prediksi ke price_predictions. */
class PredictionService
{
    public function __construct(private MlClient $ml) {}

    public function predict(array $features, ?int $motorcycleId = null, ?int $userId = null): PricePrediction
    {
        $model = MlModel::active();
        if (! $model) {
            throw new BusinessRuleException('Belum ada model aktif di tabel ml_models.');
        }

        $r = $this->ml->predict($features);

        return PricePrediction::create([
            'motorcycle_id' => $motorcycleId,
            'ml_model_id' => $model->id,
            'user_id' => $userId,
            'input_snapshot' => $features,
            'predicted_price' => Money::str(Money::of($r['predicted_price'])),
            'lower_bound' => Money::str(Money::of($r['lower_bound'])),
            'upper_bound' => Money::str(Money::of($r['upper_bound'])),
            'warnings' => $r['warnings'] ?? [],
        ]);
    }
}
