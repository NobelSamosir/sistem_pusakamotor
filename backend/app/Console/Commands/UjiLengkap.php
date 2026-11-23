<?php

namespace App\Console\Commands;

use App\Exceptions\BusinessRuleException;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\CustomerDeposit;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Motorcycle;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\CapitalService;
use App\Services\ConsignmentService;
use App\Services\DepositService;
use App\Services\HppService;
use App\Services\LeasingService;
use App\Services\Money;
use App\Services\OperatingExpenseService;
use App\Services\PurchaseService;
use App\Services\ReportService;
use App\Services\SaleService;
use App\Services\SupplyService;
use App\Services\VehicleCostService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Uji lengkap transaksi perluasan: kredit leasing, uang muka, titip jual, perlengkapan, prive.
 * Memakai tanggal Januari 2099 dan selisih neraca terhadap kondisi awal, jadi aman dijalankan
 * di database yang sudah berisi data. Semua data uji di-rollback.
 */
class UjiLengkap extends Command
{
    protected $signature = 'sia:uji-lengkap';

    protected $description = 'Uji transaksi kredit, uang muka, titip jual, perlengkapan, dan prive (otomatis di-rollback)';

    public function handle(): int
    {
        $hasil = [];
        $ok = 0;
        $fail = 0;
        $cek = function (string $nama, $aktual, $harapan) use (&$hasil, &$ok, &$fail) {
            $lulus = (string) $aktual === (string) $harapan;
            $lulus ? $ok++ : $fail++;
            $hasil[] = [$lulus ? 'LULUS' : 'GAGAL', $nama, (string) $aktual, (string) $harapan];
        };
        $tolak = function (string $nama, callable $f) use (&$hasil, &$ok, &$fail) {
            try {
                $f();
                $fail++;
                $hasil[] = ['GAGAL', $nama, 'diterima', 'ditolak'];
            } catch (BusinessRuleException $e) {
                $ok++;
                $hasil[] = ['LULUS', $nama, 'ditolak', 'ditolak'];
            }
        };
        $S = fn ($c) => app($c);
        $m = fn ($frame, $brand, $model, $year) => ['frame_number' => $frame, 'engine_number' => "E$frame", 'brand' => $brand, 'model' => $model, 'year' => $year,
            'transmission' => 'Otomatis', 'engine_capacity' => '110-125 cc', 'body_type' => 'Scooter', 'mileage' => 12000, 'ownership_status' => 'Ke-1', 'has_bpkb' => true, 'has_stnk' => true];

        $startId = (int) JournalEntry::max('id');
        DB::beginTransaction();

        try {
            // Kondisi awal: data asli showroom tidak boleh memengaruhi hasil uji.
            $bs0 = $S(ReportService::class)->balanceSheet('2099-01-31');
            $st0 = $S(ReportService::class)->stock();
            $dl = fn ($bs, $grp, $key) => Money::str(Money::of($bs[$grp][$key])->minus(Money::of($bs0[$grp][$key])));

            $budi = Supplier::create(['supplier_name' => 'UJI Budi']);
            $joko = Supplier::create(['supplier_name' => 'UJI Pak Joko (penitip)']);
            $andi = Customer::create(['customer_name' => 'UJI Andi']); $sari = Customer::create(['customer_name' => 'UJI Sari']); $rina = Customer::create(['customer_name' => 'UJI Rina']);

            $S(CapitalService::class)->deposit('2099-01-01', 100000000);
            $vario = $S(PurchaseService::class)->create($m('UJIXVARIO1', 'Honda', 'Vario', 2022), ['supplier_id' => $budi->id, 'purchase_date' => '2099-01-02', 'purchase_price' => 15000000])->motorcycle;
            $S(VehicleCostService::class)->create(['motorcycle_id' => $vario->id, 'cost_date' => '2099-01-03', 'cost_category' => 'servis', 'amount' => 600000, 'hpp_included' => true]);
            $S(SupplyService::class)->purchase(['purchase_date' => '2099-01-03', 'description' => 'Cat dan dempul', 'vendor' => 'Toko Jaya', 'amount' => 1000000]);
            $S(VehicleCostService::class)->create(['motorcycle_id' => $vario->id, 'cost_date' => '2099-01-04', 'cost_category' => 'cat_body', 'amount' => 300000, 'hpp_included' => true, 'source' => 'perlengkapan']);
            $cek('HPP Vario (beli+servis+cat sendiri)', (string)$S(HppService::class)->forMotorcycle($vario->fresh()), '15900000.00');
            $cek('Saldo perlengkapan setelah dipakai', (string)$S(SupplyService::class)->balance(), '700000.00');
            $tolak('Pakai perlengkapan melebihi saldo', fn() => $S(VehicleCostService::class)->create(['motorcycle_id' => $vario->id, 'cost_date' => '2099-01-04', 'cost_category' => 'cat_body', 'amount' => 900000, 'hpp_included' => true, 'source' => 'perlengkapan']));
            $beat = $S(PurchaseService::class)->create($m('UJIXBEAT1', 'Honda', 'Beat', 2020), ['supplier_id' => $budi->id, 'purchase_date' => '2099-01-05', 'purchase_price' => 10000000])->motorcycle;

            // Uang muka Andi -> Vario dipesan
            $umAndi = $S(DepositService::class)->create(['motorcycle_id' => $vario->id, 'customer_id' => $andi->id, 'deposit_date' => '2099-01-06', 'amount' => 1000000]);
            $cek('Status Vario setelah uang muka', $vario->fresh()->status, 'dipesan');
            $tolak('Uang muka kedua untuk motor yang sama', fn() => $S(DepositService::class)->create(['motorcycle_id' => $vario->id, 'customer_id' => $sari->id, 'deposit_date' => '2099-01-06', 'amount' => 500000]));
            $tolak('Jual motor yang dipesan Andi ke Sari', fn() => $S(SaleService::class)->create(['motorcycle_id' => $vario->id, 'customer_id' => $sari->id, 'sale_date' => '2099-01-07', 'sale_price' => 18500000]));
            $tolak('Kredit tanpa nama leasing', fn() => $S(SaleService::class)->create(['motorcycle_id' => $vario->id, 'customer_id' => $andi->id, 'sale_date' => '2099-01-07', 'sale_price' => 18500000, 'sale_type' => 'kredit', 'down_payment' => 3500000]));
            $tolak('DP kredit lebih kecil dari tanda jadi', fn() => $S(SaleService::class)->create(['motorcycle_id' => $vario->id, 'customer_id' => $andi->id, 'sale_date' => '2099-01-07', 'sale_price' => 18500000, 'sale_type' => 'kredit', 'leasing_company' => 'Adira Finance', 'down_payment' => 500000]));

            // Jual Vario kredit ke Andi
            $sv = $S(SaleService::class)->create(['motorcycle_id' => $vario->id, 'customer_id' => $andi->id, 'sale_date' => '2099-01-08', 'sale_price' => 18500000,
              'sale_type' => 'kredit', 'leasing_company' => 'Adira Finance', 'down_payment' => 3500000]);
            $cek('Kredit: dibiayai leasing', $sv->financed_amount, '15000000.00');
            $cek('Kredit: tanda jadi terpakai', $sv->deposit_applied, '1000000.00');
            $cek('Kredit: status pencairan', $sv->disbursement_status, 'menunggu');
            $cek('Kredit: laba kotor', $sv->gross_profit, '2600000.00');
            $cek('Uang muka Andi jadi dipakai', $umAndi->fresh()->status, 'dipakai');
            $bsMid = $S(ReportService::class)->balanceSheet('2099-01-08');
            $cek('Neraca tgl 8: piutang leasing', $dl($bsMid, 'assets', 'leasing_receivable'), '15000000.00');
            $cek('Neraca tgl 8 seimbang', var_export($bsMid['is_balanced'], true), 'true');
            $S(LeasingService::class)->disburse($sv, '2099-01-11');
            $tolak('Pencairan dua kali', fn() => $S(LeasingService::class)->disburse($sv->fresh(), '2099-01-12'));
            $S(LeasingService::class)->recordCommission($sv->fresh(), '2099-01-11', 500000);

            // Titip jual NMAX
            $tj = $S(ConsignmentService::class)->receive($m('UJIXNMAX1', 'Yamaha', 'NMAX', 2021), ['consignor_id' => $joko->id, 'received_date' => '2099-01-09', 'consignor_price' => 20000000]);
            $nmax = $tj->motorcycle;
            $cek('Titipan: jenis kepemilikan', $nmax->ownership_type, 'titipan');
            $cek('Titipan: dasar biaya untuk DSS', (string)$S(HppService::class)->forMotorcycle($nmax->fresh()), '20000000.00');
            $cek('Titipan tidak masuk persediaan', $dl($S(ReportService::class)->balanceSheet('2099-01-09'), 'assets', 'inventory'), '10000000.00');
            $c = $S(VehicleCostService::class)->create(['motorcycle_id' => $nmax->id, 'cost_date' => '2099-01-10', 'cost_category' => 'lainnya', 'description' => 'Cuci', 'amount' => 100000, 'hpp_included' => true]);
            $cek('Biaya motor titipan dipaksa jadi beban', var_export($c->hpp_included, true), 'false');
            $tolak('Bayar penitip sebelum motor laku', fn() => $S(ConsignmentService::class)->payout($tj->fresh(), '2099-01-10'));
            $tolak('Jual titipan di bawah harga penitip', fn() => $S(SaleService::class)->create(['motorcycle_id' => $nmax->id, 'customer_id' => $sari->id, 'sale_date' => '2099-01-12', 'sale_price' => 19000000]));
            $sn = $S(SaleService::class)->create(['motorcycle_id' => $nmax->id, 'customer_id' => $sari->id, 'sale_date' => '2099-01-12', 'sale_price' => 21500000]);
            $cek('Titipan laku: komisi', $sn->consignment_commission, '1500000.00');
            $cek('Titipan laku: tanpa HPP', $sn->hpp_amount, '0.00');
            $cek('Titipan laku: utang ke penitip', $dl($S(ReportService::class)->balanceSheet('2099-01-12'), 'liabilities', 'consignment_payable'), '20000000.00');
            $tolak('Kembalikan titipan yang sudah laku', fn() => $S(ConsignmentService::class)->returnToOwner($tj->fresh(), '2099-01-13'));
            $S(ConsignmentService::class)->payout($tj->fresh(), '2099-01-13');
            $cek('Status titipan setelah dibayar', $tj->fresh()->status, 'lunas');

            // Uang muka Rina untuk Beat, lalu hangus
            $umRina = $S(DepositService::class)->create(['motorcycle_id' => $beat->id, 'customer_id' => $rina->id, 'deposit_date' => '2099-01-14', 'amount' => 500000]);
            $S(DepositService::class)->forfeit($umRina, '2099-01-20');
            $cek('Beat kembali tersedia setelah uang muka hangus', $beat->fresh()->status, 'tersedia');
            $tolak('Hanguskan uang muka dua kali', fn() => $S(DepositService::class)->forfeit($umRina->fresh(), '2099-01-20'));

            // Prive, beban, perlengkapan umum
            $tolak('Prive melebihi kas', fn() => $S(CapitalService::class)->withdraw('2099-01-21', 999999999999));
            $S(CapitalService::class)->withdraw('2099-01-21', 2000000, 'Keperluan rumah');
            $S(OperatingExpenseService::class)->create(['expense_date' => '2099-01-22', 'category' => 'listrik', 'amount' => 300000]);
            $S(OperatingExpenseService::class)->create(['expense_date' => '2099-01-22', 'category' => 'lainnya', 'description' => 'Kain lap dan sabun', 'amount' => 100000, 'source' => 'perlengkapan']);

            // Titipan kedua dikembalikan
            $tj2 = $S(ConsignmentService::class)->receive($m('UJIXSCOOPY1', 'Honda', 'Scoopy', 2019), ['consignor_id' => $joko->id, 'received_date' => '2099-01-15', 'consignor_price' => 12000000]);
            $S(ConsignmentService::class)->returnToOwner($tj2, '2099-01-25');
            $cek('Titipan tidak laku dikembalikan', $tj2->motorcycle->fresh()->status, 'dikembalikan');
            $tolak('Jual motor yang sudah dikembalikan', fn() => $S(SaleService::class)->create(['motorcycle_id' => $tj2->motorcycle_id, 'customer_id' => $sari->id, 'sale_date' => '2099-01-26', 'sale_price' => 13000000]));

            // ===== Laporan akhir bulan
            $R = $S(ReportService::class);
            $is = $R->incomeStatement('2099-01-01', '2099-01-30');
            $cek('LR pendapatan penjualan', $is['revenue'], '18500000.00');
            $cek('LR HPP', $is['cogs'], '15900000.00');
            $cek('LR komisi leasing', $is['other_income']['leasing_commission'], '500000.00');
            $cek('LR komisi titip jual', $is['other_income']['consignment_commission'], '1500000.00');
            $cek('LR pendapatan lain (uang muka hangus)', $is['other_income']['other'], '500000.00');
            $cek('LR beban operasional', $is['operating_expense'], '500000.00');
            $cek('LR laba bersih', $is['net_profit'], '4600000.00');
            $bs = $R->balanceSheet('2099-01-30');
            $cek('Neraca kas', $dl($bs, 'assets', 'cash'), '92000000.00');
            $cek('Neraca piutang leasing', $dl($bs, 'assets', 'leasing_receivable'), '0.00');
            $cek('Neraca persediaan (Beat)', $dl($bs, 'assets', 'inventory'), '10000000.00');
            $cek('Neraca perlengkapan', $dl($bs, 'assets', 'supplies'), '600000.00');
            $cek('Neraca total aset', $dl($bs, 'assets', 'total'), '102600000.00');
            $cek('Neraca kewajiban', $dl($bs, 'liabilities', 'total'), '0.00');
            $cek('Neraca prive', $dl($bs, 'equity', 'prive'), '2000000.00');
            $cek('Neraca total ekuitas', $dl($bs, 'equity', 'total'), '102600000.00');
            $cek('Neraca seimbang', var_export($bs['is_balanced'], true), 'true');
            $cf = $R->cashFlow('2099-01-01', '2099-01-30');
            $cek('AK penerimaan penjualan', $cf['operating']['sales_receipts'], '24000000.00');
            $cek('AK pencairan leasing', $cf['operating']['leasing_disbursements'], '15000000.00');
            $cek('AK uang muka', $cf['operating']['customer_deposits'], '1500000.00');
            $cek('AK bayar penitip', $cf['operating']['consignment_payouts'], '-20000000.00');
            $cek('AK perlengkapan', $cf['operating']['supply_purchases'], '-1000000.00');
            $cek('AK operasi bersih', $cf['operating']['net'], '-6000000.00');
            $cek('AK prive', $cf['financing']['owner_withdrawals'], '-2000000.00');
            $cek('AK kas akhir = neraca', $cf['closing_cash'], $bs['assets']['cash']);
            $st = $R->stock();
            $cek('Stok milik showroom (tambahan)', $st['total_units'] - $st0['total_units'], 1);
            $cek('Stok titipan aktif (tambahan)', count($st['consignments']) - count($st0['consignments']), 0);
            $ps = $R->purchasesSales('2099-01-01', '2099-01-30');
            $cek('Laporan jual: 2 penjualan', $ps['totals']['sale_count'], 2);
            $cek('Total debit = total kredit (seluruh jurnal)', Money::str(Money::of(JournalLine::sum('debit'))), Money::str(Money::of(JournalLine::sum('credit'))));
            foreach (JournalEntry::with('lines')->where('id', '>', $startId)->get() as $e) {
              $cek("Jurnal {$e->journal_number} seimbang", (string) Money::of($e->lines->sum('debit'))->minus(Money::of($e->lines->sum('credit')))->isZero() ? 'ya' : 'tidak', 'ya');
            }
        } finally {
            DB::rollBack();
        }

        $this->table(['Hasil', 'Pengujian', 'Aktual', 'Harapan'], $hasil);
        $this->line('');
        $fail === 0
            ? $this->info("Semua {$ok} pengujian LULUS. Data uji sudah dibatalkan (rollback), database tetap bersih.")
            : $this->error("{$fail} pengujian GAGAL dari ".($ok + $fail).'.');

        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }
}
