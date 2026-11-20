<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel operating_expenses: beban umum yang tidak melekat pada unit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operating_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('expense_date');
            $table->string('category', 50);                         // listrik | gaji | sewa | iklan | lainnya
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index('expense_date');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE operating_expenses ADD CONSTRAINT operating_expenses_amount_check CHECK (amount > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operating_expenses');
    }
};
