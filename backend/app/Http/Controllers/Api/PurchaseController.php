<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Motorcycle;
use App\Models\Purchase;
use App\Services\PurchaseService;
use App\Services\PartyResolver;
use Illuminate\Support\Facades\DB;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    /** ?from=2026-11-01&to=2026-11-30 */
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Purchase::with(['motorcycle:id,vehicle_code,frame_number,brand,model,year,status', 'supplier:id,supplier_name,phone,id_number,address'])
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
     *   "seller": { name, phone?, address?, id_number? (NIK), type?, id? }, "purchase_date": "2026-11-02", "purchase_price": 15000000,
     *   "payment_method": "tunai", "notes": null, "recommendation_id": null
     * }
     */
    public function store(Request $request, PurchaseService $service, PartyResolver $parties): JsonResponse
    {
        $request->merge(['motorcycle' => array_merge($request->input('motorcycle', []), [
            'frame_number' => Motorcycle::normalizeNumber($request->input('motorcycle.frame_number')),
            'engine_number' => Motorcycle::normalizeNumber($request->input('motorcycle.engine_number')),
        ])]);

        $data = $request->validate(MotorcycleRules::rules(null, 'motorcycle.') + [
            'motorcycle' => ['required', 'array'],
            'purchase_date' => ['required', 'date'],
            'purchase_price' => ['required', 'numeric', 'gt:0'],
            'agreed_price' => ['nullable', 'numeric', 'gte:purchase_price'],
            'deduction_notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['nullable', 'in:tunai,transfer'],
            'notes' => ['nullable', 'string'],
            'recommendation_id' => ['nullable', 'exists:price_recommendations,id'],
        ] + PartyResolver::rules('seller.', 'suppliers'));

        // Identitas penjual diisi di form ini; dicatat dalam satu transaksi dengan pembeliannya
        $purchase = DB::transaction(fn () => $service->create(

            $data['motorcycle'],
            [
                'supplier_id' => $parties->supplier($data['seller'])->id,
                'purchase_date' => $data['purchase_date'],
                'purchase_price' => $data['purchase_price'],
                'agreed_price' => $data['agreed_price'] ?? null,
                'deduction_notes' => $data['deduction_notes'] ?? null,
                'payment_method' => $data['payment_method'] ?? 'tunai',
                'notes' => $data['notes'] ?? null,
            ],
            $request->user()->id,
            $data['recommendation_id'] ?? null,
        ));

        return response()->json($purchase->load('supplier'), 201);
    }

    /** Ubah data pembelian: penjual, tanggal, harga kesepakatan, uang yang dibayar, potongan, catatan. */
    public function update(Request $request, Purchase $purchase, PurchaseService $service, PartyResolver $parties): JsonResponse
    {
        $data = $request->validate([
            'purchase_date' => ['required', 'date'],
            'purchase_price' => ['required', 'numeric', 'gt:0'],
            'agreed_price' => ['nullable', 'numeric', 'gte:purchase_price'],
            'deduction_notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['nullable', 'in:tunai,transfer'],
            'notes' => ['nullable', 'string'],
        ] + PartyResolver::rules('seller.', 'suppliers'));

        $updated = DB::transaction(fn () => $service->update($purchase, [
            'supplier_id' => $parties->supplier($data['seller'])->id,
        ] + $data, $request->user()->id));

        return response()->json($updated);
    }

    public function destroy(Purchase $purchase, PurchaseService $service): JsonResponse
    {
        $service->delete($purchase);

        return response()->json(['message' => 'Pembelian, data motor, dan jurnalnya sudah dihapus.']);
    }
}
