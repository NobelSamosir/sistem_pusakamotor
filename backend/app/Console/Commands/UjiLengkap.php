<?php

namespace App\Console\Commands;

use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\CustomerDeposit;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Motorcycle;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\CapitalService;
use App\Services\DocumentServiceService;
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
 * Uji lengkap transaksi perluasan: kredit leasing (komisi, bonus), uang muka (dengan atau tanpa unit),
 * pembelian dengan potongan, perlengkapan, jasa lain, prive.
 * Memakai tanggal Januari 2099 dan selisih neraca terhadap kondisi awal, jadi aman dijalankan
 * di database yang sudah berisi data. Semua data uji di-rollback.
 */
class UjiLengkap extends Command
{
    protected $signature = 'sia:uji-lengkap';

    protected $description = 'Uji transaksi kredit, uang muka, pembelian, perlengkapan, jasa lain, dan prive (otomatis di-rollback)';

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
            $maju = Supplier::create(['supplier_name' => 'UJI Showroom Maju']);
            $andi = Customer::create(['customer_name' => 'UJI Andi']); $sari = Customer::create(['customer_name' => 'UJI Sari']); $rina = Customer::create(['customer_name' => 'UJI Rina']);

            $S(CapitalService::class)->deposit('2099-01-01', 100000000);
            // Pembelian dengan potongan: sepakat 15,5 jt, penjual terima 15 jt (dipotong pajak 500 rb)
            $tolak('Harga kesepakatan lebih kecil dari yang dibayar', fn() => $S(PurchaseService::class)->create($m('UJIXSALAH1', 'Honda', 'Vario', 2022), ['supplier_id' => $budi->id, 'purchase_date' => '2099-01-02', 'purchase_price' => 15000000, 'agreed_price' => 14000000]));
            $pv = $S(PurchaseService::class)->create($m('UJIXVARIO1', 'Honda', 'Vario', 2022), ['supplier_id' => $budi->id, 'purchase_date' => '2099-01-02', 'purchase_price' => 15000000, 'agreed_price' => 15500000, 'deduction_notes' => 'Pajak tahunan 500.000']);
            $vario = $pv->motorcycle;
            $cek('Pembelian: harga kesepakatan tersimpan', $pv->agreed_price, '15500000.00');
            $cek('Pembelian: jurnal sebesar yang dibayar', $dl($S(ReportService::class)->balanceSheet('2099-01-02'), 'assets', 'inventory'), '15000000.00');
            $S(VehicleCostService::class)->create(['motorcycle_id' => $vario->id, 'cost_date' => '2099-01-03', 'cost_category' => 'servis', 'amount' => 600000, 'hpp_included' => true]);
            $S(SupplyService::class)->purchase(['purchase_date' => '2099-01-03', 'description' => 'Cat dan dempul', 'vendor' => 'Toko Jaya', 'amount' => 1000000]);
            $S(VehicleCostService::class)->create(['motorcycle_id' => $vario->id, 'cost_date' => '2099-01-04', 'cost_category' => 'cat_body', 'amount' => 300000, 'hpp_included' => true, 'source' => 'perlengkapan']);
            $S(VehicleCostService::class)->create(['motorcycle_id' => $vario->id, 'cost_date' => '2099-01-04', 'cost_category' => 'pajak', 'amount' => 500000, 'hpp_included' => true]);
            $cek('HPP Vario (beli+servis+cat+pajak)', (string)$S(HppService::class)->forMotorcycle($vario->fresh()), '16400000.00');
            $cek('Saldo perlengkapan setelah dipakai', (string)$S(SupplyService::class)->balance(), '700000.00');
            $tolak('Pakai perlengkapan melebihi saldo', fn() => $S(VehicleCostService::class)->create(['motorcycle_id' => $vario->id, 'cost_date' => '2099-01-04', 'cost_category' => 'cat_body', 'amount' => 900000, 'hpp_included' => true, 'source' => 'perlengkapan']));
            $beat = $S(PurchaseService::class)->create($m('UJIXBEAT1', 'Honda', 'Beat', 2020), ['supplier_id' => $budi->id, 'purchase_date' => '2099-01-05', 'purchase_price' => 10000000])->motorcycle;

            // Uang muka Andi untuk Vario
            $umAndi = $S(DepositService::class)->create(['motorcycle_id' => $vario->id, 'customer_id' => $andi->id, 'deposit_date' => '2099-01-06', 'amount' => 1000000]);
            $cek('Status Vario setelah uang muka', $vario->fresh()->status, 'dipesan');
            $tolak('Uang muka kedua untuk motor yang sama', fn() => $S(DepositService::class)->create(['motorcycle_id' => $vario->id, 'customer_id' => $sari->id, 'deposit_date' => '2099-01-06', 'amount' => 500000]));
            $tolak('Jual motor yang dipesan Andi ke Sari', fn() => $S(SaleService::class)->create(['motorcycle_id' => $vario->id, 'customer_id' => $sari->id, 'sale_date' => '2099-01-07', 'sale_price' => 18500000]));
            $tolak('Kredit tanpa nama leasing', fn() => $S(SaleService::class)->create(['motorcycle_id' => $vario->id, 'customer_id' => $andi->id, 'sale_date' => '2099-01-07', 'sale_price' => 18500000, 'sale_type' => 'kredit', 'down_payment' => 3500000]));
            $tolak('DP kredit lebih kecil dari tanda jadi', fn() => $S(SaleService::class)->create(['motorcycle_id' => $vario->id, 'customer_id' => $andi->id, 'sale_date' => '2099-01-07', 'sale_price' => 18500000, 'sale_type' => 'kredit', 'leasing_company' => 'Adira Finance', 'down_payment' => 500000]));

