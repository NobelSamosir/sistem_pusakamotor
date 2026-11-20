<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Motorcycle;
use App\Models\PricePrediction;
use App\Models\PriceRecommendation;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;

/**
 * Pembelian unit: simpan motor + pembelian + jurnal (Dr Persediaan / Cr Kas)
 * dalam SATU transaksi database.
 */
class PurchaseService
{
    public function __construct(private JournalService $journal) {}

    /**
     * @param array $motor    data motorcycles (frame_number, engine_number, brand, model, year, ...)
     * @param array $purchase supplier_id, purchase_date (Y-m-d), purchase_price, payment_method?, notes?
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

        return DB::transaction(function () use ($motor, $purchase, $userId, $recommendationId) {
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
}
