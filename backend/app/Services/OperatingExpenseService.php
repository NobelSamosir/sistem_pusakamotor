<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\OperatingExpense;
use Illuminate\Support\Facades\DB;

/** Beban operasional umum: Dr Beban Operasional / Cr Kas. */
class OperatingExpenseService
{
    public function __construct(private JournalService $journal) {}

    /** @param array $data expense_date, category, description?, amount */
    public function create(array $data, ?int $userId = null): OperatingExpense
    {
        if (! Money::of($data['amount'] ?? 0)->isPositive()) {
            throw new BusinessRuleException('Nominal beban harus lebih dari 0.');
        }

        return DB::transaction(function () use ($data, $userId) {
            $expense = OperatingExpense::create([
                'user_id' => $userId,
                'expense_date' => $data['expense_date'],
                'category' => $data['category'],
                'description' => $data['description'] ?? null,
                'amount' => Money::str(Money::of($data['amount'])),
            ]);

            $this->journal->post(
                $data['expense_date'],
                'Beban '.$expense->category.($expense->description ? ': '.$expense->description : ''),
                JournalEntry::REF_OPERATING_EXPENSE,
                $expense->id,
                [
                    ['account' => Account::BEBAN_OPERASIONAL, 'debit' => $expense->amount],
                    ['account' => Account::KAS, 'credit' => $expense->amount],
                ],
                $userId,
            );

            return $expense;
        });
    }
}
