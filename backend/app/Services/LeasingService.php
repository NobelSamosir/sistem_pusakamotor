<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

/**
 * Penjualan kredit lewat perusahaan pembiayaan:
 *  - pencairan dana:  Dr Kas / Cr Piutang Perusahaan Pembiayaan
 *  - komisi leasing:  Dr Kas / Cr Pendapatan Komisi Leasing
 */
class LeasingService
{
    public function __construct(private JournalService $journal) {}

    public function disburse(Sale $sale, string $date, ?int $userId = null): Sale
    {
        return DB::transaction(function () use ($sale, $date, $userId) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            if ($sale->sale_type !== Sale::KREDIT) {
                throw new BusinessRuleException('Penjualan ini bukan penjualan kredit.');
            }
            if ($sale->disbursement_status === 'cair') {
                throw new BusinessRuleException('Dana dari leasing untuk penjualan ini sudah dicatat cair.');
            }

            $this->journal->post($date, "Pencairan dana {$sale->leasing_company} untuk {$sale->sale_number}",
                JournalEntry::REF_LEASING_DISBURSEMENT, $sale->id, [
                    ['account' => Account::KAS, 'debit' => $sale->financed_amount],
                    ['account' => Account::PIUTANG_LEASING, 'credit' => $sale->financed_amount],
                ], $userId);

            $sale->update(['disbursement_status' => 'cair', 'disbursed_at' => $date]);

            return $sale;
        });
    }

    public function recordCommission(Sale $sale, string $date, string|int|float $amount, ?int $userId = null): Sale
    {
        $value = Money::of($amount);
        if (! $value->isPositive()) {
            throw new BusinessRuleException('Komisi leasing harus lebih dari 0.');
        }

        return DB::transaction(function () use ($sale, $date, $value, $userId) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            if ($sale->sale_type !== Sale::KREDIT) {
                throw new BusinessRuleException('Komisi leasing hanya untuk penjualan kredit.');
            }
            if ($sale->commission_received_at) {
                throw new BusinessRuleException('Komisi leasing untuk penjualan ini sudah dicatat.');
            }

            $this->journal->post($date, "Komisi {$sale->leasing_company} atas {$sale->sale_number}",
                JournalEntry::REF_LEASING_COMMISSION, $sale->id, [
                    ['account' => Account::KAS, 'debit' => Money::str($value)],
                    ['account' => Account::KOMISI_LEASING, 'credit' => Money::str($value)],
                ], $userId);

            $sale->update(['leasing_commission' => Money::str($value), 'commission_received_at' => $date]);

            return $sale;
        });
    }
}
