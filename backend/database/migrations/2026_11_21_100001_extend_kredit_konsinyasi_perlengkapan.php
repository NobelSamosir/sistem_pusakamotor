<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perluasan SIA:
 *  - penjualan kredit lewat perusahaan pembiayaan (leasing) + komisi leasing
 *  - uang muka / tanda jadi pelanggan (booking)
 *  - titip jual (konsinyasi)
 *  - perlengkapan (bahan habis pakai: cat, dempul, amplas, oli)
 *  - prive pemilik
 */
return new class extends Migration
{
    private array $newAccounts = [
        ['1102', 'Piutang Perusahaan Pembiayaan',      'aset',       'debit'],
        ['1301', 'Perlengkapan',                       'aset',       'debit'],
        ['2101', 'Uang Muka Pelanggan',                'kewajiban',  'kredit'],
        ['2102', 'Utang Konsinyasi (kepada penitip)',   'kewajiban',  'kredit'],
        ['3102', 'Prive Pemilik',                      'ekuitas',    'debit'],
        ['4201', 'Pendapatan Komisi Leasing',          'pendapatan', 'kredit'],
        ['4202', 'Pendapatan Komisi Titip Jual',       'pendapatan', 'kredit'],
        ['4203', 'Pendapatan Lain-lain',               'pendapatan', 'kredit'],
    ];

    public function up(): void
    {
        $pg = DB::getDriverName() === 'pgsql';

        // 1. Unit motor: milik showroom atau titipan
        Schema::table('motorcycles', function (Blueprint $table) {
            $table->string('ownership_type', 10)->default('milik')->after('status'); // milik | titipan
        });
        if ($pg) {
            DB::statement('ALTER TABLE motorcycles DROP CONSTRAINT IF EXISTS motorcycles_status_check');
            DB::statement("ALTER TABLE motorcycles ADD CONSTRAINT motorcycles_status_check CHECK (status IN ('tersedia','perbaikan','dipesan','terjual','dikembalikan'))");
            DB::statement("ALTER TABLE motorcycles ADD CONSTRAINT motorcycles_ownership_check CHECK (ownership_type IN ('milik','titipan'))");
        }

        // 2. Penjualan: tunai/kredit, uang muka yang dipakai, titip jual
        Schema::table('sales', function (Blueprint $table) {
            $table->string('sale_type', 10)->default('tunai')->after('sale_price');       // tunai | kredit
            $table->string('leasing_company', 100)->nullable()->after('sale_type');
            $table->decimal('down_payment', 15, 2)->nullable()->after('leasing_company');  // DP total dari pembeli
            $table->decimal('financed_amount', 15, 2)->nullable()->after('down_payment');  // dibayar leasing
            $table->string('disbursement_status', 10)->nullable()->after('financed_amount'); // menunggu | cair
            $table->date('disbursed_at')->nullable()->after('disbursement_status');
            $table->decimal('leasing_commission', 15, 2)->nullable()->after('disbursed_at');
            $table->date('commission_received_at')->nullable()->after('leasing_commission');
            $table->decimal('deposit_applied', 15, 2)->default(0)->after('commission_received_at');
            $table->boolean('is_consignment')->default(false)->after('deposit_applied');
            $table->decimal('consignor_payable', 15, 2)->nullable()->after('is_consignment');
            $table->decimal('consignment_commission', 15, 2)->nullable()->after('consignor_payable');
        });
        if ($pg) {
            DB::statement('ALTER TABLE sales DROP CONSTRAINT IF EXISTS sales_hpp_check');
            DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_hpp_check CHECK (hpp_amount >= 0)');
            DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_type_check CHECK (sale_type IN ('tunai','kredit'))");
        }

        // 3. Sumber pembayaran biaya dan beban: kas atau perlengkapan
        Schema::table('vehicle_costs', function (Blueprint $table) {
            $table->string('source', 15)->default('kas')->after('hpp_included'); // kas | perlengkapan
        });
        Schema::table('operating_expenses', function (Blueprint $table) {
            $table->string('source', 15)->default('kas')->after('amount');
        });

        // 4. Uang muka / tanda jadi pelanggan
        Schema::create('customer_deposits', function (Blueprint $table) {
            $table->id();
            $table->string('deposit_number', 30)->unique();          // UM-2026-0001
            $table->foreignId('motorcycle_id')->constrained('motorcycles')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('deposit_date');
            $table->decimal('amount', 15, 2);
            $table->string('status', 15)->default('aktif');            // aktif | dipakai | dikembalikan | hangus
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->date('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['motorcycle_id', 'status']);
        });

        // 5. Titip jual (konsinyasi)
        Schema::create('consignments', function (Blueprint $table) {
            $table->id();
            $table->string('consignment_number', 30)->unique();      // TJ-2026-0001
            $table->foreignId('motorcycle_id')->unique()->constrained('motorcycles')->restrictOnDelete();
            $table->foreignId('consignor_id')->constrained('suppliers')->restrictOnDelete(); // pemilik motor
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('received_date');
            $table->decimal('consignor_price', 15, 2);                 // harga bersih yang diterima penitip
            $table->string('status', 15)->default('dititipkan');       // dititipkan | terjual | lunas | dikembalikan
            $table->date('returned_at')->nullable();
            $table->date('paid_at')->nullable();
            $table->decimal('paid_amount', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 6. Pembelian perlengkapan (bahan habis pakai)
        Schema::create('supply_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('purchase_date');
            $table->string('description');                            // contoh: Cat semprot hitam 5 kaleng
            $table->string('vendor', 100)->nullable();                 // toko
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->index('purchase_date');
        });

        if ($pg) {
            DB::statement('ALTER TABLE customer_deposits ADD CONSTRAINT customer_deposits_amount_check CHECK (amount > 0)');
            DB::statement('ALTER TABLE consignments ADD CONSTRAINT consignments_price_check CHECK (consignor_price > 0)');
            DB::statement('ALTER TABLE supply_purchases ADD CONSTRAINT supply_purchases_amount_check CHECK (amount > 0)');
        }

        // 7. Akun baru (idempoten)
        foreach ($this->newAccounts as [$code, $name, $type, $normal]) {
            DB::table('accounts')->updateOrInsert(
                ['account_code' => $code],
                ['account_name' => $name, 'account_type' => $type, 'normal_balance' => $normal,
                 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_purchases');
        Schema::dropIfExists('consignments');
        Schema::dropIfExists('customer_deposits');

        Schema::table('operating_expenses', fn (Blueprint $t) => $t->dropColumn('source'));
        Schema::table('vehicle_costs', fn (Blueprint $t) => $t->dropColumn('source'));

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sales DROP CONSTRAINT IF EXISTS sales_type_check');
        }
        Schema::table('sales', fn (Blueprint $t) => $t->dropColumn([
            'sale_type', 'leasing_company', 'down_payment', 'financed_amount', 'disbursement_status', 'disbursed_at',
            'leasing_commission', 'commission_received_at', 'deposit_applied', 'is_consignment',
            'consignor_payable', 'consignment_commission',
        ]));

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE motorcycles DROP CONSTRAINT IF EXISTS motorcycles_ownership_check');
            DB::statement('ALTER TABLE motorcycles DROP CONSTRAINT IF EXISTS motorcycles_status_check');
            DB::statement("ALTER TABLE motorcycles ADD CONSTRAINT motorcycles_status_check CHECK (status IN ('tersedia','perbaikan','terjual'))");
        }
        Schema::table('motorcycles', fn (Blueprint $t) => $t->dropColumn('ownership_type'));

        DB::table('accounts')->whereIn('account_code', array_column($this->newAccounts, 0))->delete();
    }
};
