<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\SaleService;
use App\Services\PartyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Sale::with(['motorcycle:id,vehicle_code,frame_number,brand,model,year', 'customer:id,customer_name'])
                ->when($request->query('from'), fn ($q, $v) => $q->where('sale_date', '>=', $v))
                ->when($request->query('to'), fn ($q, $v) => $q->where('sale_date', '<=', $v))
                ->when($request->query('sale_type'), fn ($q, $v) => $q->where('sale_type', $v))
                ->when($request->query('disbursement_status'), fn ($q, $v) => $q->where('disbursement_status', $v))
                ->orderByDesc('sale_date')->orderByDesc('id')
                ->paginate($request->integer('per_page', 20))
        );
    }

    public function store(Request $request, SaleService $service, PartyResolver $parties): JsonResponse
    {
        $data = $request->validate([
            'motorcycle_id' => ['required', 'exists:motorcycles,id'],
            // customer_id dipakai bila pembeli sudah membayar uang muka; selain itu identitas diisi di form (buyer)
            'customer_id' => ['nullable', 'required_without:buyer', 'exists:customers,id'],
            'sale_date' => ['required', 'date'],
            'sale_price' => ['required', 'numeric', 'gt:0'],
            'sale_type' => ['nullable', 'in:tunai,kredit'],
            'leasing_company' => ['required_if:sale_type,kredit', 'nullable', 'string', 'max:100'],
            'down_payment' => ['required_if:sale_type,kredit', 'nullable', 'numeric', 'min:0'],
            'deposit_id' => ['nullable', 'exists:customer_deposits,id'],
            'payment_method' => ['nullable', 'in:tunai,transfer'],
            'notes' => ['nullable', 'string'],
        ] + PartyResolver::rules('buyer.', 'customers', false));

        $sale = DB::transaction(function () use ($data, $service, $parties, $request) {
            if (empty($data['customer_id'])) {
                $data['customer_id'] = $parties->customer($data['buyer'])->id;
            }

            return $service->create($data, $request->user()->id);
        });

        return response()->json($sale->load(['motorcycle', 'customer']), 201);
    }
}
