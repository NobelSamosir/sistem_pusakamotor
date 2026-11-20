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
 * hpp_included = true  -> Dr Persediaan Kendaraan / Cr Kas (menambah HPP)
 * hpp_included = false -> Dr Beban Operasional / Cr Kas
 */
class VehicleCostService
{
    public function __construct(private JournalService $journal) {}

    /** @param array $data motorcycle_id, cost_date, cost_category, description?, amount, hpp_included */
    public function create(array $data, ?int $userId = null): VehicleCost
    {
        $motorcycle = Motorcycle::findOrFail($data['motorcycle_id']);
        $hppIncluded = (bool) ($data['hpp_included'] ?? true);

        if ($hppIncluded && $motorcycle->isSold()) {
            throw new BusinessRuleException('Motor sudah terjual, biaya tidak bisa lagi ditambahkan ke HPP.');
        }
        if (! Money::of($data['amount'] ?? 0)->isPositive()) {
            throw new BusinessRuleException('Nominal biaya harus lebih dari 0.');
        }

        return DB::transaction(function () use ($data, $userId, $motorcycle, $hppIncluded) {
            $cost = VehicleCost::create([
                'motorcycle_id' => $motorcycle->id,
                'user_id' => $userId,
                'cost_date' => $data['cost_date'],
                'cost_category' => $data['cost_category'],
                'description' => $data['description'] ?? null,
                'amount' => Money::str(Money::of($data['amount'])),
                'hpp_included' => $hppIncluded,
            ]);

            $this->journal->post(
                $data['cost_date'],
                "Biaya {$cost->cost_category} {$motorcycle->brand} {$motorcycle->model} ({$motorcycle->frame_number})",
                JournalEntry::REF_VEHICLE_COST,
                $cost->id,
                [
                    ['account' => $hppIncluded ? Account::PERSEDIAAN : Account::BEBAN_OPERASIONAL, 'debit' => $cost->amount],
                    ['account' => Account::KAS, 'credit' => $cost->amount],
                ],
                $userId,
            );

            return $cost;
        });
    }
}
