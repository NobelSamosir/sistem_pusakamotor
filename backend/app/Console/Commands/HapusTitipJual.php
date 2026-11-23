<?php

namespace App\Console\Commands;

use App\Services\TitipJualCleanup;
use Illuminate\Console\Command;

/** Jalankan SEBELUM `php artisan migrate` jika migrasi revisi berhenti karena masih ada data titip jual. */
class HapusTitipJual extends Command
{
    protected $signature = 'sia:hapus-titip-jual {--force : Hapus tanpa konfirmasi}';

    protected $description = 'Hapus data uji titip jual (motor titipan, penjualan, uang muka, biaya, dan jurnalnya)';

    public function handle(TitipJualCleanup $cleanup): int
    {
        $n = $cleanup->preview();
        if ($n['motorcycles'] === 0) {
            $this->info('Tidak ada data titip jual. Langsung jalankan: php artisan migrate');

            return self::SUCCESS;
        }

        $this->table(['Data', 'Jumlah'], [
            ['Motor titipan', $n['motorcycles']], ['Data titip jual', $n['consignments']], ['Penjualan motor titipan', $n['sales']],
            ['Uang muka motor titipan', $n['deposits']], ['Biaya motor titipan', $n['costs']], ['Jurnal terkait', $n['journals']],
        ]);
        if (! $this->option('force') && ! $this->confirm('Hapus semua data di atas? Data lain tidak tersentuh.')) {
            $this->warn('Dibatalkan.');

            return self::FAILURE;
        }

        $cleanup->run();
        $this->info('Data titip jual sudah dihapus. Sekarang jalankan: php artisan migrate');

        return self::SUCCESS;
    }
}
