<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel price_predictions: riwayat setiap prediksi range harga.
 * Perbaikan dari ERD: motorcycle_id nullable (DSS beli dipakai sebelum
 * motor tercatat) dan kolom warnings untuk peringatan keandalan model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('motorcycle_id')->nullable()->constrained('motorcycles')->nullOnDelete();
            $table->foreignId('ml_model_id')->constrained('ml_models')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('input_snapshot');                        // 17 fitur yang dikirim ke model
            $table->decimal('predicted_price', 15, 2);
            $table->decimal('lower_bound', 15, 2);                  // L = prediksi - MAE
            $table->decimal('upper_bound', 15, 2);                  // U = prediksi + MAE
            $table->jsonb('warnings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_predictions');
    }
};
