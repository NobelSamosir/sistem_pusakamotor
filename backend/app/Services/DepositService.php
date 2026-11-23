<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\CustomerDeposit;
use App\Models\JournalEntry;
use App\Models\Motorcycle;
use Illuminate\Support\Facades\DB;

/**
 * Uang muka / tanda jadi pelanggan (booking).
 *  - diterima:     Dr Kas / Cr Uang Muka Pelanggan       (kewajiban, motor belum diserahkan)
 *  - dipakai:      otomatis saat penjualan (SaleService)
 *  - dikembalikan: Dr Uang Muka Pelanggan / Cr Kas
 *  - hangus:       Dr Uang Muka Pelanggan / Cr Pendapatan Lain-lain (pembeli batal)
 */
class DepositService
{
    public function __construct(private JournalService $journal) {}

    public function create(array $data, ?int $userId = null): CustomerDeposit
    {
        $amount = Money::of($data['amount'] ?? 0);
        if (! $amount->isPositive()) {
            throw new BusinessRuleException('Uang muka harus lebih dari 0.');
        }

        return DB::transaction(function () use ($data, $amount, $userId) {
            $m = Motorcycle::whereKey($data['motorcycle_id'])->lockForUpdate()->firstOrFail();
            if (! in_array($m->status, [Motorcycle::STATUS_TERSEDIA, Motorcycle::STATUS_PERBAIKAN], true)) {
                throw new BusinessRuleException("Motor tidak bisa dipesan (status: {$m->status}).");
            }

            $date = $data['deposit_date'];
            $deposit = CustomerDeposit::create([
                'deposit_number' => DocumentNumber::next(CustomerDeposit::class, 'deposit_number', 'UM', $date),
                'motorcycle_id' => $m->id,
                'customer_id' => $data['customer_id'],
                'user_id' => $userId,
                'deposit_date' => $date,
                'amount' => Money::str($amount),
                'status' => CustomerDeposit::AKTIF,
                'notes' => $data['notes'] ?? null,
            ]);
            $m->update(['status' => Motorcycle::STATUS_DIPESAN]);

            $this->journal->post($date, "Uang muka {$deposit->deposit_number} untuk {$m->brand} {$m->model} ({$m->frame_number})",
                JournalEntry::REF_DEPOSIT, $deposit->id, [
                    ['account' => Account::KAS, 'debit' => $deposit->amount],
                    ['account' => Account::UANG_MUKA, 'credit' => $deposit->amount],
                ], $userId);

            return $deposit;
        });
    }

    /** Pembeli batal dan uang muka dikembalikan. */
    public function refund(CustomerDeposit $deposit, string $date, ?int $userId = null): CustomerDeposit
    {
        return $this->close($deposit, $date, CustomerDeposit::DIKEMBALIKAN, Account::KAS, 'Pengembalian uang muka', $userId);
    }

    /** Pembeli batal dan uang muka hangus (menjadi pendapatan showroom). */
    public function forfeit(CustomerDeposit $deposit, string $date, ?int $userId = null): CustomerDeposit
    {
        return $this->close($deposit, $date, CustomerDeposit::HANGUS, Account::PENDAPATAN_LAIN, 'Uang muka hangus', $userId);
    }

    private function close(CustomerDeposit $deposit, string $date, string $status, string $creditAccount, string $label, ?int $userId): CustomerDeposit
    {
        return DB::transaction(function () use ($deposit, $date, $status, $creditAccount, $label, $userId) {
            $deposit = CustomerDeposit::whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if ($deposit->status !== CustomerDeposit::AKTIF) {
                throw new BusinessRuleException("Uang muka ini sudah tidak aktif (status: {$deposit->status}).");
            }

            $this->journal->post($date, "{$label} {$deposit->deposit_number}", JournalEntry::REF_DEPOSIT, $deposit->id, [
                ['account' => Account::UANG_MUKA, 'debit' => $deposit->amount],
                ['account' => $creditAccount, 'credit' => $deposit->amount],
            ], $userId);

            $deposit->update(['status' => $status, 'closed_at' => $date]);
            Motorcycle::whereKey($deposit->motorcycle_id)->where('status', Motorcycle::STATUS_DIPESAN)
                ->update(['status' => Motorcycle::STATUS_TERSEDIA]);

            return $deposit;
        });
    }
}
