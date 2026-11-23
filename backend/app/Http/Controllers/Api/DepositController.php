<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerDeposit;
use App\Services\DepositService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Uang muka / tanda jadi pelanggan. */
class DepositController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            CustomerDeposit::with(['motorcycle:id,vehicle_code,brand,model,year,frame_number,status', 'customer:id,customer_name,phone'])
                ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
                ->when($request->query('motorcycle_id'), fn ($q, $v) => $q->where('motorcycle_id', $v))
                ->orderByRaw("CASE WHEN status = 'aktif' THEN 0 ELSE 1 END")
                ->orderByDesc('deposit_date')->orderByDesc('id')
                ->paginate($request->integer('per_page', 20))
        );
    }

    public function store(Request $request, DepositService $service): JsonResponse
    {
        $data = $request->validate([
            'motorcycle_id' => ['required', 'exists:motorcycles,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'deposit_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string'],
        ]);

        return response()->json($service->create($data, $request->user()->id)->load('motorcycle', 'customer'), 201);
    }

    public function refund(Request $request, CustomerDeposit $deposit, DepositService $service): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date']]);

        return response()->json($service->refund($deposit, $data['date'], $request->user()->id));
    }

    public function forfeit(Request $request, CustomerDeposit $deposit, DepositService $service): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date']]);

        return response()->json($service->forfeit($deposit, $data['date'], $request->user()->id));
    }
}
