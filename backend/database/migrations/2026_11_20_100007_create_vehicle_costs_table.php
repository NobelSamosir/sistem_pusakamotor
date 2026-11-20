<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel vehicle_costs: biaya per unit (servis, sparepart, dll).
 * hpp_included = true  -> menambah Persediaan Kendaraan (masuk HPP)
 * hpp_included = false -> dicatat sebagai Beban Operasional
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('motorcycle_id')->constrained('motorcycles')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('cost_date');
            $table->string('cost_category', 50);                    // servis | sparepart | cat_body | administrasi | lainnya
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->boolean('hpp_included')->default(true);
            $table->timestamps();

            $table->index('cost_date');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE vehicle_costs ADD CONSTRAINT vehicle_costs_amount_check CHECK (amount > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_costs');
    }
};
