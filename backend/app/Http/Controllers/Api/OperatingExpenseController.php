<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OperatingExpense;
use App\Services\OperatingExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperatingExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            OperatingExpense::query()
                ->when($request->query('from'), fn ($q, $v) => $q->where('expense_date', '>=', $v))
                ->when($request->query('to'), fn ($q, $v) => $q->where('expense_date', '<=', $v))
                ->orderByDesc('expense_date')->orderByDesc('id')
                ->paginate($request->integer('per_page', 20))
        );
    }

    public function store(Request $request, OperatingExpenseService $service): JsonResponse
    {
        $data = $request->validate([
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'in:listrik,gaji,sewa,iklan,kebersihan,lainnya'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'source' => ['nullable', 'in:kas,perlengkapan'],
        ]);

        return response()->json($service->create($data, $request->user()->id), 201);
    }
}
