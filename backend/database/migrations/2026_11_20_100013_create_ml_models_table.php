<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel ml_models: daftar model yang pernah dipakai, satu yang aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ml_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_name', 100);                      // XGBoost skenario B
            $table->string('algorithm', 50);                        // XGBRegressor
            $table->string('version', 20);
            $table->string('target_transform', 20)->default('log');
            $table->decimal('mae', 15, 2);
            $table->decimal('rmse', 15, 2)->nullable();
            $table->decimal('r2', 6, 4)->nullable();
            $table->string('model_file');                           // xgb_skenario_b.joblib
            $table->boolean('is_active')->default(false);
            $table->date('trained_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_models');
    }
};
