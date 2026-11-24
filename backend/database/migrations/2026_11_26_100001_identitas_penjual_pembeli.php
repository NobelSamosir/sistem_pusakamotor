<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identitas penjual dan pembeli diisi langsung di form transaksi (tanpa form master terpisah).
 * Ditambah NIK (nomor KTP) opsional sebagai bukti identitas pihak yang bertransaksi.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['suppliers', 'customers'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->string('id_number', 16)->nullable()->after('phone');
                $table->index('id_number');
            });
        }
    }

    public function down(): void
    {
        foreach (['suppliers', 'customers'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropIndex(['id_number']);
                $table->dropColumn('id_number');
            });
        }
    }
};
