<?php

namespace App\Console\Commands;

use App\Services\Dss\DssRules;
use App\Services\Dss\FeatureBuilder;
use App\Services\Dss\MlClient;
use App\Services\Dss\PredictionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Uji DSS memakai layanan model SUNGGUHAN (ml-service harus menyala).
 * 1. Prediksi Honda Vario 2022 (Gambar 4.23) harus Rp19.777.670
 * 2. Aturan Tabel 3.10 dijalankan di range hasil prediksi itu
 * Semua data uji dibatalkan (rollback).
 *
 *   php artisan dss:uji-contoh
 */
class UjiContohDss extends Command
{
    protected $signature = 'dss:uji-contoh';

    protected $description = 'Uji integrasi model + aturan DSS dengan contoh Bab 4 (otomatis di-rollback)';

    public function handle(MlClient $ml, FeatureBuilder $features, PredictionService $predictions): int
    {
        $health = $ml->health();
        if (! $health) {
            $this->error('Layanan model tidak bisa dihubungi di '.config('pusaka.ml_url').'.');
            $this->line('Jalankan dulu di folder ml-service:  uvicorn main:app --port 8001');

            return self::FAILURE;
        }
        $this->info("Layanan model aktif: {$health['model']} (MAE ".number_format($health['mae'], 0, ',', '.').')');

        DB::beginTransaction();
        try {
            // Contoh Gambar 4.23 memakai kota Bandung Kota, bukan kota showroom
            $f = $features->fromInput([
                'brand' => 'Honda', 'model' => 'Vario', 'year' => 2022, 'transmission' => 'Otomatis',
                'engine_capacity' => '110-125 cc', 'body_type' => 'Scooter', 'mileage' => 12000,
                'ownership_status' => 'Ke-1', 'has_bpkb' => true, 'has_stnk' => true, 'has_faktur' => true,
                'kota' => 'Bandung Kota',
            ]);
            $p = $predictions->predict($f);
        } finally {
            DB::rollBack();
        }

        $rp = fn ($v) => $v === null ? '-' : 'Rp'.number_format((float) $v, 0, ',', '.');
        $cocok = (int) round($p->predicted_price) === 19777670;

        $this->table(['Item', 'Hasil', 'Harapan Bab 4'], [
            ['Prediksi Vario 2022', $rp($p->predicted_price), 'Rp19.777.670'],
            ['Batas bawah (L)', $rp($p->lower_bound), 'Rp17.179.745'],
            ['Batas atas (U)', $rp($p->upper_bound), 'Rp22.375.595'],
            ['Peringatan', implode(', ', array_column($p->warnings ?? [], 'kode')) ?: 'tidak ada', 'tidak ada'],
        ]);

        $this->line('DSS pembelian (PL 10%, estimasi servis Rp500.000, tanpa potongan dari penjual):');
        $rows = [];
        foreach (['15000000', '16000000', '17000000'] as $offer) {
            $r = DssRules::purchase($p->lower_bound, '0.10', '500000', $offer);
            $rows[] = [$rp($offer), $rp($r['max_purchase_price']), $r['decision_status'], $rp($r['recommended_price']), $rp($r['paid_to_seller'])];
        }
        $this->table(['Harga kesepakatan', 'Kesepakatan maks', 'Status', 'Rekomendasi', 'Dibayar ke penjual'], $rows);
        $r = DssRules::purchase($p->lower_bound, '0.10', '500000', '15500000', '500000');
        $this->line('Bila Rp500.000 dipotong dari uang penjual, kesepakatan Rp15.500.000: '.$r['decision_status'].', penjual menerima '.$rp($r['paid_to_seller']).'.');

        $this->line('DSS penjualan (HPP Rp15.600.000):');
        $rows = [];
        foreach (['0.10', '0.15', '0.50'] as $pl) {
            $r = DssRules::sale($p->lower_bound, $p->upper_bound, '15600000', $pl);
            $rows[] = [($pl * 100).'%', $rp($r['target_price']), $r['decision_status'], $rp($r['recommended_price']), $rp($r['expected_profit'])];
        }
        $r = DssRules::sale($p->lower_bound, $p->upper_bound, '23000000', '0.15');
        $rows[] = ['15% (HPP 23 jt)', $rp($r['target_price']), $r['decision_status'], $rp($r['recommended_price']), $rp($r['expected_profit'])];
        $this->table(['PL', 'Harga target', 'Status', 'Rekomendasi', 'Perkiraan laba'], $rows);

        if ($cocok) {
            $this->info('LULUS: prediksi sama persis dengan Bab 4. Data uji sudah di-rollback.');

            return self::SUCCESS;
        }
        $this->warn('Prediksi berbeda dari Bab 4. Cek versi scikit-learn/xgboost di ml-service (lihat metadata.json).');

        return self::FAILURE;
    }
}
