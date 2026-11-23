<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consignment;
use App\Models\Motorcycle;
use App\Services\ConsignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Titip jual (konsinyasi). */
class ConsignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Consignment::with(['motorcycle:id,vehicle_code,brand,model,year,frame_number,status', 'motorcycle.sale:id,motorcycle_id,sale_price,sale_date', 'consignor:id,supplier_name,phone'])
                ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
                ->orderByRaw("CASE status WHEN 'terjual' THEN 0 WHEN 'dititipkan' THEN 1 ELSE 2 END")
                ->orderByDesc('received_date')
                ->paginate($request->integer('per_page', 20))
        );
    }

    /** Body: { motorcycle: {...}, consignor_id, received_date, consignor_price, notes } */
    public function store(Request $request, ConsignmentService $service): JsonResponse
    {
        $request->merge(['motorcycle' => array_merge($request->input('motorcycle', []), [
            'frame_number' => Motorcycle::normalizeNumber($request->input('motorcycle.frame_number')),
            'engine_number' => Motorcycle::normalizeNumber($request->input('motorcycle.engine_number')),
        ])]);

        $data = $request->validate(MotorcycleRules::rules(null, 'motorcycle.') + [
            'motorcycle' => ['required', 'array'],
            'consignor_id' => ['required', 'exists:suppliers,id'],
            'received_date' => ['required', 'date'],
            'consignor_price' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $c = $service->receive($data['motorcycle'], $data, $request->user()->id);

        return response()->json($c, 201);
    }

    public function returnToOwner(Request $request, Consignment $consignment, ConsignmentService $service): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date']]);

        return response()->json($service->returnToOwner($consignment, $data['date']));
    }

    public function payout(Request $request, Consignment $consignment, ConsignmentService $service): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date']]);

        return response()->json($service->payout($consignment, $data['date'], $request->user()->id));
    }
}
