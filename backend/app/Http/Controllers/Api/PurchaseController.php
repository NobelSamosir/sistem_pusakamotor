<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Motorcycle;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    /** ?from=2026-11-01&to=2026-11-30 */
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Purchase::with(['motorcycle:id,vehicle_code,frame_number,brand,model,year,status', 'supplier:id,supplier_name'])
                ->when($request->query('from'), fn ($q, $v) => $q->where('purchase_date', '>=', $v))
                ->when($request->query('to'), fn ($q, $v) => $q->where('purchase_date', '<=', $v))
                ->orderByDesc('purchase_date')->orderByDesc('id')
                ->paginate($request->integer('per_page', 20))
        );
    }

    /**
     * Body JSON:
     * {
     *   "motorcycle": { frame_number, engine_number, brand, model, year, ... },
     *   "supplier_id": 1, "purchase_date": "2026-11-02", "purchase_price": 15000000,
     *   "payment_method": "tunai", "notes": null, "recommendation_id": null
     * }
     */
    public function store(Request $request, PurchaseService $service): JsonResponse
    {
        $request->merge(['motorcycle' => array_merge($request->input('motorcycle', []), [
            'frame_number' => Motorcycle::normalizeNumber($request->input('motorcycle.frame_number')),
            'engine_number' => Motorcycle::normalizeNumber($request->input('motorcycle.engine_number')),
        ])]);

        $data = $request->validate(MotorcycleRules::rules(null, 'motorcycle.') + [
            'motorcycle' => ['required', 'array'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_date' => ['required', 'date'],
            'purchase_price' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['nullable', 'in:tunai,transfer'],
            'notes' => ['nullable', 'string'],
            'recommendation_id' => ['nullable', 'exists:price_recommendations,id'],
        ]);

        $purchase = $service->create(
            $data['motorcycle'],
            [
                'supplier_id' => $data['supplier_id'],
                'purchase_date' => $data['purchase_date'],
                'purchase_price' => $data['purchase_price'],
                'payment_method' => $data['payment_method'] ?? 'tunai',
                'notes' => $data['notes'] ?? null,
            ],
            $request->user()->id,
            $data['recommendation_id'] ?? null,
        );

        return response()->json($purchase->load('supplier'), 201);
    }
}
