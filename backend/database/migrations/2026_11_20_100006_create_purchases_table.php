<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel purchases: pembelian unit. Satu motor hanya punya satu pembelian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number', 30)->unique();       // contoh: PB-2026-0001
            $table->foreignId('motorcycle_id')->unique()->constrained('motorcycles')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('purchase_date');
            $table->decimal('purchase_price', 15, 2);
            $table->string('payment_method', 20)->default('tunai'); // tunai | transfer
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('purchase_date');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE purchases ADD CONSTRAINT purchases_price_check CHECK (purchase_price > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
