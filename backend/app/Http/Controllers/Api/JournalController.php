<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Services\CapitalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    /** Jurnal umum per periode beserta barisnya. */
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            JournalEntry::with('lines.account:id,account_code,account_name')
                ->when($request->query('from'), fn ($q, $v) => $q->where('entry_date', '>=', $v))
                ->when($request->query('to'), fn ($q, $v) => $q->where('entry_date', '<=', $v))
                ->orderBy('entry_date')->orderBy('id')
                ->paginate($request->integer('per_page', 50))
        );
    }

    /** Jurnal manual setoran modal: Dr Kas / Cr Modal Pemilik. */
    public function storeCapital(Request $request, CapitalService $service): JsonResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = $service->deposit(
            $data['entry_date'],
            $data['amount'],
            $data['description'] ?? 'Setoran modal pemilik',
            $request->user()->id,
        );

        return response()->json($entry->load('lines.account'), 201);
    }
}
