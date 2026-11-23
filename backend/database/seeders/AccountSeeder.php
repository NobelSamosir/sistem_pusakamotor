<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Bagan akun sesuai Tabel 3.8.
 */
class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $akun = [
            ['1101', 'Kas (tunai dan transfer)',       'aset',        'debit'],
            ['1201', 'Persediaan Kendaraan',           'aset',        'debit'],
            ['3101', 'Modal Pemilik',                  'ekuitas',     'kredit'],
            ['4101', 'Pendapatan Penjualan Kendaraan', 'pendapatan',  'kredit'],
            ['5101', 'Harga Pokok Penjualan (HPP)',    'beban_pokok', 'debit'],
            ['6101', 'Beban Operasional',              'beban',       'debit'],
            // Perluasan: kredit leasing, uang muka, titip jual, perlengkapan, prive
            ['1102', 'Piutang Perusahaan Pembiayaan',  'aset',        'debit'],
            ['1301', 'Perlengkapan',                   'aset',        'debit'],
            ['2101', 'Uang Muka Pelanggan',            'kewajiban',   'kredit'],
            ['2102', 'Utang Konsinyasi (kepada penitip)', 'kewajiban', 'kredit'],
            ['3102', 'Prive Pemilik',                  'ekuitas',     'debit'],
            ['4201', 'Pendapatan Komisi Leasing',      'pendapatan',  'kredit'],
            ['4202', 'Pendapatan Komisi Titip Jual',   'pendapatan',  'kredit'],
            ['4203', 'Pendapatan Lain-lain',           'pendapatan',  'kredit'],
            ['4204', 'Pendapatan Jasa Pengurusan Surat', 'pendapatan', 'kredit'],
        ];

        foreach ($akun as [$kode, $nama, $jenis, $saldoNormal]) {
            DB::table('accounts')->updateOrInsert(
                ['account_code' => $kode],
                [
                    'account_name' => $nama,
                    'account_type' => $jenis,
                    'normal_balance' => $saldoNormal,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
