<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = $request->query('q');

        return response()->json(
            Customer::when($q, fn ($s) => $s->where('customer_name', 'ilike', "%{$q}%"))
                ->orderBy('customer_name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Customer::create($this->validated($request)), 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json($customer);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $customer->update($this->validated($request));

        return response()->json($customer);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        if ($customer->sales()->exists()) {
            return response()->json(['message' => 'Pelanggan sudah memiliki transaksi penjualan, tidak bisa dihapus.'], 422);
        }
        $customer->delete();

        return response()->json(['message' => 'Pelanggan dihapus.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_type' => ['nullable', 'in:perorangan,dealer'],
            'phone' => ['nullable', 'string', 'max:30'],
            'id_number' => ['nullable', 'digits:16'],
            'address' => ['nullable', 'string'],
        ]);
    }
}
