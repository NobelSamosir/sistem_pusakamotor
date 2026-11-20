<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel accounts: bagan akun (Tabel 3.8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code', 10)->unique();
            $table->string('account_name', 100);
            $table->string('account_type', 20);                    // aset | kewajiban | ekuitas | pendapatan | beban_pokok | beban
            $table->string('normal_balance', 6);                   // debit | kredit
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
