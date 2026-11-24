<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Motorcycle;
use App\Models\PricePrediction;
use App\Models\PriceRecommendation;
use App\Models\Purchase;
use App\Models\VehicleCost;
use Illuminate\Support\Facades\DB;

/**
 * Pembelian unit: simpan motor + pembelian + jurnal (Dr Persediaan / Cr Kas)
 * dalam SATU transaksi database. Jurnal sebesar uang yang benar-benar dibayar ke penjual;
 * selisih dengan harga kesepakatan (pajak, perbaikan) dicatat sebagai Biaya Kendaraan
 * masuk HPP saat showroom membayarnya.
 */
class PurchaseService
{
    public function __construct(private JournalService $journal) {}

    /**
     * @param array $motor    data motorcycles (frame_number, engine_number, brand, model, year, ...)
     * @param array $purchase supplier_id, purchase_date (Y-m-d), purchase_price (yang dibayar ke penjual),
     *                        agreed_price? (harga kesepakatan), deduction_notes? (rincian potongan), payment_method?, notes?
     */
    public function create(array $motor, array $purchase, ?int $userId = null, ?int $recommendationId = null): Purchase
    {
        $motor['frame_number'] = Motorcycle::normalizeNumber($motor['frame_number'] ?? null);
        $motor['engine_number'] = Motorcycle::normalizeNumber($motor['engine_number'] ?? null);

        if (Motorcycle::where('frame_number', $motor['frame_number'])->exists()) {
            throw new BusinessRuleException("Nomor rangka {$motor['frame_number']} sudah terdaftar.");
        }
        if (Motorcycle::where('engine_number', $motor['engine_number'])->exists()) {
            throw new BusinessRuleException("Nomor mesin {$motor['engine_number']} sudah terdaftar.");
        }
        if (! Money::of($purchase['purchase_price'] ?? 0)->isPositive()) {
            throw new BusinessRuleException('Harga beli harus lebih dari 0.');
        }
        $agreed = isset($purchase['agreed_price']) && $purchase['agreed_price'] !== '' ? Money::of($purchase['agreed_price']) : null;
        if ($agreed && $agreed->isLessThan(Money::of($purchase['purchase_price']))) {
            throw new BusinessRuleException('Harga kesepakatan tidak boleh lebih kecil dari uang yang dibayar ke penjual.');
        }

        return DB::transaction(function () use ($motor, $purchase, $userId, $recommendationId, $agreed) {
            $date = $purchase['purchase_date'];

            $motorcycle = Motorcycle::create($motor + [
                'vehicle_code' => DocumentNumber::next(Motorcycle::class, 'vehicle_code', 'PM', $date),
                'status' => Motorcycle::STATUS_TERSEDIA,
            ]);

            $record = Purchase::create([
                'purchase_number' => DocumentNumber::next(Purchase::class, 'purchase_number', 'PB', $date),
                'motorcycle_id' => $motorcycle->id,
                'supplier_id' => $purchase['supplier_id'],
                'user_id' => $userId,
                'purchase_date' => $date,
                'purchase_price' => Money::str(Money::of($purchase['purchase_price'])),
                'agreed_price' => $agreed ? Money::str($agreed) : null,
                'deduction_notes' => $purchase['deduction_notes'] ?? null,
                'payment_method' => $purchase['payment_method'] ?? 'tunai',
                'notes' => $purchase['notes'] ?? null,
            ]);

            $amount = $record->purchase_price;
            $this->journal->post(
                $date,
                "Pembelian {$motorcycle->brand} {$motorcycle->model} {$motorcycle->year} ({$motorcycle->frame_number})",
                JournalEntry::REF_PURCHASE,
                $record->id,
                [
                    ['account' => Account::PERSEDIAAN, 'debit' => $amount],
                    ['account' => Account::KAS, 'credit' => $amount],
                ],
                $userId,
            );

            // Tautkan hasil DSS beli (yang dibuat sebelum motor tercatat) ke motor baru
            if ($recommendationId) {
                $rec = PriceRecommendation::find($recommendationId);
                if ($rec && $rec->recommendation_type === 'beli') {
                    $rec->update(['motorcycle_id' => $motorcycle->id]);
                    PricePrediction::whereKey($rec->price_prediction_id)->update(['motorcycle_id' => $motorcycle->id]);
                }
            }

            return $record->load('motorcycle');
        });
    }

