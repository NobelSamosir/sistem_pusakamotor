<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel motorcycles: kartu identitas setiap unit (pusat ERD).
 * Perbaikan dari ERD: document_status diganti 4 kolom boolean (has_*)
 * agar n_dokumen dan ada_* untuk model bisa diturunkan langsung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motorcycles', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_code', 20)->unique();          // contoh: PM-2026-0001
            $table->string('frame_number', 30)->unique();          // nomor rangka, huruf besar tanpa spasi
            $table->string('engine_number', 30)->unique();         // nomor mesin, huruf besar tanpa spasi
            $table->string('license_plate', 15)->nullable();       // nomor polisi
            $table->string('bpkb_number', 30)->nullable();

            // Karakteristik yang menjadi fitur model (ejaan mengikuti data latih)
            $table->string('brand', 50);
            $table->string('model', 100);
            $table->smallInteger('year');
            $table->string('transmission', 30);
            $table->string('engine_capacity', 30);
            $table->string('body_type', 50);
            $table->unsignedInteger('mileage');                    // km asli, dikonversi ke mileage_numeric saat prediksi
            $table->string('ownership_status', 30);
            $table->boolean('has_bpkb')->default(false);
            $table->boolean('has_stnk')->default(false);
            $table->boolean('has_faktur')->default(false);
            $table->boolean('has_buku_servis')->default(false);

            // Data unit yang bukan fitur model
            $table->string('color', 30)->nullable();
            $table->text('condition_notes')->nullable();

            $table->string('status', 20)->default('tersedia');     // tersedia | perbaikan | terjual
            $table->timestamps();

            $table->index('status');
            $table->index(['brand', 'model']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE motorcycles ADD CONSTRAINT motorcycles_status_check CHECK (status IN ('tersedia','perbaikan','terjual'))");
            DB::statement('ALTER TABLE motorcycles ADD CONSTRAINT motorcycles_mileage_check CHECK (mileage >= 0)');
            DB::statement('ALTER TABLE motorcycles ADD CONSTRAINT motorcycles_year_check CHECK (year BETWEEN 1970 AND 2100)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('motorcycles');
    }
};
