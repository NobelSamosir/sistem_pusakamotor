<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VehicleCost;
use App\Services\VehicleCostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleCostController extends Controller
{
    /** ?motorcycle_id=1&from=&to= */
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            VehicleCost::with('motorcycle:id,vehicle_code,frame_number,brand,model,year')
                ->when($request->query('motorcycle_id'), fn ($q, $v) => $q->where('motorcycle_id', $v))
                ->when($request->query('from'), fn ($q, $v) => $q->where('cost_date', '>=', $v))
                ->when($request->query('to'), fn ($q, $v) => $q->where('cost_date', '<=', $v))
                ->orderByDesc('cost_date')->orderByDesc('id')
                ->paginate($request->integer('per_page', 20))
        );
    }

    public function store(Request $request, VehicleCostService $service): JsonResponse
    {
        $data = $request->validate([
            'motorcycle_id' => ['required', 'exists:motorcycles,id'],
            'cost_date' => ['required', 'date'],
            'cost_category' => ['required', 'in:servis,sparepart,cat_body,administrasi,lainnya'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'source' => ['nullable', 'in:kas,perlengkapan'],
            'hpp_included' => ['required', 'boolean'],
        ]);

        return response()->json($service->create($data, $request->user()->id), 201);
    }
}
