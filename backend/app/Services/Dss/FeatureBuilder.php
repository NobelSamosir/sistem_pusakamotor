<?php

namespace App\Services\Dss;

use App\Models\Motorcycle;

/**
 * Menyusun 17 fitur model (Tabel 3.3) dari data motor di sistem.
 * - mileage (km asli) diubah ke mileage_numeric = titik tengah rentang OLX
 * - n_dokumen dan ada_* diturunkan dari has_bpkb, has_stnk, has_faktur, has_buku_servis
 * - seller_type, provinsi, kota diisi konstanta showroom dari config/pusaka.php
 */
class FeatureBuilder
{
    public function fromMotorcycle(Motorcycle $m): array
    {
        return $this->fromInput($m->only([
            'brand', 'model', 'year', 'transmission', 'engine_capacity', 'body_type',
            'mileage', 'ownership_status', 'has_bpkb', 'has_stnk', 'has_faktur', 'has_buku_servis',
        ]));
    }

    /**
     * @param array $in brand, model, year, transmission, engine_capacity, body_type,
     *                  mileage (km), ownership_status, has_bpkb, has_stnk, has_faktur, has_buku_servis,
     *                  opsional: kota, provinsi, seller_type (untuk uji contoh)
     */
    public function fromInput(array $in): array
    {
        $flags = [
            'ada_bpkb' => (int) (bool) ($in['has_bpkb'] ?? false),
            'ada_stnk' => (int) (bool) ($in['has_stnk'] ?? false),
            'ada_faktur' => (int) (bool) ($in['has_faktur'] ?? false),
            'ada_buku_servis' => (int) (bool) ($in['has_buku_servis'] ?? false),
        ];

        return [
            'year' => (int) $in['year'],
            'mileage_numeric' => $this->mileageNumeric((int) $in['mileage']),
            'n_dokumen' => array_sum($flags),
            ...$flags,
            'dokumen_tidak_diketahui' => 0,
            'brand' => (string) $in['brand'],
            'transmission' => (string) $in['transmission'],
            'engine_capacity' => (string) $in['engine_capacity'],
            'body_type' => (string) $in['body_type'],
            'ownership_status' => (string) $in['ownership_status'],
            'seller_type' => (string) ($in['seller_type'] ?? config('pusaka.seller_type')),
            'provinsi' => (string) ($in['provinsi'] ?? config('pusaka.provinsi')),
            'model' => (string) $in['model'],
            'kota' => (string) ($in['kota'] ?? config('pusaka.kota')),
        ];
    }

    /**
     * Km asli -> mileage_numeric.
     * Memakai storage/app/mileage_map.json (ekspor notebook cleaning) bila ada,
     * jika tidak memakai rentang 5.000 km: 12.000 km -> 10.000-15.000 -> 12.500.
     */
    public function mileageNumeric(int $km): float
    {
        foreach ($this->mileageRanges() as [$lower, $upper, $numeric]) {
            if ($km >= $lower && ($upper === null || $km < $upper)) {
                return $numeric;
            }
        }

        return (float) (intdiv(max($km, 0), 5000) * 5000 + 2500);
    }

    /** @return array<int, array{0: int, 1: ?int, 2: float}> */
    private function mileageRanges(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $path = config('pusaka.mileage_map_path');
        if (! $path || ! is_file($path)) {
            return $cache = [];
        }

        $ranges = [];
        foreach (json_decode(file_get_contents($path), true) ?: [] as $row) {
            // Label contoh: "10.000-15.000 km", ">200.000 km", "0-5.000 km"
            preg_match_all('/\d[\d.]*/', (string) ($row['mileage'] ?? ''), $m);
            $nums = array_map(fn ($n) => (int) str_replace('.', '', $n), $m[0]);
            if (! $nums || ! isset($row['mileage_numeric'])) {
                continue;
            }
            $upper = count($nums) >= 2 ? $nums[1] : null;
            $ranges[] = [$nums[0], $upper, (float) $row['mileage_numeric']];
        }
        usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);

        return $cache = $ranges;
    }
}
