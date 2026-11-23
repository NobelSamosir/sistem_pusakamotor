<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\Consignment;
use App\Models\CustomerDeposit;
use App\Models\JournalEntry;
use App\Models\Motorcycle;
use Illuminate\Support\Facades\DB;

/**
 * Titip jual (konsinyasi).
 *  - Penerimaan motor titipan: TIDAK dijurnal, motor bukan milik showroom (bukan persediaan).
 *  - Saat laku: dijurnal oleh SaleService (Utang Konsinyasi + Pendapatan Komisi Titip Jual).
 *  - Pembayaran ke penitip: Dr Utang Konsinyasi / Cr Kas.
 *  - Motor dikembalikan ke penitip (tidak laku): tidak dijurnal.
 */
class ConsignmentService
{
    public function __construct(private JournalService $journal) {}

    public function receive(array $motor, array $data, ?int $userId = null): Consignment
    {
        $motor['frame_number'] = Motorcycle::normalizeNumber($motor['frame_number'] ?? null);
        $motor['engine_number'] = Motorcycle::normalizeNumber($motor['engine_number'] ?? null);

        if (Motorcycle::where('frame_number', $motor['frame_number'])->exists()) {
            throw new BusinessRuleException("Nomor rangka {$motor['frame_number']} sudah terdaftar.");
        }
        if (Motorcycle::where('engine_number', $motor['engine_number'])->exists()) {
            throw new BusinessRuleException("Nomor mesin {$motor['engine_number']} sudah terdaftar.");
        }
        if (! Money::of($data['consignor_price'] ?? 0)->isPositive()) {
            throw new BusinessRuleException('Harga bersih untuk penitip harus lebih dari 0.');
        }

        return DB::transaction(function () use ($motor, $data, $userId) {
            $date = $data['received_date'];
            $m = Motorcycle::create($motor + [
                'vehicle_code' => DocumentNumber::next(Motorcycle::class, 'vehicle_code', 'PM', $date),
                'status' => Motorcycle::STATUS_TERSEDIA,
                'ownership_type' => Motorcycle::TITIPAN,
            ]);

            return Consignment::create([
                'consignment_number' => DocumentNumber::next(Consignment::class, 'consignment_number', 'TJ', $date),
                'motorcycle_id' => $m->id,
                'consignor_id' => $data['consignor_id'],
                'user_id' => $userId,
                'received_date' => $date,
                'consignor_price' => Money::str(Money::of($data['consignor_price'])),
                'status' => Consignment::DITITIPKAN,
                'notes' => $data['notes'] ?? null,
            ])->load('motorcycle', 'consignor');
        });
    }

    /** Motor titipan tidak laku dan diambil kembali oleh pemiliknya. */
    public function returnToOwner(Consignment $c, string $date): Consignment
    {
        return DB::transaction(function () use ($c, $date) {
            $c = Consignment::whereKey($c->id)->lockForUpdate()->firstOrFail();
            if ($c->status !== Consignment::DITITIPKAN) {
                throw new BusinessRuleException("Titipan ini tidak bisa dikembalikan (status: {$c->status}).");
            }
            if (CustomerDeposit::where('motorcycle_id', $c->motorcycle_id)->where('status', CustomerDeposit::AKTIF)->exists()) {
                throw new BusinessRuleException('Motor ini masih punya uang muka aktif dari pelanggan. Selesaikan uang mukanya terlebih dahulu.');
            }

            $c->update(['status' => Consignment::DIKEMBALIKAN, 'returned_at' => $date]);
            Motorcycle::whereKey($c->motorcycle_id)->update(['status' => Motorcycle::STATUS_DIKEMBALIKAN]);

            return $c;
        });
    }

    /** Pembayaran hasil penjualan kepada penitip. */
    public function payout(Consignment $c, string $date, ?int $userId = null): Consignment
    {
        return DB::transaction(function () use ($c, $date, $userId) {
            $c = Consignment::whereKey($c->id)->lockForUpdate()->firstOrFail();
            if ($c->status !== Consignment::TERJUAL) {
                throw new BusinessRuleException($c->status === Consignment::LUNAS
                    ? 'Penitip sudah dibayar.'
                    : 'Motor titipan belum terjual, belum ada yang harus dibayar.');
            }

            $amount = $c->consignor_price;
            $this->journal->post($date, "Pembayaran kepada penitip {$c->consignment_number}",
                JournalEntry::REF_CONSIGNMENT_PAYOUT, $c->id, [
                    ['account' => Account::UTANG_KONSINYASI, 'debit' => $amount],
                    ['account' => Account::KAS, 'credit' => $amount],
                ], $userId);

            $c->update(['status' => Consignment::LUNAS, 'paid_at' => $date, 'paid_amount' => $amount]);

            return $c;
        });
    }
}
