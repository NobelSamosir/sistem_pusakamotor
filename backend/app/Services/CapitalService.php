<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\JournalEntry;

/** Jurnal manual setoran modal: Dr Kas / Cr Modal Pemilik. */
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
}
