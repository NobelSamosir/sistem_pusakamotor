<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Motorcycle;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

/**
 * Penjualan unit:
 *  - HPP dibekukan saat itu (hpp_amount), laba kotor = harga jual - HPP
 *  - status motor menjadi terjual
 *  - jurnal 1: Dr Kas / Cr Pendapatan Penjualan (harga jual)
 *  - jurnal 2: Dr HPP / Cr Persediaan Kendaraan (HPP unit)
 */
class SaleService
{
    public function __construct(private JournalService $journal, private HppService $hpp) {}

    /** @param array $data motorcycle_id, customer_id, sale_date, sale_price, payment_method?, notes? */
    public function create(array $data, ?int $userId = null): Sale
    {
        return DB::transaction(function () use ($data, $userId) {
            $motorcycle = Motorcycle::whereKey($data['motorcycle_id'])->lockForUpdate()->firstOrFail();

            if ($motorcycle->isSold() || $motorcycle->sale()->exists()) {
                throw new BusinessRuleException('Motor ini sudah terjual.');
            }
            if (! $motorcycle->purchase()->exists()) {
                throw new BusinessRuleException('Motor belum memiliki data pembelian, HPP tidak bisa dihitung.');
            }

            $salePrice = Money::of($data['sale_price'] ?? 0);
            if (! $salePrice->isPositive()) {
                throw new BusinessRuleException('Harga jual harus lebih dari 0.');
            }

            $hpp = $this->hpp->forMotorcycle($motorcycle);
            $date = $data['sale_date'];

            $sale = Sale::create([
                'sale_number' => DocumentNumber::next(Sale::class, 'sale_number', 'PJ', $date),
                'motorcycle_id' => $motorcycle->id,
                'customer_id' => $data['customer_id'],
                'user_id' => $userId,
                'sale_date' => $date,
                'sale_price' => Money::str($salePrice),
                'hpp_amount' => Money::str($hpp),
                'gross_profit' => Money::str($salePrice->minus($hpp)),
                'payment_method' => $data['payment_method'] ?? 'tunai',
                'notes' => $data['notes'] ?? null,
            ]);

            $motorcycle->update(['status' => Motorcycle::STATUS_TERJUAL]);

            $label = "{$motorcycle->brand} {$motorcycle->model} {$motorcycle->year} ({$motorcycle->frame_number})";

            $this->journal->post($date, "Penjualan {$label}", JournalEntry::REF_SALE, $sale->id, [
                ['account' => Account::KAS, 'debit' => $sale->sale_price],
                ['account' => Account::PENDAPATAN, 'credit' => $sale->sale_price],
            ], $userId);

            $this->journal->post($date, "HPP penjualan {$label}", JournalEntry::REF_SALE, $sale->id, [
                ['account' => Account::HPP, 'debit' => $sale->hpp_amount],
                ['account' => Account::PERSEDIAAN, 'credit' => $sale->hpp_amount],
            ], $userId);

            return $sale;
        });
    }
}
