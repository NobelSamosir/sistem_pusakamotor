<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Motorcycle;
use App\Models\VehicleCost;
use Illuminate\Support\Facades\DB;

/**
 * Biaya per unit.
 * Debit:  Persediaan Kendaraan (masuk HPP) atau Beban Operasional
 * Kredit: Kas (dibayar tunai/transfer, misal bengkel) atau Perlengkapan (bahan dari stok sendiri)
 */
class VehicleCostService
{
    private const LABEL = ['cat_body' => 'cat dan body', 'administrasi' => 'administrasi/pajak'];

    public function __construct(private JournalService $journal, private SupplyService $supplies) {}

    /** @param array $data motorcycle_id, cost_date, cost_category, description?, amount, hpp_included, source? (kas|perlengkapan) */
    public function create(array $data, ?int $userId = null): VehicleCost
    {
        $motorcycle = Motorcycle::findOrFail($data['motorcycle_id']);
        $hppIncluded = (bool) ($data['hpp_included'] ?? true);
        $source = ($data['source'] ?? 'kas') === 'perlengkapan' ? 'perlengkapan' : 'kas';
        if (! $motorcycle->isInShowroom()) {
            throw new BusinessRuleException("Biaya tidak bisa dicatat untuk motor berstatus {$motorcycle->status}.");
        }
        if (! Money::of($data['amount'] ?? 0)->isPositive()) {
            throw new BusinessRuleException('Nominal biaya harus lebih dari 0.');
        }
        $amount = Money::str(Money::of($data['amount']));
        if ($source === 'perlengkapan') {
            $this->supplies->ensureAvailable($amount);
        }

        return DB::transaction(function () use ($data, $userId, $motorcycle, $hppIncluded, $source, $amount) {
            $cost = VehicleCost::create([
                'motorcycle_id' => $motorcycle->id,
                'user_id' => $userId,
                'cost_date' => $data['cost_date'],
                'cost_category' => $data['cost_category'],
                'description' => $data['description'] ?? null,
                'amount' => $amount,
                'hpp_included' => $hppIncluded,
                'source' => $source,
            ]);

            $this->journal->post(
                $data['cost_date'],
                "Biaya ".(self::LABEL[$cost->cost_category] ?? str_replace('_', ' ', $cost->cost_category))." {$motorcycle->brand} {$motorcycle->model} ({$motorcycle->frame_number})"
                    .($source === 'perlengkapan' ? ' dari perlengkapan' : ''),
                JournalEntry::REF_VEHICLE_COST,
                $cost->id,
                [
                    ['account' => $hppIncluded ? Account::PERSEDIAAN : Account::BEBAN_OPERASIONAL, 'debit' => $amount],
                    ['account' => $source === 'perlengkapan' ? Account::PERLENGKAPAN : Account::KAS, 'credit' => $amount],
                ],
                $userId,
            );

            return $cost;
        });
    }

    /** Ubah biaya kendaraan (hanya selama motor masih di showroom). */
    public function update(VehicleCost $cost, array $data, ?int $userId = null): VehicleCost
    {
        return DB::transaction(function () use ($cost, $data, $userId) {
            $this->delete($cost);

            return $this->create($data + ['motorcycle_id' => $cost->motorcycle_id], $userId);
        });
    }

    /** Hapus biaya kendaraan beserta jurnalnya. Biaya motor yang sudah terjual tidak bisa dihapus karena sudah masuk HPP penjualan. */
    public function delete(VehicleCost $cost): void
    {
        $motorcycle = $cost->motorcycle;
        if ($motorcycle && ! $motorcycle->isInShowroom()) {
            throw new BusinessRuleException('Biaya motor yang sudah terjual tidak bisa diubah atau dihapus karena sudah masuk HPP penjualan. Hapus penjualannya terlebih dahulu bila memang salah.');
        }
        DB::transaction(function () use ($cost) {
            $this->journal->deleteForReference(JournalEntry::REF_VEHICLE_COST, $cost->id);
            $cost->delete();
        });
    }
}
