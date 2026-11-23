<?php

namespace App\Services\Dss;

use App\Exceptions\BusinessRuleException;
use App\Models\Motorcycle;
use App\Models\PriceRecommendation;
use App\Services\HppService;
use App\Services\Money;
use Illuminate\Support\Facades\DB;

/**
 * Integrasi model ke DSS (RM 3):
 * fitur motor -> prediksi range [L ; U] -> aturan Tabel 3.10 -> rekomendasi tersimpan.
 */
class DssService
{
    public function __construct(
        private FeatureBuilder $features,
        private PredictionService $predictions,
        private HppService $hpp,
    ) {}

    /**
     * DSS pembelian (motor belum tercatat).
     *
     * @param array $motor  karakteristik motor (lihat FeatureBuilder::fromInput)
     */
    public function purchase(array $motor, string $markup, string $serviceCost, string $offer, ?int $userId = null): PriceRecommendation
    {
        return DB::transaction(function () use ($motor, $markup, $serviceCost, $offer, $userId) {
            $prediction = $this->predictions->predict($this->features->fromInput($motor), null, $userId);
            $r = DssRules::purchase($prediction->lower_bound, $markup, $serviceCost, $offer);

            return PriceRecommendation::create([
                'recommendation_type' => 'beli',
                'motorcycle_id' => null,
                'price_prediction_id' => $prediction->id,
                'user_id' => $userId,
                'target_markup' => $markup,
                'offer_price' => Money::str(Money::of($offer)),
                'estimated_service_cost' => Money::str(Money::of($serviceCost)),
                'max_purchase_price' => $r['max_purchase_price'],
                'decision_status' => $r['decision_status'],
                'recommended_price' => $r['recommended_price'],
                'expected_profit' => $r['expected_profit'],
                'expected_margin' => $r['expected_margin'],
                'notes' => $this->notes($prediction->warnings),
            ])->load('prediction');
        });
    }

    /** DSS penjualan (motor sudah di stok, HPP dari SIA). */
    public function sale(Motorcycle $motorcycle, string $markup, ?int $userId = null): PriceRecommendation
    {
        if (! $motorcycle->isInShowroom()) {
            throw new BusinessRuleException("Rekomendasi harga jual hanya untuk motor yang masih di showroom (status: {$motorcycle->status}).");
        }

        return DB::transaction(function () use ($motorcycle, $markup, $userId) {
            $hpp = Money::str($this->hpp->forMotorcycle($motorcycle));
            $prediction = $this->predictions->predict($this->features->fromMotorcycle($motorcycle), $motorcycle->id, $userId);
            $r = DssRules::sale($prediction->lower_bound, $prediction->upper_bound, $hpp, $markup);

            return PriceRecommendation::create([
                'recommendation_type' => 'jual',
                'motorcycle_id' => $motorcycle->id,
                'price_prediction_id' => $prediction->id,
                'user_id' => $userId,
                'target_markup' => $markup,
                'hpp' => $hpp,
                'target_price' => $r['target_price'],
                'decision_status' => $r['decision_status'],
                'recommended_price' => $r['recommended_price'],
                'expected_profit' => $r['expected_profit'],
                'expected_margin' => $r['expected_margin'],
                'notes' => $this->notes($prediction->warnings),
            ])->load('prediction');
        });
    }

    private function notes(?array $warnings): ?string
    {
        if (! $warnings) {
            return null;
        }
        $baru = collect($warnings)->contains(fn ($w) => str_starts_with($w['kode'] ?? '', 'kategori_baru'));

        return ($baru ? 'Perlu cek manual. ' : '').collect($warnings)->pluck('pesan')->implode(' ');
    }
}
