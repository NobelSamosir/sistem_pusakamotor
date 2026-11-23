<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Upah jasa pengurusan surat digabung ke Pendapatan Lain-lain (4203).
 * Jika akun 4204 sempat dibuat oleh versi sebelumnya, baris jurnalnya
 * dipindah ke 4203 lalu akun 4204 dihapus. Aman dijalankan walau 4204 tidak ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $old = DB::table('accounts')->where('account_code', '4204')->value('id');
        if (! $old) {
            return;
        }
        $new = DB::table('accounts')->where('account_code', '4203')->value('id');
        DB::table('journal_lines')->where('account_id', $old)->update(['account_id' => $new]);
        DB::table('accounts')->where('id', $old)->delete();
    }

    public function down(): void
    {
        // Tidak dikembalikan: penggabungan akun bersifat permanen.
    }
};