            // Jual Vario kredit ke Andi lewat Adira
            $sv = $S(SaleService::class)->create(['motorcycle_id' => $vario->id, 'customer_id' => $andi->id, 'sale_date' => '2099-01-08', 'sale_price' => 18500000,
              'sale_type' => 'kredit', 'leasing_company' => 'Adira Finance', 'down_payment' => 3500000]);
            $cek('Kredit: dibiayai leasing', $sv->financed_amount, '15000000.00');
            $cek('Kredit: tanda jadi terpakai', $sv->deposit_applied, '1000000.00');
            $cek('Kredit: status pencairan', $sv->disbursement_status, 'menunggu');
            $cek('Kredit: laba kotor', $sv->gross_profit, '2100000.00');
            $cek('Uang muka Andi jadi dipakai', $umAndi->fresh()->status, 'dipakai');
            $bsMid = $S(ReportService::class)->balanceSheet('2099-01-08');
            $cek('Neraca tgl 8: piutang leasing', $dl($bsMid, 'assets', 'leasing_receivable'), '15000000.00');
            $cek('Neraca tgl 8 seimbang', var_export($bsMid['is_balanced'], true), 'true');
            // Dana cair bersama komisi (nominal diisi bebas)
            $S(LeasingService::class)->disburse($sv, '2099-01-11', null, 750000);
            $cek('Komisi tercatat saat pencairan', $sv->fresh()->leasing_commission, '750000.00');
            $tolak('Pencairan dua kali', fn() => $S(LeasingService::class)->disburse($sv->fresh(), '2099-01-12'));
            $tolak('Komisi dua kali', fn() => $S(LeasingService::class)->recordCommission($sv->fresh(), '2099-01-12', 100000));
            $tolak('Bonus nol', fn() => $S(DocumentServiceService::class)->create(['service_date' => '2099-01-20', 'service_type' => 'bonus_leasing', 'amount_received' => 0, 'amount_paid' => 0]));
            $S(DocumentServiceService::class)->create(['service_date' => '2099-01-20', 'service_type' => 'bonus_leasing', 'description' => 'Adira Finance target 2026', 'amount_received' => 1000000, 'amount_paid' => 0]);

            // Uang muka sebelum unit ada: Sari pesan PCX, unit dibeli dari showroom lain
            $tolak('Uang muka tanpa unit dan tanpa keterangan', fn() => $S(DepositService::class)->create(['customer_id' => $sari->id, 'deposit_date' => '2099-01-12', 'amount' => 2000000]));
            $umSari = $S(DepositService::class)->create(['customer_id' => $sari->id, 'unit_description' => 'Honda PCX 2021 hitam', 'deposit_date' => '2099-01-12', 'amount' => 2000000]);
            $cek('Uang muka tanpa unit: motor kosong', var_export($umSari->motorcycle_id, true), 'NULL');
            $pcx = $S(PurchaseService::class)->create($m('UJIXPCX1', 'Honda', 'PCX', 2021), ['supplier_id' => $maju->id, 'purchase_date' => '2099-01-13', 'purchase_price' => 25000000])->motorcycle;
            $tolak('Uang muka Sari dipakai pelanggan lain', fn() => $S(SaleService::class)->create(['motorcycle_id' => $pcx->id, 'customer_id' => $rina->id, 'sale_date' => '2099-01-14', 'sale_price' => 28000000, 'deposit_id' => $umSari->id]));
            $sp = $S(SaleService::class)->create(['motorcycle_id' => $pcx->id, 'customer_id' => $sari->id, 'sale_date' => '2099-01-14', 'sale_price' => 28000000, 'deposit_id' => $umSari->id]);
            $cek('PCX: uang muka terpakai', $sp->deposit_applied, '2000000.00');
            $cek('PCX: laba kotor', $sp->gross_profit, '3000000.00');
            $cek('Uang muka Sari terkait ke PCX', $umSari->fresh()->motorcycle_id, $pcx->id);

            // Uang muka Rina untuk Beat lalu hangus; uang muka Andi tanpa unit lalu dikembalikan
            $umRina = $S(DepositService::class)->create(['motorcycle_id' => $beat->id, 'customer_id' => $rina->id, 'deposit_date' => '2099-01-14', 'amount' => 500000]);
            $S(DepositService::class)->forfeit($umRina, '2099-01-20');
            $cek('Beat kembali tersedia setelah uang muka hangus', $beat->fresh()->status, 'tersedia');
            $tolak('Hanguskan uang muka dua kali', fn() => $S(DepositService::class)->forfeit($umRina->fresh(), '2099-01-20'));
            $umAndi2 = $S(DepositService::class)->create(['customer_id' => $andi->id, 'unit_description' => 'Yamaha NMAX', 'deposit_date' => '2099-01-15', 'amount' => 300000]);
            $S(DepositService::class)->refund($umAndi2, '2099-01-16');
            $cek('Uang muka tanpa unit dikembalikan', $umAndi2->fresh()->status, 'dikembalikan');

