<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerDeposit;
use App\Services\DepositService;
use App\Services\PartyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Uang muka / tanda jadi pelanggan. */
class DepositController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            CustomerDeposit::with(['motorcycle:id,vehicle_code,brand,model,year,frame_number,status', 'customer:id,customer_name,phone'])
                ->when($request->query('customer_id'), fn ($q, $v) => $q->where('customer_id', $v))
                ->when($request->boolean('unlinked'), fn ($q) => $q->whereNull('motorcycle_id'))
                ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
                ->when($request->query('motorcycle_id'), fn ($q, $v) => $q->where('motorcycle_id', $v))
                ->orderByRaw("CASE WHEN status = 'aktif' THEN 0 ELSE 1 END")
                ->orderByDesc('deposit_date')->orderByDesc('id')
                ->paginate($request->integer('per_page', 20))
        );
    }

    public function store(Request $request, DepositService $service, PartyResolver $parties): JsonResponse
    {
        $data = $request->validate([
            'motorcycle_id' => ['required', 'exists:motorcycles,id'],
            'deposit_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string'],
        ] + PartyResolver::rules('buyer.', 'customers'));

        $deposit = DB::transaction(function () use ($data, $service, $parties, $request) {
            $data['customer_id'] = $parties->customer($data['buyer'])->id;

            return $service->create($data, $request->user()->id);
        });

        return response()->json($deposit->load('motorcycle', 'customer'), 201);
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

    /** Ubah uang muka yang masih aktif (tanggal, nominal, keterangan, identitas pembeli). */
    public function update(Request $request, CustomerDeposit $deposit, DepositService $service, PartyResolver $parties): JsonResponse
    {
        $data = $request->validate([
            'deposit_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string'],
        ] + PartyResolver::rules('buyer.', 'customers'));

        $updated = DB::transaction(function () use ($data, $deposit, $service, $parties, $request) {
            $data['customer_id'] = $parties->customer($data['buyer'])->id;

            return $service->update($deposit, $data, $request->user()->id);
        });

        return response()->json($updated->load('motorcycle', 'customer'));
    }

    public function destroy(CustomerDeposit $deposit, DepositService $service): JsonResponse
    {
        $service->delete($deposit);

        return response()->json(['message' => 'Uang muka dan jurnalnya sudah dihapus.']);
    }
}
