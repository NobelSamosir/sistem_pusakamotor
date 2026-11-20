<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel sales: penjualan unit. Satu motor paling banyak satu penjualan.
 * Perbaikan dari ERD: hpp_amount dan gross_profit dibekukan saat penjualan,
 * karena template jurnal HPP (Tabel 3.9) membutuhkan hpp_amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number', 30)->unique();            // contoh: PJ-2026-0001
            $table->foreignId('motorcycle_id')->unique()->constrained('motorcycles')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('sale_date');
            $table->decimal('sale_price', 15, 2);
            $table->decimal('hpp_amount', 15, 2);
            $table->decimal('gross_profit', 15, 2);                 // sale_price - hpp_amount
            $table->string('payment_method', 20)->default('tunai');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('sale_date');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_price_check CHECK (sale_price > 0)');
            DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_hpp_check CHECK (hpp_amount > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
