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
        return response()->json($this->save($request, $service, $parties), 201);
    }

    public function update(Request $request, DocumentService $documentService, DocumentServiceService $service, PartyResolver $parties): JsonResponse
    {
        return response()->json($this->save($request, $service, $parties, $documentService));
    }

    public function destroy(DocumentService $documentService, DocumentServiceService $service): JsonResponse
    {
        $service->delete($documentService);

        return response()->json(['message' => 'Pendapatan lain dan jurnalnya sudah dihapus.']);
    }

    /** Jenis pendapatan cukup ditulis di keterangan; biaya yang diteruskan (mis. ke Samsat) boleh 0. */
    private function save(Request $request, DocumentServiceService $service, PartyResolver $parties, ?DocumentService $old = null): DocumentService
    {
        $data = $request->validate([
            'service_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'service_type' => ['nullable', Rule::in(DocumentService::TYPES)],
            'amount_received' => ['required', 'numeric', 'gt:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
        ] + PartyResolver::rules('customer.', 'customers', false));
        $data['service_type'] = $data['service_type'] ?? 'lainnya';
        $data['amount_paid'] = $data['amount_paid'] ?? 0;

        return DB::transaction(function () use ($data, $service, $parties, $request, $old) {
            if (! empty($data['customer']['name'])) {
                $data['customer_id'] = $parties->customer($data['customer'])->id;
            }

            return $old ? $service->update($old, $data, $request->user()->id) : $service->create($data, $request->user()->id);
        });
    }
}
