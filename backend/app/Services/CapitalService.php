<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

/**
 * Transaksi pemilik:
 *  - setoran modal: Dr Kas / Cr Modal Pemilik
 *  - prive (pengambilan pribadi): Dr Prive Pemilik / Cr Kas
 */
class CapitalService
{
    public function __construct(private JournalService $journal) {}

    public function deposit(string $date, string|int|float $amount, string $description = 'Setoran modal pemilik', ?int $userId = null): JournalEntry
    {
        $value = Money::of($amount);
        if (! $value->isPositive()) {
            throw new BusinessRuleException('Setoran modal harus lebih dari 0.');
        }

        return $this->journal->post($date, $description, JournalEntry::REF_MANUAL, null, [
            ['account' => Account::KAS, 'debit' => Money::str($value)],
            ['account' => Account::MODAL, 'credit' => Money::str($value)],
        ], $userId);
    }

    public function withdraw(string $date, string|int|float $amount, string $description = 'Pengambilan pribadi pemilik (prive)', ?int $userId = null): JournalEntry
    {
        $value = Money::of($amount);
        if (! $value->isPositive()) {
            throw new BusinessRuleException('Nominal prive harus lebih dari 0.');
        }

        $kas = DB::table('journal_lines as l')->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('a.account_code', Account::KAS)->where('e.entry_date', '<=', $date)
            ->selectRaw('COALESCE(SUM(l.debit), 0) - COALESCE(SUM(l.credit), 0) as saldo')->value('saldo');
        if ($value->isGreaterThan(Money::of($kas ?? 0))) {
            throw new BusinessRuleException('Saldo kas tidak cukup untuk prive sebesar itu.');
        }

        return $this->journal->post($date, $description, JournalEntry::REF_PRIVE, null, [
            ['account' => Account::PRIVE, 'debit' => Money::str($value)],
            ['account' => Account::KAS, 'credit' => Money::str($value)],
        ], $userId);
    }
}
