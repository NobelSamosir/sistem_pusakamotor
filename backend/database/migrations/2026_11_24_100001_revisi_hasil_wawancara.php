<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi sesuai wawancara pemilik (23 November 2026):
 *  1. Titip jual (konsinyasi) dihapus. Motor dari showroom lain dicatat sebagai pembelian biasa.
 *  2. Uang muka boleh dibayar sebelum unitnya ada (motorcycle_id kosong + keterangan unit).
 *  3. Pembelian mencatat harga kesepakatan dan rincian potongan (pajak, perbaikan).
 *  4. Jenis jasa baru: pengantaran pinjaman ke bank/leasing (masuk Pendapatan Lain-lain).
 */
return new class extends Migration
{
    public function up(): void
    {
        $pg = DB::getDriverName() === 'pgsql';

        // Pengaman: jangan hapus fitur titip jual kalau sudah ada transaksinya.
        $titipan = Schema::hasTable('consignments') ? DB::table('consignments')->count() : 0;
        $jurnalTitip = DB::table('journal_lines as l')->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->whereIn('a.account_code', ['2102', '4202'])->count();
        if ($titipan > 0 || $jurnalTitip > 0) {
            throw new RuntimeException("Masih ada {$titipan} data titip jual dan {$jurnalTitip} baris jurnal titip jual. "
                .'Hapus data uji titip jual tersebut terlebih dahulu, lalu jalankan migrate lagi.');
        }

        // 1. Hapus titip jual
        Schema::dropIfExists('consignments');
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['is_consignment', 'consignor_payable', 'consignment_commission']);
        });
        if ($pg) {
            DB::statement('ALTER TABLE motorcycles DROP CONSTRAINT IF EXISTS motorcycles_ownership_check');
        }
        Schema::table('motorcycles', function (Blueprint $table) {
            $table->dropColumn('ownership_type');
        });
        if ($pg) {
            DB::statement('ALTER TABLE motorcycles DROP CONSTRAINT IF EXISTS motorcycles_status_check');
            DB::statement("ALTER TABLE motorcycles ADD CONSTRAINT motorcycles_status_check CHECK (status IN ('tersedia','perbaikan','dipesan','terjual'))");
        }
        DB::table('accounts')->whereIn('account_code', ['2102', '4202'])->delete();

        // 2. Uang muka sebelum unit tersedia
        Schema::table('customer_deposits', function (Blueprint $table) {
            $table->string('unit_description')->nullable()->after('motorcycle_id');
        });
        if ($pg) {
            DB::statement('ALTER TABLE customer_deposits ALTER COLUMN motorcycle_id DROP NOT NULL');
        }

        // 3. Harga kesepakatan dan potongan pembelian
        Schema::table('purchases', function (Blueprint $table) {
            $table->decimal('agreed_price', 15, 2)->nullable()->after('purchase_price');
            $table->text('deduction_notes')->nullable()->after('agreed_price');
        });
        if ($pg) {
            DB::statement('ALTER TABLE purchases ADD CONSTRAINT purchases_agreed_check CHECK (agreed_price IS NULL OR agreed_price >= purchase_price)');
        }

        // 4. Jenis jasa pengantaran pinjaman
        if ($pg) {
            DB::statement('ALTER TABLE document_services DROP CONSTRAINT IF EXISTS document_services_type_check');
            DB::statement("ALTER TABLE document_services ADD CONSTRAINT document_services_type_check CHECK (service_type IN ('perpanjang_stnk','pajak_tahunan','balik_nama','mutasi','pengantaran_pinjaman','lainnya'))");
        }
    }

    public function down(): void
    {
        $pg = DB::getDriverName() === 'pgsql';
        if ($pg) {
            DB::statement('ALTER TABLE purchases DROP CONSTRAINT IF EXISTS purchases_agreed_check');
        }
        Schema::table('purchases', fn (Blueprint $t) => $t->dropColumn(['agreed_price', 'deduction_notes']));
        Schema::table('customer_deposits', fn (Blueprint $t) => $t->dropColumn('unit_description'));
        // Titip jual tidak dikembalikan otomatis.
    }
};
