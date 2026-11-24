<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplyPurchase;
use App\Services\Money;
use App\Services\SupplyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Perlengkapan: pembelian, saldo, dan riwayat pemakaian. */
class SupplyController extends Controller
{
    public function index(Request $request, SupplyService $supplies): JsonResponse
    {
        $usage = DB::query()->fromSub(
            DB::table('vehicle_costs as c')->join('motorcycles as m', 'm.id', '=', 'c.motorcycle_id')
                ->where('c.source', 'perlengkapan')
                ->selectRaw("c.cost_date as date, 'Motor ' || m.vehicle_code || ' ' || m.brand || ' ' || m.model || COALESCE(': ' || c.description, '') as description, c.amount")
                ->unionAll(
                    DB::table('operating_expenses')->where('source', 'perlengkapan')
                        ->selectRaw("expense_date as date, 'Umum: ' || category || COALESCE(' - ' || description, '') as description, amount")
                ),
            'u'
        )->orderByDesc('date')->limit(50)->get();

        return response()->json([
            'balance' => Money::str($supplies->balance()),
            'purchases' => SupplyPurchase::orderByDesc('purchase_date')->orderByDesc('id')->limit(50)->get(),
            'usage' => $usage,
        ]);
    }

    public function store(Request $request, SupplyService $supplies): JsonResponse
    {
        $data = $this->rules($request);

        return response()->json($supplies->purchase($data, $request->user()->id), 201);
    }

    public function update(Request $request, SupplyPurchase $supply, SupplyService $supplies): JsonResponse
    {
        return response()->json($supplies->update($supply, $this->rules($request), $request->user()->id));
    }

    public function destroy(SupplyPurchase $supply, SupplyService $supplies): JsonResponse
    {
        $supplies->delete($supply);

        return response()->json(['message' => 'Pembelian perlengkapan dan jurnalnya sudah dihapus.']);
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'purchase_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);
    }
}
