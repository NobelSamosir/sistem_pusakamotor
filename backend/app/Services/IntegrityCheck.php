<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Mencari data yang tidak sinkron antara tabel transaksi dan jurnal, misalnya karena
 * baris dihapus langsung di database (bukan lewat tombol hapus di aplikasi).
 */
class IntegrityCheck
{
    /** tabel transaksi => [reference_type jurnal, kolom nomor/keterangan, label] */
    public const MAP = [
        'purchases' => ['purchase', 'purchase_number', 'Pembelian'],
        'vehicle_costs' => ['vehicle_cost', 'cost_category', 'Biaya kendaraan'],
        'sales' => ['sale', 'sale_number', 'Penjualan'],
        'operating_expenses' => ['operating_expense', 'category', 'Beban operasional'],
        'customer_deposits' => ['deposit', 'deposit_number', 'Uang muka'],
        'supply_purchases' => ['supply_purchase', 'description', 'Pembelian perlengkapan'],
        'document_services' => ['document_service', 'description', 'Pendapatan lain'],
    ];

    /** @return array<int, array{jenis:string, masalah:string, id:int, keterangan:string}> */
    public function problems(): array
    {
        $out = [];
        foreach (self::MAP as $table => [$ref, $col, $label]) {
            // Transaksi tanpa jurnal
            $rows = DB::table("{$table} as t")
                ->whereNotExists(fn ($q) => $q->from('journal_entries as j')->whereColumn('j.reference_id', 't.id')->where('j.reference_type', $ref))
                ->select('t.id', "t.{$col} as ket")->get();
            foreach ($rows as $r) {
                $out[] = ['jenis' => $label, 'masalah' => 'Transaksi tanpa jurnal', 'id' => $r->id, 'keterangan' => (string) $r->ket];
            }
            // Jurnal yang transaksinya sudah tidak ada
            $rows = DB::table('journal_entries as j')->where('j.reference_type', $ref)
                ->whereNotExists(fn ($q) => $q->from("{$table} as t")->whereColumn('t.id', 'j.reference_id'))
                ->select('j.id', 'j.journal_number as ket')->get();
            foreach ($rows as $r) {
                $out[] = ['jenis' => $label, 'masalah' => 'Jurnal tanpa transaksi', 'id' => $r->id, 'keterangan' => (string) $r->ket];
            }
        }
        // Penjualan kredit yang sudah cair harus punya jurnal pencairan
        $rows = DB::table('sales as s')->where('s.disbursement_status', 'cair')
            ->whereNotExists(fn ($q) => $q->from('journal_entries as j')->whereColumn('j.reference_id', 's.id')->where('j.reference_type', 'leasing_disbursement'))
            ->select('s.id', 's.sale_number as ket')->get();
        foreach ($rows as $r) {
            $out[] = ['jenis' => 'Pencairan leasing', 'masalah' => 'Status cair tanpa jurnal', 'id' => $r->id, 'keterangan' => (string) $r->ket];
        }

        return $out;
    }
}
