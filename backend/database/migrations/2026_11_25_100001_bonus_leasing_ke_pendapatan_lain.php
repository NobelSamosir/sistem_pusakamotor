<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bonus tahunan leasing dicatat sebagai Pendapatan Lain-lain lewat menu Pendapatan Lain
 * (jenis bonus_leasing), bukan lagi form khusus di halaman Kredit Leasing.
 * Bonus yang sempat tercatat dengan cara lama dipindah ke akun 4203.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_services DROP CONSTRAINT IF EXISTS document_services_type_check');
            DB::statement("ALTER TABLE document_services ADD CONSTRAINT document_services_type_check CHECK (service_type IN ('perpanjang_stnk','pajak_tahunan','balik_nama','mutasi','pengantaran_pinjaman','bonus_leasing','lainnya'))");
        }

        $old = DB::table('journal_entries')->where('reference_type', 'leasing_bonus')->pluck('id');
        if ($old->isNotEmpty()) {
            $komisi = DB::table('accounts')->where('account_code', '4201')->value('id');
            $lain = DB::table('accounts')->where('account_code', '4203')->value('id');
            DB::table('journal_lines')->whereIn('journal_entry_id', $old)->where('account_id', $komisi)->update(['account_id' => $lain]);
            DB::table('journal_entries')->whereIn('id', $old)->update(['reference_type' => 'document_service']);
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan.
    }
};