    /**
     * Ubah data pembelian (penjual, tanggal, harga, potongan, catatan). Karakteristik motor
     * diubah lewat data motor. Jurnal pembelian dihapus lalu dibuat ulang dengan angka baru.
     */
    public function update(Purchase $record, array $purchase, ?int $userId = null): Purchase
    {
        $motorcycle = $record->motorcycle;
        if (! $motorcycle->isInShowroom()) {
            throw new BusinessRuleException('Pembelian motor yang sudah terjual tidak bisa diubah karena HPP-nya sudah dipakai di penjualan. Hapus penjualannya terlebih dahulu bila memang salah.');
        }
        if (! Money::of($purchase['purchase_price'] ?? 0)->isPositive()) {
            throw new BusinessRuleException('Harga beli harus lebih dari 0.');
        }
        $agreed = isset($purchase['agreed_price']) && $purchase['agreed_price'] !== '' ? Money::of($purchase['agreed_price']) : null;
        if ($agreed && $agreed->isLessThan(Money::of($purchase['purchase_price']))) {
            throw new BusinessRuleException('Harga kesepakatan tidak boleh lebih kecil dari uang yang dibayar ke penjual.');
        }

        return DB::transaction(function () use ($record, $purchase, $agreed, $motorcycle, $userId) {
            $record->update([
                'supplier_id' => $purchase['supplier_id'],
                'purchase_date' => $purchase['purchase_date'],
                'purchase_price' => Money::str(Money::of($purchase['purchase_price'])),
                'agreed_price' => $agreed ? Money::str($agreed) : null,
                'deduction_notes' => $purchase['deduction_notes'] ?? null,
                'payment_method' => $purchase['payment_method'] ?? $record->payment_method,
                'notes' => $purchase['notes'] ?? null,
            ]);

            $this->journal->deleteForReference(JournalEntry::REF_PURCHASE, $record->id);
            $this->journal->post(
                $record->purchase_date->toDateString(),
                "Pembelian {$motorcycle->brand} {$motorcycle->model} {$motorcycle->year} ({$motorcycle->frame_number})",
                JournalEntry::REF_PURCHASE,
                $record->id,
                [
                    ['account' => Account::PERSEDIAAN, 'debit' => $record->purchase_price],
                    ['account' => Account::KAS, 'credit' => $record->purchase_price],
                ],
                $userId,
            );

            return $record->fresh(['motorcycle', 'supplier']);
        });
    }

    /** Hapus pembelian beserta data motor dan jurnalnya. Hanya bila motor belum punya transaksi lain. */
    public function delete(Purchase $record): void
    {
        $motorcycle = $record->motorcycle;
        if ($motorcycle->sale()->exists()) {
            throw new BusinessRuleException('Motor ini sudah terjual. Hapus penjualannya terlebih dahulu.');
        }
        if ($motorcycle->deposits()->exists()) {
            throw new BusinessRuleException('Motor ini punya data uang muka. Hapus uang mukanya terlebih dahulu.');
        }
        if (VehicleCost::where('motorcycle_id', $motorcycle->id)->exists()) {
            throw new BusinessRuleException('Motor ini punya biaya kendaraan. Hapus biayanya terlebih dahulu di menu Biaya Kendaraan.');
        }

        DB::transaction(function () use ($record, $motorcycle) {
            $this->journal->deleteForReference(JournalEntry::REF_PURCHASE, $record->id);
            PriceRecommendation::where('motorcycle_id', $motorcycle->id)->update(['motorcycle_id' => null]);
            PricePrediction::where('motorcycle_id', $motorcycle->id)->update(['motorcycle_id' => null]);
            $record->delete();
            $motorcycle->delete();
        });
    }
}
