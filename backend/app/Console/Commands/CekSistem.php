<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\MlModel;
use App\Services\Dss\MlClient;
use App\Services\ReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cek kesiapan sistem: koneksi database, isi setiap tabel, saldo kas,
 * neraca, layanan model, dan data awal yang wajib ada.
 *
 *   php artisan sistem:cek
 */
class CekSistem extends Command
{
    protected $signature = 'sistem:cek';

    protected $description = 'Cek koneksi database, isi tabel, dan kesiapan data awal sistem';

    public function handle(ReportService $reports, MlClient $ml): int
    {
        try {
            DB::select('select 1');
            $this->info('Database terhubung: '.config('database.connections.pgsql.host'));
        } catch (\Throwable $e) {
            $this->error('Database TIDAK terhubung: '.$e->getMessage());

            return self::FAILURE;
        }

        $tabel = [
            'users' => 'Akun login', 'accounts' => 'Bagan akun', 'ml_models' => 'Model ML',
            'suppliers' => 'Pemasok', 'customers' => 'Pelanggan', 'motorcycles' => 'Motor',
            'purchases' => 'Pembelian', 'vehicle_costs' => 'Biaya kendaraan', 'sales' => 'Penjualan',
            'operating_expenses' => 'Beban operasional', 'journal_entries' => 'Jurnal',
            'journal_lines' => 'Baris jurnal', 'price_predictions' => 'Prediksi harga',
            'price_recommendations' => 'Rekomendasi DSS',
            'customer_deposits' => 'Uang muka pelanggan',
            'supply_purchases' => 'Pembelian perlengkapan', 'document_services' => 'Jasa lain (surat, pinjaman)',
        ];
        $rows = [];
        foreach ($tabel as $t => $label) {
            $last = DB::table($t)->max('created_at');
            $rows[] = [$label, $t, DB::table($t)->count(), $last ? substr($last, 0, 19) : '-'];
        }
        $this->table(['Data', 'Tabel', 'Jumlah baris', 'Terakhir disimpan'], $rows);

        $masalah = app(\App\Services\IntegrityCheck::class)->problems();
        if ($masalah) {
            $this->error(count($masalah).' data tidak sinkron (biasanya karena baris dihapus langsung di database):');
            $this->table(['Jenis', 'Masalah', 'ID', 'Keterangan'], array_map('array_values', $masalah));
            $this->line('Perbaiki lewat tombol Ubah/Hapus di aplikasi, jangan menghapus langsung di database.');
        } else {
            $this->info('Keutuhan data: setiap transaksi punya jurnal dan setiap jurnal punya transaksi.');
        }

        $b = $reports->balanceSheet(now()->toDateString());
        $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
        $this->line('Saldo hari ini: Kas '.$rp($b['assets']['cash']).' | Persediaan '.$rp($b['assets']['inventory'])
            .' | Modal '.$rp($b['equity']['capital']).' | Neraca '.($b['is_balanced'] ? 'SEIMBANG' : 'TIDAK SEIMBANG'));

        $h = $ml->health();
        $this->line('Layanan model: '.($h ? "AKTIF ({$h['model']})" : 'TIDAK AKTIF, jalankan uvicorn main:app --port 8001'));
        $this->line('');

        // Daftar kesiapan data awal
        $cek = [
            ['Akun login pemilik', DB::table('users')->where('is_active', true)->exists()],
            ['12 akun (6 akun Tabel 3.8 + 6 akun perluasan)', Account::count() >= 12],
            ['Model ML aktif', (bool) MlModel::active()],
            ['Setoran modal awal sudah dicatat', DB::table('journal_entries')->where('reference_type', 'manual')->exists()],
            ['Saldo kas tidak minus', (float) $b['assets']['cash'] >= 0],
            ['Layanan model menyala', (bool) $h],
        ];
        foreach ($cek as [$nama, $ok]) {
            $ok ? $this->info("[SIAP]  {$nama}") : $this->warn("[BELUM] {$nama}");
        }

        return self::SUCCESS;
    }
}