            // Pengantaran pinjaman ke bank: hanya komisi
            $jp = $S(DocumentServiceService::class)->create(['service_date' => '2099-01-18', 'service_type' => 'pengantaran_pinjaman', 'description' => 'Dana tunai BPKB Adira', 'amount_received' => 400000, 'amount_paid' => 0]);
            $cek('Pengantaran pinjaman: komisi', $jp->fee, '400000.00');

            // Prive, beban, perlengkapan umum
            $tolak('Prive melebihi kas', fn() => $S(CapitalService::class)->withdraw('2099-01-21', 999999999999));
            $S(CapitalService::class)->withdraw('2099-01-21', 2000000, 'Keperluan rumah');
            $S(OperatingExpenseService::class)->create(['expense_date' => '2099-01-22', 'category' => 'listrik', 'amount' => 300000]);
            $S(OperatingExpenseService::class)->create(['expense_date' => '2099-01-22', 'category' => 'lainnya', 'description' => 'Kain lap dan sabun', 'amount' => 100000, 'source' => 'perlengkapan']);

            // ===== Laporan akhir bulan
            $R = $S(ReportService::class);
            $is = $R->incomeStatement('2099-01-01', '2099-01-30');
            $cek('LR pendapatan penjualan', $is['revenue'], '46500000.00');
            $cek('LR HPP', $is['cogs'], '41400000.00');
            $cek('LR komisi leasing', $is['other_income']['leasing_commission'], '750000.00');
            $cek('LR pendapatan lain (hangus + pinjaman + bonus)', $is['other_income']['other'], '1900000.00');
            $cek('LR beban operasional', $is['operating_expense'], '400000.00');
            $cek('LR laba bersih', $is['net_profit'], '7350000.00');
            $bs = $R->balanceSheet('2099-01-30');
            $cek('Neraca kas', $dl($bs, 'assets', 'cash'), '94750000.00');
            $cek('Neraca piutang leasing', $dl($bs, 'assets', 'leasing_receivable'), '0.00');
            $cek('Neraca persediaan (Beat)', $dl($bs, 'assets', 'inventory'), '10000000.00');
            $cek('Neraca perlengkapan', $dl($bs, 'assets', 'supplies'), '600000.00');
            $cek('Neraca total aset', $dl($bs, 'assets', 'total'), '105350000.00');
            $cek('Neraca kewajiban', $dl($bs, 'liabilities', 'total'), '0.00');
            $cek('Neraca prive', $dl($bs, 'equity', 'prive'), '2000000.00');
            $cek('Neraca total ekuitas', $dl($bs, 'equity', 'total'), '105350000.00');
            $cek('Neraca seimbang', var_export($bs['is_balanced'], true), 'true');
            $cf = $R->cashFlow('2099-01-01', '2099-01-30');
            $cek('AK penerimaan penjualan', $cf['operating']['sales_receipts'], '28500000.00');
            $cek('AK pencairan leasing', $cf['operating']['leasing_disbursements'], '15000000.00');
            $cek('AK komisi leasing', $cf['operating']['leasing_commissions'], '750000.00');
            $cek('AK uang muka', $cf['operating']['customer_deposits'], '3500000.00');
            $cek('AK pendapatan lain (jasa, bonus)', $cf['operating']['document_services'], '1400000.00');
            $cek('AK perlengkapan', $cf['operating']['supply_purchases'], '-1000000.00');
            $cek('AK operasi bersih', $cf['operating']['net'], '-3250000.00');
            $cek('AK prive', $cf['financing']['owner_withdrawals'], '-2000000.00');
            $cek('AK kas akhir = neraca', $cf['closing_cash'], $bs['assets']['cash']);
            $st = $R->stock();
            $cek('Stok milik showroom (tambahan)', $st['total_units'] - $st0['total_units'], 1);
            $ps = $R->purchasesSales('2099-01-01', '2099-01-30');
            $cek('Laporan jual: 2 penjualan', $ps['totals']['sale_count'], 2);
            $cek('Laporan beli: harga kesepakatan', collect($ps['purchases'])->firstWhere('frame_number', 'UJIXVARIO1')->agreed_price, '15500000.00');
            $cek('Total debit = total kredit (seluruh jurnal)', Money::str(Money::of(JournalLine::sum('debit'))), Money::str(Money::of(JournalLine::sum('credit'))));
            foreach (JournalEntry::with('lines')->where('id', '>', $startId)->get() as $e) {
              $cek("Jurnal {$e->journal_number} seimbang", Money::of($e->lines->sum('debit'))->minus(Money::of($e->lines->sum('credit')))->isZero() ? 'ya' : 'tidak', 'ya');
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
