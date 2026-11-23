<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\LeasingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Penjualan kredit: daftar piutang leasing, pencairan dana, komisi leasing. */
class LeasingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Sale::with(['motorcycle:id,vehicle_code,brand,model,year,frame_number', 'customer:id,customer_name'])
                ->where('sale_type', Sale::KREDIT)
                ->when($request->query('status'), fn ($q, $v) => $q->where('disbursement_status', $v))
                ->orderByRaw("CASE WHEN disbursement_status = 'menunggu' THEN 0 ELSE 1 END")
                ->orderByDesc('sale_date')
                ->paginate($request->integer('per_page', 20))
        );
    }

    public function disburse(Request $request, Sale $sale, LeasingService $leasing): JsonResponse
    {
        $data = $request->validate(['disbursed_at' => ['required', 'date', 'after_or_equal:'.$sale->sale_date->toDateString()]]);

        return response()->json($leasing->disburse($sale, $data['disbursed_at'], $request->user()->id));
    }

    public function commission(Request $request, Sale $sale, LeasingService $leasing): JsonResponse
    {
        $data = $request->validate([
            'received_at' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        return response()->json($leasing->recordCommission($sale, $data['received_at'], $data['amount'], $request->user()->id));
    }
}
