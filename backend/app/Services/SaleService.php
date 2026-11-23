<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\Consignment;
use App\Models\CustomerDeposit;
use App\Models\JournalEntry;
use App\Models\Motorcycle;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

/**
 * Penjualan unit (tunai atau kredit lewat leasing; motor milik atau titipan).
 *
 * Jurnal pendapatan (satu jurnal, debit bisa lebih dari satu baris):
 *   Dr Kas                        uang yang diterima hari ini
 *   Dr Uang Muka Pelanggan        tanda jadi yang sudah dibayar sebelumnya (jika ada)
 *   Dr Piutang Pembiayaan         sisa harga yang akan dicairkan leasing (kredit)
 *      Cr Pendapatan Penjualan    harga jual                         (motor milik)
 *      Cr Utang Konsinyasi        harga bersih untuk penitip         (motor titipan)
 *      Cr Pendapatan Komisi Titip selisih harga jual - harga penitip (motor titipan)
 * Jurnal HPP (motor milik saja): Dr HPP / Cr Persediaan Kendaraan
 */
class SaleService
{
    public function __construct(private JournalService $journal, private HppService $hpp) {}

    /**
     * @param array $data motorcycle_id, customer_id, sale_date, sale_price, sale_type (tunai|kredit),
     *                    leasing_company?, down_payment?, payment_method?, notes?
     */
    public function create(array $data, ?int $userId = null): Sale
    {
        return DB::transaction(function () use ($data, $userId) {
            $motorcycle = Motorcycle::whereKey($data['motorcycle_id'])->lockForUpdate()->firstOrFail();

            if ($motorcycle->sale()->exists() || ! $motorcycle->isInShowroom()) {
                throw new BusinessRuleException("Motor ini tidak bisa dijual (status: {$motorcycle->status}).");
            }

            $isTitip = $motorcycle->isConsignment();
            $consignment = null;
            if ($isTitip) {
                $consignment = Consignment::where('motorcycle_id', $motorcycle->id)->lockForUpdate()->first();
                if (! $consignment || $consignment->status !== Consignment::DITITIPKAN) {
                    throw new BusinessRuleException('Data titip jual motor ini tidak aktif.');
                }
            } elseif (! $motorcycle->purchase()->exists()) {
                throw new BusinessRuleException('Motor belum memiliki data pembelian, HPP tidak bisa dihitung.');
            }

            $salePrice = Money::of($data['sale_price'] ?? 0);
            if (! $salePrice->isPositive()) {
                throw new BusinessRuleException('Harga jual harus lebih dari 0.');
            }

            // Uang muka (tanda jadi) yang masih aktif untuk motor ini
            $deposit = CustomerDeposit::where('motorcycle_id', $motorcycle->id)
                ->where('status', CustomerDeposit::AKTIF)->lockForUpdate()->first();
            if ($deposit && (int) $deposit->customer_id !== (int) $data['customer_id']) {
                throw new BusinessRuleException("Motor ini sudah dipesan pelanggan lain ({$deposit->deposit_number}). Kembalikan atau hanguskan uang mukanya terlebih dahulu.");
            }
            $depositAmt = $deposit ? Money::of($deposit->amount) : Money::of(0);

            // Tunai atau kredit
            $type = $data['sale_type'] ?? Sale::TUNAI;
            $downPayment = null;
            $financed = Money::of(0);
            if ($type === Sale::KREDIT) {
                if (empty($data['leasing_company'])) {
                    throw new BusinessRuleException('Nama perusahaan pembiayaan (leasing) wajib diisi untuk penjualan kredit.');
                }
                $downPayment = Money::of($data['down_payment'] ?? 0);
                if ($downPayment->isNegative() || $downPayment->isGreaterThanOrEqualTo($salePrice)) {
                    throw new BusinessRuleException('Uang muka (DP) kredit harus lebih dari atau sama dengan 0 dan lebih kecil dari harga jual.');
                }
                if ($downPayment->isLessThan($depositAmt)) {
                    throw new BusinessRuleException('DP kredit lebih kecil dari tanda jadi yang sudah dibayar pelanggan.');
                }
                $financed = $salePrice->minus($downPayment);
                $cashNow = $downPayment->minus($depositAmt);
            } else {
                $type = Sale::TUNAI;
                if ($depositAmt->isGreaterThan($salePrice)) {
                    throw new BusinessRuleException('Tanda jadi lebih besar dari harga jual.');
                }
                $cashNow = $salePrice->minus($depositAmt);
            }

            // Dasar biaya dan laba
            if ($isTitip) {
                $payable = Money::of($consignment->consignor_price);
                $commission = $salePrice->minus($payable);
                if ($commission->isNegative()) {
                    throw new BusinessRuleException('Harga jual di bawah harga bersih yang dijanjikan kepada penitip.');
                }
                $hpp = Money::of(0);
                $grossProfit = $commission;
            } else {
                $hpp = $this->hpp->forMotorcycle($motorcycle);
                $grossProfit = $salePrice->minus($hpp);
            }

            $date = $data['sale_date'];
            $sale = Sale::create([
                'sale_number' => DocumentNumber::next(Sale::class, 'sale_number', 'PJ', $date),
                'motorcycle_id' => $motorcycle->id,
                'customer_id' => $data['customer_id'],
                'user_id' => $userId,
                'sale_date' => $date,
                'sale_price' => Money::str($salePrice),
                'hpp_amount' => Money::str($hpp),
                'gross_profit' => Money::str($grossProfit),
                'payment_method' => $data['payment_method'] ?? 'tunai',
                'notes' => $data['notes'] ?? null,
                'sale_type' => $type,
                'leasing_company' => $type === Sale::KREDIT ? $data['leasing_company'] : null,
                'down_payment' => $downPayment ? Money::str($downPayment) : null,
                'financed_amount' => $type === Sale::KREDIT ? Money::str($financed) : null,
                'disbursement_status' => $type === Sale::KREDIT ? 'menunggu' : null,
                'deposit_applied' => Money::str($depositAmt),
                'is_consignment' => $isTitip,
                'consignor_payable' => $isTitip ? Money::str($payable) : null,
                'consignment_commission' => $isTitip ? Money::str($commission) : null,
            ]);

            $motorcycle->update(['status' => Motorcycle::STATUS_TERJUAL]);
            $consignment?->update(['status' => Consignment::TERJUAL]);
            $deposit?->update(['status' => CustomerDeposit::DIPAKAI, 'sale_id' => $sale->id, 'closed_at' => $date]);

            $label = "{$motorcycle->brand} {$motorcycle->model} {$motorcycle->year} ({$motorcycle->frame_number})";
            $lines = [];
            if ($cashNow->isPositive()) {
                $lines[] = ['account' => Account::KAS, 'debit' => Money::str($cashNow)];
            }
            if ($depositAmt->isPositive()) {
                $lines[] = ['account' => Account::UANG_MUKA, 'debit' => Money::str($depositAmt), 'memo' => $deposit->deposit_number];
            }
            if ($financed->isPositive()) {
                $lines[] = ['account' => Account::PIUTANG_LEASING, 'debit' => Money::str($financed), 'memo' => $data['leasing_company']];
            }
            if ($isTitip) {
                if ($payable->isPositive()) {
                    $lines[] = ['account' => Account::UTANG_KONSINYASI, 'credit' => Money::str($payable), 'memo' => $consignment->consignment_number];
                }
                if ($commission->isPositive()) {
                    $lines[] = ['account' => Account::KOMISI_TITIP, 'credit' => Money::str($commission)];
                }
            } else {
                $lines[] = ['account' => Account::PENDAPATAN, 'credit' => $sale->sale_price];
            }

            $jenis = $type === Sale::KREDIT ? "kredit ({$data['leasing_company']})" : 'tunai';
            $this->journal->post($date, ($isTitip ? 'Penjualan titip jual ' : 'Penjualan ')."{$jenis} {$label}",
                JournalEntry::REF_SALE, $sale->id, $lines, $userId);

            if (! $isTitip) {
                $this->journal->post($date, "HPP penjualan {$label}", JournalEntry::REF_SALE, $sale->id, [
                    ['account' => Account::HPP, 'debit' => $sale->hpp_amount],
                    ['account' => Account::PERSEDIAAN, 'credit' => $sale->hpp_amount],
                ], $userId);
            }

            return $sale;
        });
    }
}
