<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = $request->query('q');

        return response()->json(
            Supplier::when($q, fn ($s) => $s->where('supplier_name', 'ilike', "%{$q}%"))
                ->orderBy('supplier_name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Supplier::create($this->validated($request)), 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json($supplier);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($this->validated($request));

        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        if ($supplier->purchases()->exists()) {
            return response()->json(['message' => 'Pemasok sudah memiliki transaksi pembelian, tidak bisa dihapus.'], 422);
        }
        $supplier->delete();

        return response()->json(['message' => 'Pemasok dihapus.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'supplier_name' => ['required', 'string', 'max:150'],
            'supplier_type' => ['nullable', 'in:perorangan,dealer'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
        ]);
    }
}
