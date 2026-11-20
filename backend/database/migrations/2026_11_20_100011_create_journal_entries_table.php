<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel journal_entries: kepala jurnal.
 * reference_type + reference_id menunjuk transaksi sumber
 * (purchase, vehicle_cost, sale, operating_expense, manual).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('journal_number', 30)->unique();         // contoh: JU-2026-0001
            $table->date('entry_date');
            $table->string('description');
            $table->string('reference_type', 30);
            $table->unsignedBigInteger('reference_id')->nullable(); // kosong untuk jurnal manual
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
            $table->index('entry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
