<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\OperatingExpense;
use Illuminate\Support\Facades\DB;

/** Beban operasional umum: Dr Beban Operasional / Cr Kas (atau Cr Perlengkapan bila memakai stok bahan). */
class OperatingExpenseService
{
    public function __construct(private JournalService $journal, private SupplyService $supplies) {}

    /** @param array $data expense_date, category, description?, amount, source? (kas|perlengkapan) */
    public function create(array $data, ?int $userId = null): OperatingExpense
    {
        if (! Money::of($data['amount'] ?? 0)->isPositive()) {
            throw new BusinessRuleException('Nominal beban harus lebih dari 0.');
        }
        $source = ($data['source'] ?? 'kas') === 'perlengkapan' ? 'perlengkapan' : 'kas';
        $amount = Money::str(Money::of($data['amount']));
        if ($source === 'perlengkapan') {
            $this->supplies->ensureAvailable($amount);
        }

        return DB::transaction(function () use ($data, $userId, $source, $amount) {
            $expense = OperatingExpense::create([
                'user_id' => $userId,
                'expense_date' => $data['expense_date'],
                'category' => $data['category'],
                'description' => $data['description'] ?? null,
                'amount' => $amount,
                'source' => $source,
            ]);

            $this->journal->post(
                $data['expense_date'],
                'Beban '.$expense->category.($expense->description ? ': '.$expense->description : ''),
                JournalEntry::REF_OPERATING_EXPENSE,
                $expense->id,
                [
                    ['account' => Account::BEBAN_OPERASIONAL, 'debit' => $amount],
                    ['account' => $source === 'perlengkapan' ? Account::PERLENGKAPAN : Account::KAS, 'credit' => $amount],
                ],
                $userId,
            );

            return $expense;
        });
    }
}
