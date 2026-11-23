<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jasa pengurusan surat kendaraan (perpanjang STNK, pajak tahunan, balik nama, mutasi).
 * Showroom hanya perantara: uang pelanggan diteruskan ke Samsat/biro jasa,
 * yang diakui sebagai pendapatan hanya selisihnya (upah jasa), dicatat di Pendapatan Lain-lain (4203).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_services', function (Blueprint $table) {
            $table->id();
            $table->string('service_number', 30)->unique();          // JS-2026-0001
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('service_date');
            $table->string('service_type', 30);                      // perpanjang_stnk | pajak_tahunan | balik_nama | mutasi | lainnya
            $table->string('license_plate', 20)->nullable();
            $table->string('description')->nullable();
            $table->decimal('amount_received', 15, 2);                // dibayar pelanggan
            $table->decimal('amount_paid', 15, 2);                    // disetor ke Samsat / biro jasa
            $table->decimal('fee', 15, 2);                            // upah jasa = pendapatan
            $table->timestamps();
            $table->index('service_date');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE document_services ADD CONSTRAINT document_services_type_check CHECK (service_type IN ('perpanjang_stnk','pajak_tahunan','balik_nama','mutasi','lainnya'))");
            DB::statement('ALTER TABLE document_services ADD CONSTRAINT document_services_amount_check CHECK (amount_paid >= 0 AND fee > 0 AND amount_received = amount_paid + fee)');
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('document_services');
    }
};
