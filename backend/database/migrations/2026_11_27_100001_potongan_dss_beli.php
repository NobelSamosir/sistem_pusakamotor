<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DSS beli: potongan yang disepakati dengan penjual disimpan terpisah dari estimasi biaya servis.
 * estimated_service_cost = perkiraan biaya servis/pajak yang dikeluarkan showroom (bisa berubah),
 * seller_deduction       = potongan dari uang penjual yang disepakati saat tawar-menawar (boleh 0).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_recommendations', function (Blueprint $table) {
            $table->decimal('seller_deduction', 15, 2)->default(0)->after('estimated_service_cost');
        });
    }

    public function down(): void
    {
        Schema::table('price_recommendations', fn (Blueprint $t) => $t->dropColumn('seller_deduction'));
    }
};
