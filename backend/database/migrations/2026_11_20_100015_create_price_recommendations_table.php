<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel price_recommendations: hasil DSS beli dan DSS jual.
 * target_markup = PL, persen laba terhadap HPP (bukan margin atas harga jual).
 * expected_margin = laba / harga jual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('recommendation_type', 10);              // beli | jual
            $table->foreignId('motorcycle_id')->nullable()->constrained('motorcycles')->nullOnDelete();
            $table->foreignId('price_prediction_id')->constrained('price_predictions')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('target_markup', 6, 4);                 // PL, contoh 0.1500 = 15%

            // DSS penjualan
            $table->decimal('hpp', 15, 2)->nullable();
            $table->decimal('target_price', 15, 2)->nullable();     // HT = HPP x (1 + PL)

            // DSS pembelian
            $table->decimal('offer_price', 15, 2)->nullable();      // harga tawaran penjual
            $table->decimal('estimated_service_cost', 15, 2)->nullable();
            $table->decimal('max_purchase_price', 15, 2)->nullable(); // HB_maks = L / (1 + PL) - B_est

            // Hasil
            $table->string('decision_status', 30);                  // Layak dibeli | Margin tipis | Tidak disarankan | Sesuai pasar | Di bawah pasar | Di atas pasar | Berisiko rugi
            $table->decimal('recommended_price', 15, 2)->nullable();
            $table->decimal('expected_profit', 15, 2)->nullable();
            $table->decimal('expected_margin', 6, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('recommendation_type');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE price_recommendations ADD CONSTRAINT price_recommendations_type_check CHECK (recommendation_type IN ('beli','jual'))");
            DB::statement('ALTER TABLE price_recommendations ADD CONSTRAINT price_recommendations_markup_check CHECK (target_markup >= 0 AND target_markup <= 1)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('price_recommendations');
    }
};
