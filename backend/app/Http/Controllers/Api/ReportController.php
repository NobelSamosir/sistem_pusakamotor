<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function stock(): JsonResponse
    {
        return response()->json($this->reports->stock());
    }

    public function purchasesSales(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return response()->json($this->reports->purchasesSales($from, $to));
    }

    public function incomeStatement(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return response()->json($this->reports->incomeStatement($from, $to));
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['nullable', 'date']]);

        return response()->json($this->reports->balanceSheet($data['date'] ?? now()->toDateString()));
    }

    public function cashFlow(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return response()->json($this->reports->cashFlow($from, $to));
    }

    /** Periode default: bulan berjalan. */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            $data['from'] ?? now()->startOfMonth()->toDateString(),
            $data['to'] ?? now()->endOfMonth()->toDateString(),
        ];
    }
}
