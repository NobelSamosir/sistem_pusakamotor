<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Motorcycle;
use App\Services\HppService;
use App\Services\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MotorcycleController extends Controller
{
    /** Daftar unit. Filter: ?status=tersedia&brand=Honda&q=MH1 */
    public function index(Request $request): JsonResponse
    {
        $q = $request->query('q');

        $items = Motorcycle::with(['purchase:id,motorcycle_id,purchase_date,purchase_price'])
            ->when($request->query('status'), fn ($s, $v) => $s->where('status', $v))
            ->when($request->query('brand'), fn ($s, $v) => $s->where('brand', $v))
            ->when($q, fn ($s) => $s->where(fn ($w) => $w
                ->where('frame_number', 'ilike', "%{$q}%")
                ->orWhere('engine_number', 'ilike', "%{$q}%")
                ->orWhere('vehicle_code', 'ilike', "%{$q}%")
                ->orWhere('model', 'ilike', "%{$q}%")
                ->orWhere('license_plate', 'ilike', "%{$q}%")))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json($items);
    }

    /** Kartu unit: identitas, pembelian, biaya, HPP berjalan, penjualan, riwayat prediksi. */
    public function show(Motorcycle $motorcycle, HppService $hpp): JsonResponse
    {
        $motorcycle->load([
            'purchase.supplier',
            'costs' => fn ($q) => $q->orderBy('cost_date'),
            'sale.customer',
            'predictions' => fn ($q) => $q->latest()->limit(10),
            'recommendations' => fn ($q) => $q->latest()->limit(10),
        ]);

        $current = $hpp->forMotorcycle($motorcycle);

        return response()->json($motorcycle->toArray() + [
            'hpp_current' => Money::str($current),
        ]);
    }

    /** Ubah data unit (hanya unit yang belum terjual). */
    public function update(Request $request, Motorcycle $motorcycle): JsonResponse
    {
        if ($motorcycle->isSold()) {
            throw new BusinessRuleException('Data motor yang sudah terjual tidak bisa diubah.');
        }

        foreach (['frame_number', 'engine_number'] as $field) {
            if ($request->has($field)) {
                $request->merge([$field => Motorcycle::normalizeNumber($request->input($field))]);
            }
        }

        // Saat ubah data, setiap kolom opsional ('sometimes'): kirim hanya yang berubah
        $rules = array_map(fn ($r) => array_merge(['sometimes'], $r), MotorcycleRules::rules($motorcycle->id));

        $data = $request->validate($rules + [
            'status' => ['sometimes', Rule::in([Motorcycle::STATUS_TERSEDIA, Motorcycle::STATUS_PERBAIKAN])],
        ]);

        $motorcycle->update($data);

        return response()->json($motorcycle->fresh());
    }
}
