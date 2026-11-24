<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Motorcycle;
use App\Models\PricePrediction;
use App\Models\PriceRecommendation;
use App\Services\Dss\DssService;
use App\Services\Dss\FeatureBuilder;
use App\Services\Dss\MlClient;
use App\Services\Dss\PredictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DssController extends Controller
{
    /** Isi dropdown form: kategori dari data latih + konteks showroom + PL default. */
    public function options(MlClient $ml): JsonResponse
    {
        return response()->json([
            'metadata' => $ml->metadata(),
            'showroom' => [
                'seller_type' => config('pusaka.seller_type'),
                'provinsi' => config('pusaka.provinsi'),
                'kota' => config('pusaka.kota'),
            ],
            'default_markup' => config('pusaka.default_markup'),
        ]);
    }

    /** Status layanan model, untuk indikator di frontend. */
    public function health(MlClient $ml): JsonResponse
    {
        $h = $ml->health();

        return response()->json(['online' => $h !== null, 'detail' => $h]);
    }

    /** Prediksi range saja (halaman Prediksi Harga). Kirim motorcycle_id ATAU karakteristik motor. */
    public function predict(Request $request, PredictionService $predictions, FeatureBuilder $features): JsonResponse
    {
        if ($request->filled('motorcycle_id')) {
            $m = Motorcycle::findOrFail($request->integer('motorcycle_id'));
            $p = $predictions->predict($features->fromMotorcycle($m), $m->id, $request->user()->id);
        } else {
            $data = $request->validate($this->motorRules());
            $p = $predictions->predict($features->fromInput($data), null, $request->user()->id);
        }

        return response()->json($p, 201);
    }

    public function predictions(Request $request): JsonResponse
    {
        return response()->json(
            PricePrediction::with('motorcycle:id,vehicle_code,brand,model,year')
                ->when($request->query('motorcycle_id'), fn ($q, $v) => $q->where('motorcycle_id', $v))
                ->latest('id')->paginate($request->integer('per_page', 20))
        );
    }

    /** DSS pembelian: karakteristik motor + PL + estimasi servis + harga tawaran penjual. */
    public function purchase(Request $request, DssService $dss): JsonResponse
    {
        $data = $request->validate($this->motorRules() + [
            'target_markup' => ['required', 'numeric', 'between:0,1'],
            // Perkiraan biaya servis/pajak yang dibayar showroom (bisa berubah)
            'estimated_service_cost' => ['required', 'numeric', 'min:0'],
            // Potongan yang disepakati dari uang penjual (boleh 0)
            'seller_deduction' => ['nullable', 'numeric', 'min:0', 'lt:offer_price'],
            'offer_price' => ['required', 'numeric', 'gt:0'],
        ]);

        $rec = $dss->purchase(
            $data,
            (string) $data['target_markup'],
            (string) $data['estimated_service_cost'],
            (string) $data['offer_price'],
            $request->user()->id,
            (string) ($data['seller_deduction'] ?? 0),
        );

        return response()->json($rec, 201);
    }

    /** DSS penjualan: cukup PL, karakteristik dan HPP diambil dari data unit. */
    public function sale(Request $request, Motorcycle $motorcycle, DssService $dss): JsonResponse
    {
        $data = $request->validate([
            'target_markup' => ['required', 'numeric', 'between:0,1'],
        ]);

        return response()->json($dss->sale($motorcycle, (string) $data['target_markup'], $request->user()->id), 201);
    }

    /** Riwayat rekomendasi. ?type=beli|jual&motorcycle_id= */
    public function recommendations(Request $request): JsonResponse
    {
        return response()->json(
            PriceRecommendation::with(['prediction', 'motorcycle:id,vehicle_code,brand,model,year'])
                ->when($request->query('type'), fn ($q, $v) => $q->where('recommendation_type', $v))
                ->when($request->query('motorcycle_id'), fn ($q, $v) => $q->where('motorcycle_id', $v))
                ->latest('id')->paginate($request->integer('per_page', 20))
        );
    }

    private function motorRules(): array
    {
        return [
            'brand' => ['required', 'string', 'max:50'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'between:1970,'.(date('Y') + 1)],
            'transmission' => ['required', 'string', 'max:30'],
            'engine_capacity' => ['required', 'string', 'max:30'],
            'body_type' => ['required', 'string', 'max:50'],
            'mileage' => ['required', 'integer', 'min:0'],
            'ownership_status' => ['required', 'string', 'max:30'],
            'has_bpkb' => ['boolean'],
            'has_stnk' => ['boolean'],
            'has_faktur' => ['boolean'],
            'has_buku_servis' => ['boolean'],
        ];
    }
}
