<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\SupplyPurchase;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Perlengkapan = bahan habis pakai (cat, dempul, amplas, oli, kain poles).
 *  - dibeli:  Dr Perlengkapan / Cr Kas
 *  - dipakai untuk motor tertentu: lewat Biaya Kendaraan sumber "perlengkapan"
 *            (Dr Persediaan Kendaraan / Cr Perlengkapan) sehingga masuk HPP unit
 *  - dipakai untuk keperluan umum: lewat Beban Operasional sumber "perlengkapan"
 */
class SupplyService
{
    public function __construct(private JournalService $journal) {}

    public function purchase(array $data, ?int $userId = null): SupplyPurchase
    {
        if (! Money::of($data['amount'] ?? 0)->isPositive()) {
            throw new BusinessRuleException('Nominal pembelian perlengkapan harus lebih dari 0.');
        }

        return DB::transaction(function () use ($data, $userId) {
            $p = SupplyPurchase::create([
                'user_id' => $userId,
                'purchase_date' => $data['purchase_date'],
                'description' => $data['description'],
                'vendor' => $data['vendor'] ?? null,
                'amount' => Money::str(Money::of($data['amount'])),
            ]);

            $this->journal->post($data['purchase_date'], "Pembelian perlengkapan: {$p->description}",
                JournalEntry::REF_SUPPLY_PURCHASE, $p->id, [
                    ['account' => Account::PERLENGKAPAN, 'debit' => $p->amount],
                    ['account' => Account::KAS, 'credit' => $p->amount],
                ], $userId);

            return $p;
        });
    }

    /** Saldo perlengkapan saat ini (nilai rupiah bahan yang belum dipakai). */
    public function balance(): BigDecimal
    {
        $row = DB::table('journal_lines as l')->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('a.account_code', Account::PERLENGKAPAN)
            ->selectRaw('COALESCE(SUM(l.debit), 0) - COALESCE(SUM(l.credit), 0) as saldo')->first();

        return Money::of($row->saldo ?? 0);
    }

    /** Pastikan pemakaian tidak melebihi saldo perlengkapan. */
    public function ensureAvailable(string $amount): void
    {
        $saldo = $this->balance();
        if (Money::of($amount)->isGreaterThan($saldo)) {
            throw new BusinessRuleException('Saldo perlengkapan tidak cukup (tersisa Rp'.number_format((float) (string) $saldo, 0, ',', '.').'). Catat pembelian perlengkapan terlebih dahulu.');
        }
    }

    /** Ubah pembelian perlengkapan. */
    public function update(SupplyPurchase $purchase, array $data, ?int $userId = null): SupplyPurchase
    {
        return DB::transaction(function () use ($purchase, $data, $userId) {
            $this->delete($purchase, Money::of($data['amount'] ?? 0));

            return $this->purchase($data, $userId);
        });
    }

    /** Hapus pembelian perlengkapan, asal stok yang sudah terpakai tidak menjadi minus. */
    public function delete(SupplyPurchase $purchase, ?BigDecimal $replacement = null): void
    {
        $after = $this->balance()->minus(Money::of($purchase->amount))->plus($replacement ?? Money::of(0));
        if ($after->isNegative()) {
            throw new BusinessRuleException('Perlengkapan dari pembelian ini sudah terpakai, jadi saldo akan menjadi minus. Hapus atau ubah dulu pemakaiannya di Biaya Kendaraan atau Beban Operasional.');
        }
        DB::transaction(function () use ($purchase) {
            $this->journal->deleteForReference(JournalEntry::REF_SUPPLY_PURCHASE, $purchase->id);
            $purchase->delete();
        });
    }
}
