<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\DocumentService;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

/**
 * Jasa pengurusan surat. Showroom bertindak sebagai perantara (agen), sehingga
 * uang yang diteruskan ke Samsat bukan pendapatan; yang dijurnal hanya upah jasa:
 *   Dr Kas / Cr Pendapatan Lain-lain (sebesar upah).
 */
class DocumentServiceService
{
    public function __construct(private JournalService $journal) {}

    public function create(array $data, ?int $userId = null): DocumentService
    {
        $received = Money::of($data['amount_received'] ?? 0);
        $paid = Money::of($data['amount_paid'] ?? 0);
        $fee = $received->minus($paid);

        if ($paid->isNegative()) {
            throw new BusinessRuleException('Biaya yang disetor ke Samsat tidak boleh negatif.');
        }
        if (! $fee->isPositive()) {
            throw new BusinessRuleException('Uang dari pelanggan harus lebih besar dari biaya yang disetor, supaya ada upah jasa.');
        }

        return DB::transaction(function () use ($data, $userId, $received, $paid, $fee) {
            $s = DocumentService::create([
                'service_number' => DocumentNumber::next(DocumentService::class, 'service_number', 'JS', $data['service_date']),
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $userId,
                'service_date' => $data['service_date'],
                'service_type' => $data['service_type'],
                'license_plate' => $data['license_plate'] ?? null,
                'description' => $data['description'] ?? null,
                'amount_received' => Money::str($received),
                'amount_paid' => Money::str($paid),
                'fee' => Money::str($fee),
            ]);

            $label = [
                'pengantaran_pinjaman' => 'Komisi pengantaran pinjaman',
                'bonus_leasing' => 'Bonus tahunan leasing',
            ][$s->service_type] ?? 'Upah jasa '.str_replace('_', ' ', $s->service_type);
            $this->journal->post($s->service_date->toDateString(),
                $label.($s->license_plate ? " {$s->license_plate}" : '').($s->description ? " - {$s->description}" : ''),
                JournalEntry::REF_DOCUMENT_SERVICE, $s->id, [
                    ['account' => Account::KAS, 'debit' => $s->fee],
                    ['account' => Account::PENDAPATAN_LAIN, 'credit' => $s->fee],
                ], $userId);

            return $s->load('customer');
        });
    }

    public function update(DocumentService $service, array $data, ?int $userId = null): DocumentService
    {
        return DB::transaction(function () use ($service, $data, $userId) {
            $this->delete($service);

            return $this->create($data, $userId);
        });
    }

    public function delete(DocumentService $service): void
    {
        DB::transaction(function () use ($service) {
            $this->journal->deleteForReference(JournalEntry::REF_DOCUMENT_SERVICE, $service->id);
            $service->delete();
        });
    }
}
