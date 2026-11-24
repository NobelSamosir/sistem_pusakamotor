<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentService;
use App\Services\DocumentServiceService;
use App\Services\PartyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Pendapatan lain-lain: jasa pengurusan surat, pengantaran pinjaman, bonus leasing. */
class DocumentServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            DocumentService::with('customer:id,customer_name')
                ->when($request->query('service_type'), fn ($q, $v) => $q->where('service_type', $v))
                ->when($request->query('from'), fn ($q, $v) => $q->where('service_date', '>=', $v))
                ->when($request->query('to'), fn ($q, $v) => $q->where('service_date', '<=', $v))
                ->orderByDesc('service_date')->orderByDesc('id')
                ->paginate($request->integer('per_page', 20))
        );
    }

    public function store(Request $request, DocumentServiceService $service, PartyResolver $parties): JsonResponse
    {
        $data = $request->validate([
            'service_date' => ['required', 'date'],
            'service_type' => ['required', Rule::in(DocumentService::TYPES)],
            'license_plate' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount_received' => ['required', 'numeric', 'gt:0'],
            'amount_paid' => ['required', 'numeric', 'min:0'],
        ] + PartyResolver::rules('customer.', 'customers', false));

        return response()->json(DB::transaction(function () use ($data, $service, $parties, $request) {
            // Nama pelanggan opsional; bila diisi, dicatat sebagai pelanggan
            if (! empty($data['customer']['name'])) {
                $data['customer_id'] = $parties->customer($data['customer'])->id;
            }

            return $service->create($data, $request->user()->id);
        }), 201);
    }
}
