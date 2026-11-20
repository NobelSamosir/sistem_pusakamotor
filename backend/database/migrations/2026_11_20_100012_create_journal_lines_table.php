<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel journal_lines: baris debit/kredit.
 * Setiap baris hanya berisi debit ATAU kredit. Keseimbangan per jurnal
 * dijaga oleh JournalService di backend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->index('account_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_nonnegative_check CHECK (debit >= 0 AND credit >= 0)');
            DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_one_side_check CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
