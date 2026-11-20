<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\JournalEntry;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu pembuatan jurnal.
 * Setiap jurnal wajib: minimal dua baris, tiap baris hanya debit ATAU kredit,
 * total debit = total kredit. Jika tidak, transaksi dibatalkan.
 */
class JournalService
{
    /**
     * @param array<int, array{account: string, debit?: string|int|float, credit?: string|int|float, memo?: string}> $lines
     *        'account' berisi kode akun Tabel 3.8, contoh Account::KAS
     */
    public function post(
        string $date,
        string $description,
        string $referenceType,
        ?int $referenceId,
        array $lines,
        ?int $userId = null,
    ): JournalEntry {
        if (count($lines) < 2) {
            throw new BusinessRuleException('Jurnal minimal terdiri dari dua baris.');
        }

        $totalDebit = BigDecimal::zero();
        $totalCredit = BigDecimal::zero();
        $prepared = [];

        foreach ($lines as $line) {
            $debit = Money::of($line['debit'] ?? 0);
            $credit = Money::of($line['credit'] ?? 0);

            if ($debit->isNegative() || $credit->isNegative()) {
                throw new BusinessRuleException('Nilai debit/kredit tidak boleh negatif.');
            }
            if ($debit->isPositive() === $credit->isPositive()) {
                throw new BusinessRuleException('Setiap baris jurnal harus berisi debit ATAU kredit.');
            }

            $accountId = Account::where('account_code', $line['account'])->value('id');
            if (! $accountId) {
                throw new BusinessRuleException("Akun {$line['account']} tidak ditemukan.");
            }

            $totalDebit = $totalDebit->plus($debit);
            $totalCredit = $totalCredit->plus($credit);
            $prepared[] = [
                'account_id' => $accountId,
                'debit' => Money::str($debit),
                'credit' => Money::str($credit),
                'memo' => $line['memo'] ?? null,
            ];
        }

        if (! $totalDebit->isEqualTo($totalCredit)) {
            throw new BusinessRuleException("Jurnal tidak seimbang: debit {$totalDebit}, kredit {$totalCredit}.");
        }

        return DB::transaction(function () use ($date, $description, $referenceType, $referenceId, $userId, $prepared) {
            $entry = JournalEntry::create([
                'journal_number' => DocumentNumber::next(JournalEntry::class, 'journal_number', 'JU', $date),
                'entry_date' => $date,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'user_id' => $userId,
            ]);
            $entry->lines()->createMany($prepared);

            return $entry->load('lines');
        });
    }

    /** Hapus semua jurnal milik satu transaksi (dipakai saat transaksi diedit lalu dijurnal ulang). */
    public function deleteForReference(string $referenceType, int $referenceId): void
    {
        JournalEntry::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->get()
            ->each->delete();
    }
}
