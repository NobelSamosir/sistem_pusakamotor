<?php

namespace App\Services;

use App\Models\Motorcycle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Ringkasan untuk halaman Dashboard (Gambar 3.20). */
class DashboardService
{
    public function __construct(private ReportService $reports) {}

    public function summary(?string $today = null): array
    {
        $now = $today ? Carbon::parse($today) : Carbon::today();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();

        $statusCounts = Motorcycle::query()
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $income = $this->reports->incomeStatement($monthStart, $monthEnd);
        $balance = $this->reports->balanceSheet($now->toDateString());

        // Grafik 12 bulan terakhir
        $start = $now->copy()->startOfMonth()->subMonths(11)->toDateString();
        $salesByMonth = DB::table('sales')->where('sale_date', '>=', $start)
            ->selectRaw("to_char(sale_date, 'YYYY-MM') as month, COUNT(*) as units, SUM(sale_price) as amount")
            ->groupBy('month')->get()->keyBy('month');
        $purchasesByMonth = DB::table('purchases')->where('purchase_date', '>=', $start)
            ->selectRaw("to_char(purchase_date, 'YYYY-MM') as month, COUNT(*) as units, SUM(purchase_price) as amount")
            ->groupBy('month')->get()->keyBy('month');

        $chart = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = $now->copy()->startOfMonth()->subMonths($i)->format('Y-m');
            $chart[] = [
                'month' => $m,
                'sales_units' => (int) ($salesByMonth[$m]->units ?? 0),
                'sales_amount' => Money::str(Money::of($salesByMonth[$m]->amount ?? 0)),
                'purchase_units' => (int) ($purchasesByMonth[$m]->units ?? 0),
                'purchase_amount' => Money::str(Money::of($purchasesByMonth[$m]->amount ?? 0)),
            ];
        }

        return [
            'month' => $now->format('Y-m'),
            'motorcycles' => [
                'total' => (int) $statusCounts->sum(),
                'tersedia' => (int) ($statusCounts[Motorcycle::STATUS_TERSEDIA] ?? 0),
                'perbaikan' => (int) ($statusCounts[Motorcycle::STATUS_PERBAIKAN] ?? 0),
                'dipesan' => (int) ($statusCounts[Motorcycle::STATUS_DIPESAN] ?? 0),
                'terjual' => (int) ($statusCounts[Motorcycle::STATUS_TERJUAL] ?? 0),
            ],
            'outstanding' => [
                'leasing_receivable' => $balance['assets']['leasing_receivable'],
                'leasing_waiting' => DB::table('sales')->where('disbursement_status', 'menunggu')->count(),
                'customer_deposits' => $balance['liabilities']['customer_deposits'],
                'deposits_unlinked' => DB::table('customer_deposits')->where('status', 'aktif')->whereNull('motorcycle_id')->count(),
                'cash' => $balance['assets']['cash'],
                'supplies' => $balance['assets']['supplies'],
            ],
            'this_month' => [
                'purchase_units' => DB::table('purchases')->whereBetween('purchase_date', [$monthStart, $monthEnd])->count(),
                'purchase_amount' => Money::str(Money::of(DB::table('purchases')->whereBetween('purchase_date', [$monthStart, $monthEnd])->sum('purchase_price'))),
                'sale_units' => DB::table('sales')->whereBetween('sale_date', [$monthStart, $monthEnd])->count(),
                'sale_amount' => $income['revenue'],
                'net_profit' => $income['net_profit'],
            ],
            'chart' => $chart,
            'latest_motorcycles' => Motorcycle::latest('id')->limit(5)
                ->get(['id', 'vehicle_code', 'brand', 'model', 'year', 'status']),
            'latest_sales' => DB::table('sales as s')->join('motorcycles as m', 'm.id', '=', 's.motorcycle_id')
                ->orderByDesc('s.sale_date')->orderByDesc('s.id')->limit(5)
                ->get(['s.sale_number', 's.sale_date', 'm.brand', 'm.model', 's.sale_price', 's.gross_profit']),
        ];
    }
}
