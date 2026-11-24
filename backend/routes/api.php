<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\DocumentServiceController;
use App\Http\Controllers\Api\DssController;
use App\Http\Controllers\Api\JournalController;
use App\Http\Controllers\Api\LeasingController;
use App\Http\Controllers\Api\MotorcycleController;
use App\Http\Controllers\Api\OperatingExpenseController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplyController;
use App\Http\Controllers\Api\VehicleCostController;
use Illuminate\Support\Facades\Route;

/*
| Semua rute di file ini otomatis berawalan /api
| Contoh: POST http://127.0.0.1:8000/api/login
*/

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    // Autentikasi
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Data master
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('customers', CustomerController::class);
    Route::get('/accounts', [AccountController::class, 'index']);

    // Unit motor (dibuat lewat pembelian, bukan form terpisah)
    Route::get('/motorcycles', [MotorcycleController::class, 'index']);
    Route::get('/motorcycles/{motorcycle}', [MotorcycleController::class, 'show']);
    Route::put('/motorcycles/{motorcycle}', [MotorcycleController::class, 'update']);

    // Transaksi
    Route::get('/purchases', [PurchaseController::class, 'index']);
    Route::post('/purchases', [PurchaseController::class, 'store']);
    Route::put('/purchases/{purchase}', [PurchaseController::class, 'update']);
    Route::delete('/purchases/{purchase}', [PurchaseController::class, 'destroy']);
    Route::get('/vehicle-costs', [VehicleCostController::class, 'index']);
    Route::post('/vehicle-costs', [VehicleCostController::class, 'store']);
    Route::put('/vehicle-costs/{vehicleCost}', [VehicleCostController::class, 'update']);
    Route::delete('/vehicle-costs/{vehicleCost}', [VehicleCostController::class, 'destroy']);
    Route::get('/sales', [SaleController::class, 'index']);
    Route::post('/sales', [SaleController::class, 'store']);
    Route::delete('/sales/{sale}', [SaleController::class, 'destroy']);
    Route::get('/operating-expenses', [OperatingExpenseController::class, 'index']);
    Route::post('/operating-expenses', [OperatingExpenseController::class, 'store']);
    Route::put('/operating-expenses/{operatingExpense}', [OperatingExpenseController::class, 'update']);
    Route::delete('/operating-expenses/{operatingExpense}', [OperatingExpenseController::class, 'destroy']);

    // Penjualan kredit (leasing)
    Route::get('/leasing', [LeasingController::class, 'index']);
    Route::get('/leasing/companies', [LeasingController::class, 'companies']);
    Route::post('/leasing/{sale}/disburse', [LeasingController::class, 'disburse']);
    Route::post('/leasing/{sale}/commission', [LeasingController::class, 'commission']);

    // Uang muka / tanda jadi pelanggan
    Route::get('/deposits', [DepositController::class, 'index']);
    Route::post('/deposits', [DepositController::class, 'store']);
    Route::put('/deposits/{deposit}', [DepositController::class, 'update']);
    Route::delete('/deposits/{deposit}', [DepositController::class, 'destroy']);
    Route::post('/deposits/{deposit}/refund', [DepositController::class, 'refund']);
    Route::post('/deposits/{deposit}/forfeit', [DepositController::class, 'forfeit']);


    // Pendapatan lain-lain: pengurusan surat, pengantaran pinjaman, bonus leasing
    Route::get('/document-services', [DocumentServiceController::class, 'index']);
    Route::post('/document-services', [DocumentServiceController::class, 'store']);
    Route::put('/document-services/{documentService}', [DocumentServiceController::class, 'update']);
    Route::delete('/document-services/{documentService}', [DocumentServiceController::class, 'destroy']);

    // Perlengkapan (bahan habis pakai)
    Route::get('/supplies', [SupplyController::class, 'index']);
    Route::post('/supplies', [SupplyController::class, 'store']);
    Route::put('/supplies/{supply}', [SupplyController::class, 'update']);
    Route::delete('/supplies/{supply}', [SupplyController::class, 'destroy']);

    // Jurnal
    Route::get('/journals', [JournalController::class, 'index']);
    Route::post('/journals/capital', [JournalController::class, 'storeCapital']);
    Route::post('/journals/withdrawal', [JournalController::class, 'storeWithdrawal']);
    Route::put('/journals/{journal}', [JournalController::class, 'updateManual']);
    Route::delete('/journals/{journal}', [JournalController::class, 'destroyManual']);

    // Prediksi harga dan DSS (RM 3)
    Route::get('/ml/options', [DssController::class, 'options']);
    Route::get('/ml/health', [DssController::class, 'health']);
    Route::post('/predictions', [DssController::class, 'predict']);
    Route::get('/predictions', [DssController::class, 'predictions']);
    Route::post('/dss/purchase', [DssController::class, 'purchase']);
    Route::post('/dss/sale/{motorcycle}', [DssController::class, 'sale']);
    Route::get('/recommendations', [DssController::class, 'recommendations']);

    // Laporan (?from=YYYY-MM-DD&to=YYYY-MM-DD, neraca ?date=YYYY-MM-DD)
    Route::prefix('reports')->group(function () {
        Route::get('/stock', [ReportController::class, 'stock']);
        Route::get('/purchases-sales', [ReportController::class, 'purchasesSales']);
        Route::get('/income-statement', [ReportController::class, 'incomeStatement']);
        Route::get('/balance-sheet', [ReportController::class, 'balanceSheet']);
        Route::get('/cash-flow', [ReportController::class, 'cashFlow']);
    });
});
