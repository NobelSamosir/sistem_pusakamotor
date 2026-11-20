<?php

namespace App\Console\Commands;

use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Services\CapitalService;
use App\Services\HppService;
use App\Services\OperatingExpenseService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Services\VehicleCostService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menjalankan contoh transaksi blueprint di database sungguhan,
 * mencocokkan saldonya, lalu MEMBATALKAN semuanya (rollback).
 * Aman dijalankan berkali-kali: tidak ada data yang tertinggal.
 *
 *   php artisan sia:uji-contoh
 */
class UjiContohSia extends Command
{
    protected $signature = 'sia:uji-contoh';

    protected $description = 'Uji alur SIA dengan contoh transaksi blueprint (otomatis di-rollback)';

    public function handle(
        CapitalService $capital,
        PurchaseService $purchases,
        VehicleCostService $costs,
        OperatingExpenseService $expenses,
        SaleService $sales,
        HppService $hpp,
    ): int {
        $hasil = [];
        $cek = function (string $nama, $aktual, $harapan) use (&$hasil) {
            $hasil[] = [(string) $aktual === (string) $harapan ? 'LULUS' : 'GAGAL', $nama, $aktual, $harapan];
        };

        $startId = (int) JournalEntry::max('id');
        DB::beginTransaction();

        try {
            $sup = Supplier::create(['supplier_name' => 'UJI Pemasok']);
            $cus = Customer::create(['customer_name' => 'UJI Pelanggan']);
            $uid = DB::table('users')->value('id');

            $capital->deposit('2026-11-01', 50000000, 'UJI setoran modal', $uid);

            $vario = $purchases->create(
                ['frame_number' => 'uji vario 0001', 'engine_number' => 'UJIVARIOMESIN1', 'brand' => 'Honda', 'model' => 'Vario',
                 'year' => 2022, 'transmission' => 'Otomatis', 'engine_capacity' => '110-125 cc', 'body_type' => 'Scooter',
                 'mileage' => 12000, 'ownership_status' => 'Ke-1', 'has_bpkb' => true, 'has_stnk' => true, 'has_faktur' => true],
                ['supplier_id' => $sup->id, 'purchase_date' => '2026-11-02', 'purchase_price' => 15000000], $uid,
            )->motorcycle;

            $costs->create(['motorcycle_id' => $vario->id, 'cost_date' => '2026-11-03', 'cost_category' => 'servis',
                'amount' => 600000, 'hpp_included' => true], $uid);
            $cek('HPP Vario', (string) $hpp->forMotorcycle($vario->fresh()), '15600000.00');

            $expenses->create(['expense_date' => '2026-11-05', 'category' => 'listrik', 'amount' => 300000], $uid);

            $sale = $sales->create(['motorcycle_id' => $vario->id, 'customer_id' => $cus->id,
                'sale_date' => '2026-11-10', 'sale_price' => 17940000], $uid);
            $cek('Laba kotor Vario', $sale->gross_profit, '2340000.00');

            $nmax = $purchases->create(
                ['frame_number' => 'UJINMAX0001', 'engine_number' => 'UJINMAXMESIN1', 'brand' => 'Yamaha', 'model' => 'NMAX',
                 'year' => 2021, 'transmission' => 'Otomatis', 'engine_capacity' => '150-250 cc', 'body_type' => 'Scooter',
                 'mileage' => 21000, 'ownership_status' => 'Ke-1', 'has_bpkb' => true, 'has_stnk' => true],
                ['supplier_id' => $sup->id, 'purchase_date' => '2026-11-12', 'purchase_price' => 22000000], $uid,
            )->motorcycle;
            $costs->create(['motorcycle_id' => $nmax->id, 'cost_date' => '2026-11-13', 'cost_category' => 'servis',
                'amount' => 400000, 'hpp_included' => true], $uid);

            // Saldo HANYA dari jurnal yang dibuat oleh uji ini
            $ids = JournalEntry::where('id', '>', $startId)->pluck('id');
            $saldo = DB::table('journal_lines as l')->join('accounts as a', 'a.id', '=', 'l.account_id')
                ->whereIn('l.journal_entry_id', $ids)
                ->groupBy('a.account_code', 'a.normal_balance')
                ->selectRaw("a.account_code, case when a.normal_balance = 'debit' then sum(l.debit) - sum(l.credit) else sum(l.credit) - sum(l.debit) end as saldo")
                ->pluck('saldo', 'account_code');

            $cek('Saldo Kas', number_format((float) $saldo['1101'], 2, '.', ''), '29640000.00');
            $cek('Saldo Persediaan', number_format((float) $saldo['1201'], 2, '.', ''), '22400000.00');
            $laba = $saldo['4101'] - $saldo['5101'] - $saldo['6101'];
            $cek('Laba bersih', number_format($laba, 2, '.', ''), '2040000.00');
            $cek('Aset = Modal + Laba', number_format($saldo['1101'] + $saldo['1201'], 2, '.', ''),
                number_format($saldo['3101'] + $laba, 2, '.', ''));

            try {
                $costs->create(['motorcycle_id' => $vario->id, 'cost_date' => '2026-11-15', 'cost_category' => 'servis',
                    'amount' => 100000, 'hpp_included' => true]);
                $cek('Tolak biaya HPP motor terjual', 'diterima', 'ditolak');
            } catch (BusinessRuleException) {
                $cek('Tolak biaya HPP motor terjual', 'ditolak', 'ditolak');
            }

            try {
                $sales->create(['motorcycle_id' => $vario->id, 'customer_id' => $cus->id,
                    'sale_date' => '2026-11-15', 'sale_price' => 18000000]);
                $cek('Tolak jual motor terjual', 'diterima', 'ditolak');
            } catch (BusinessRuleException) {
                $cek('Tolak jual motor terjual', 'ditolak', 'ditolak');
            }
        } finally {
            DB::rollBack();
        }

        $this->table(['Hasil', 'Pengujian', 'Aktual', 'Harapan'], $hasil);
        $gagal = collect($hasil)->where(0, 'GAGAL')->count();
        $this->line('');
        $gagal === 0
            ? $this->info('Semua pengujian LULUS. Data uji sudah dibatalkan (rollback), database tetap bersih.')
            : $this->error("{$gagal} pengujian GAGAL.");

        return $gagal === 0 ? self::SUCCESS : self::FAILURE;
    }
}
