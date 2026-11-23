<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Motorcycle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lima laporan SIA. Laporan keuangan (laba rugi, neraca, arus kas)
 * dihitung dari journal_lines, sehingga selalu konsisten dengan jurnal.
 */
class ReportService
{
    /* ------------------------------------------------------------------ */
    /* 1. Laporan stok kendaraan                                           */
    /* ------------------------------------------------------------------ */
    public function stock(): array
    {
        $rows = DB::table('motorcycles as m')
            ->join('purchases as p', 'p.motorcycle_id', '=', 'm.id')
            ->leftJoin('vehicle_costs as c', function ($join) {
                $join->on('c.motorcycle_id', '=', 'm.id')->where('c.hpp_included', true);
            })
            ->whereIn('m.status', Motorcycle::STATUS_DI_SHOWROOM)
            ->where('m.ownership_type', Motorcycle::MILIK)
            ->groupBy('m.id', 'm.vehicle_code', 'm.frame_number', 'm.brand', 'm.model', 'm.year', 'm.color', 'm.status', 'p.purchase_date', 'p.purchase_price')
            ->orderBy('p.purchase_date')
            ->selectRaw('m.id, m.vehicle_code, m.frame_number, m.brand, m.model, m.year, m.color, m.status,
                p.purchase_date, p.purchase_price, COALESCE(SUM(c.amount), 0) as hpp_costs')
            ->get();

        $today = Carbon::today();
        $items = $rows->map(function ($r) use ($today) {
            $hpp = Money::of($r->purchase_price)->plus(Money::of($r->hpp_costs));

            return [
                'id' => $r->id,
                'vehicle_code' => $r->vehicle_code,
                'frame_number' => $r->frame_number,
                'unit' => "{$r->brand} {$r->model} {$r->year}",
                'color' => $r->color,
                'status' => $r->status,
                'purchase_date' => $r->purchase_date,
                'purchase_price' => Money::str(Money::of($r->purchase_price)),
                'hpp_costs' => Money::str(Money::of($r->hpp_costs)),
                'hpp' => Money::str($hpp),
                'days_in_stock' => (int) Carbon::parse($r->purchase_date)->diffInDays($today),
            ];
        })->values();

        $titipan = DB::table('consignments as c')
            ->join('motorcycles as m', 'm.id', '=', 'c.motorcycle_id')
            ->join('suppliers as s', 's.id', '=', 'c.consignor_id')
            ->where('c.status', 'dititipkan')
            ->orderBy('c.received_date')
            ->selectRaw("m.id, m.vehicle_code, m.frame_number, m.brand || ' ' || m.model || ' ' || m.year as unit,
                m.status, c.consignment_number, c.received_date, s.supplier_name as consignor, c.consignor_price")
            ->get();

        return [
            'items' => $items,
            'consignments' => $titipan,
            'total_units' => $items->count(),
            'total_hpp' => Money::str($items->reduce(fn ($t, $i) => $t->plus(Money::of($i['hpp'])), Money::of(0))),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* 2. Laporan pembelian dan penjualan                                  */
    /* ------------------------------------------------------------------ */
    public function purchasesSales(string $from, string $to): array
    {
        $purchases = DB::table('purchases as p')
            ->join('motorcycles as m', 'm.id', '=', 'p.motorcycle_id')
            ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->whereBetween('p.purchase_date', [$from, $to])
            ->orderBy('p.purchase_date')->orderBy('p.id')
            ->selectRaw("p.purchase_number, p.purchase_date, m.vehicle_code, m.frame_number,
                m.brand || ' ' || m.model || ' ' || m.year as unit, s.supplier_name, p.purchase_price")
            ->get();

        $sales = DB::table('sales as s')
            ->join('motorcycles as m', 'm.id', '=', 's.motorcycle_id')
            ->join('customers as c', 'c.id', '=', 's.customer_id')
            ->whereBetween('s.sale_date', [$from, $to])
            ->orderBy('s.sale_date')->orderBy('s.id')
            ->selectRaw("s.sale_number, s.sale_date, m.vehicle_code, m.frame_number,
                m.brand || ' ' || m.model || ' ' || m.year as unit, c.customer_name,
                s.sale_price, s.hpp_amount, s.gross_profit, s.sale_type, s.leasing_company,
                s.is_consignment, s.consignor_payable")
            ->get()
            ->map(function ($r) {
                $profit = Money::of($r->gross_profit);
                $r->markup = ($r->is_consignment || Money::of($r->hpp_amount)->isZero()) ? null
                    : (string) $profit->dividedBy(Money::of($r->hpp_amount), 4, \Brick\Math\RoundingMode::HALF_UP);
                $r->margin = (string) $profit->dividedBy(Money::of($r->sale_price), 4, \Brick\Math\RoundingMode::HALF_UP);

                return $r;
            });

        $sum = fn ($rows, $col) => Money::str($rows->reduce(fn ($t, $r) => $t->plus(Money::of($r->$col)), Money::of(0)));

        return [
            'period' => ['from' => $from, 'to' => $to],
            'purchases' => $purchases,
            'sales' => $sales,
            'totals' => [
                'purchase_count' => $purchases->count(),
                'purchase_amount' => $sum($purchases, 'purchase_price'),
                'sale_count' => $sales->count(),
                'sale_amount' => $sum($sales, 'sale_price'),
                'hpp_amount' => $sum($sales, 'hpp_amount'),
                'gross_profit' => $sum($sales, 'gross_profit'),
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* 3. Laporan laba rugi                                                */
    /* ------------------------------------------------------------------ */
    public function incomeStatement(string $from, string $to): array
    {
        $b = $this->balances($to, $from);

        $revenue = $b[Account::PENDAPATAN];
        $cogs = $b[Account::HPP];
        $expense = $b[Account::BEBAN_OPERASIONAL];
        $grossProfit = $revenue->minus($cogs);
        $other = [
            'leasing_commission' => $b[Account::KOMISI_LEASING],
            'consignment_commission' => $b[Account::KOMISI_TITIP],
            'document_service' => $b[Account::JASA_SURAT],
            'other' => $b[Account::PENDAPATAN_LAIN],
        ];
        $otherTotal = array_reduce($other, fn ($t, $v) => $t->plus($v), Money::of(0));
        $netProfit = $grossProfit->plus($otherTotal)->minus($expense);

        return [
            'period' => ['from' => $from, 'to' => $to],
            'revenue' => Money::str($revenue),
            'cogs' => Money::str($cogs),
            'gross_profit' => Money::str($grossProfit),
            'other_income' => array_map(fn ($v) => Money::str($v), $other) + ['total' => Money::str($otherTotal)],
            'operating_expense' => Money::str($expense),
            'net_profit' => Money::str($netProfit),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* 4. Laporan neraca per tanggal                                       */
    /* ------------------------------------------------------------------ */
    public function balanceSheet(string $date): array
    {
        $b = $this->balances($date);

        $cash = $b[Account::KAS];
        $receivable = $b[Account::PIUTANG_LEASING];
        $inventory = $b[Account::PERSEDIAAN];
        $supplies = $b[Account::PERLENGKAPAN];
        $assets = $cash->plus($receivable)->plus($inventory)->plus($supplies);

        $deposits = $b[Account::UANG_MUKA];
        $consignPayable = $b[Account::UTANG_KONSINYASI];
        $liabilities = $deposits->plus($consignPayable);

        $capital = $b[Account::MODAL];
        $prive = $b[Account::PRIVE];
        $profit = $b[Account::PENDAPATAN]->plus($b[Account::KOMISI_LEASING])->plus($b[Account::KOMISI_TITIP])
            ->plus($b[Account::PENDAPATAN_LAIN])->plus($b[Account::JASA_SURAT])->minus($b[Account::HPP])->minus($b[Account::BEBAN_OPERASIONAL]);
        $equity = $capital->minus($prive)->plus($profit);

        return [
            'date' => $date,
            'assets' => [
                'cash' => Money::str($cash),
                'leasing_receivable' => Money::str($receivable),
                'inventory' => Money::str($inventory),
                'supplies' => Money::str($supplies),
                'total' => Money::str($assets),
            ],
            'liabilities' => [
                'customer_deposits' => Money::str($deposits),
                'consignment_payable' => Money::str($consignPayable),
                'total' => Money::str($liabilities),
            ],
            'equity' => [
                'capital' => Money::str($capital),
                'prive' => Money::str($prive),
                'retained_profit' => Money::str($profit),
                'total' => Money::str($equity),
            ],
            'liabilities_and_equity' => Money::str($liabilities->plus($equity)),
            'is_balanced' => $assets->isEqualTo($liabilities->plus($equity)),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* 5. Laporan arus kas (metode langsung)                               */
    /* ------------------------------------------------------------------ */
    public function cashFlow(string $from, string $to): array
    {
        $openingDate = Carbon::parse($from)->subDay()->toDateString();
        $opening = $this->balances($openingDate)[Account::KAS];

        $flows = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('a.account_code', Account::KAS)
            ->whereBetween('e.entry_date', [$from, $to])
            ->groupBy('e.reference_type')
            ->selectRaw('e.reference_type, SUM(l.debit) - SUM(l.credit) as net')
            ->pluck('net', 'reference_type');

        $get = fn (string $type) => Money::of($flows[$type] ?? 0);

        $operating = [
            'sales_receipts' => $get(JournalEntry::REF_SALE),
            'leasing_disbursements' => $get(JournalEntry::REF_LEASING_DISBURSEMENT),
            'leasing_commissions' => $get(JournalEntry::REF_LEASING_COMMISSION),
            'document_services' => $get(JournalEntry::REF_DOCUMENT_SERVICE),
            'customer_deposits' => $get(JournalEntry::REF_DEPOSIT),
            'consignment_payouts' => $get(JournalEntry::REF_CONSIGNMENT_PAYOUT),
            'motorcycle_purchases' => $get(JournalEntry::REF_PURCHASE),
            'vehicle_costs' => $get(JournalEntry::REF_VEHICLE_COST),
            'supply_purchases' => $get(JournalEntry::REF_SUPPLY_PURCHASE),
            'operating_expenses' => $get(JournalEntry::REF_OPERATING_EXPENSE),
        ];
        $netOperating = array_reduce($operating, fn ($t, $v) => $t->plus($v), Money::of(0));
        $financing = [
            'capital_contributions' => $get(JournalEntry::REF_MANUAL),
            'owner_withdrawals' => $get(JournalEntry::REF_PRIVE),
        ];
        $netFinancing = array_reduce($financing, fn ($t, $v) => $t->plus($v), Money::of(0));
        $netChange = $netOperating->plus($netFinancing);

        return [
            'period' => ['from' => $from, 'to' => $to],
            'operating' => array_map(fn ($v) => Money::str($v), $operating) + ['net' => Money::str($netOperating)],
            'financing' => array_map(fn ($v) => Money::str($v), $financing) + ['net' => Money::str($netFinancing)],
            'net_change' => Money::str($netChange),
            'opening_cash' => Money::str($opening),
            'closing_cash' => Money::str($opening->plus($netChange)),
        ];
    }

    /**
     * Saldo setiap akun (sesuai saldo normal) sampai $to, opsional mulai $from.
     *
     * @return array<string, \Brick\Math\BigDecimal> kunci = kode akun
     */
    public function balances(string $to, ?string $from = null): array
    {
        $rows = DB::table('accounts as a')
            ->leftJoin('journal_lines as l', 'l.account_id', '=', 'a.id')
            ->leftJoin('journal_entries as e', function ($join) use ($to, $from) {
                $join->on('e.id', '=', 'l.journal_entry_id')->where('e.entry_date', '<=', $to);
                if ($from) {
                    $join->where('e.entry_date', '>=', $from);
                }
            })
            ->groupBy('a.account_code', 'a.normal_balance')
            ->selectRaw('a.account_code, a.normal_balance,
                COALESCE(SUM(CASE WHEN e.id IS NOT NULL THEN l.debit END), 0) as debit,
                COALESCE(SUM(CASE WHEN e.id IS NOT NULL THEN l.credit END), 0) as credit')
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $debit = Money::of($r->debit);
            $credit = Money::of($r->credit);
            $result[$r->account_code] = $r->normal_balance === 'debit' ? $debit->minus($credit) : $credit->minus($debit);
        }
        foreach ([Account::KAS, Account::PIUTANG_LEASING, Account::PERSEDIAAN, Account::PERLENGKAPAN,
            Account::UANG_MUKA, Account::UTANG_KONSINYASI, Account::MODAL, Account::PRIVE, Account::PENDAPATAN,
            Account::KOMISI_LEASING, Account::KOMISI_TITIP, Account::PENDAPATAN_LAIN, Account::JASA_SURAT, Account::HPP,
            Account::BEBAN_OPERASIONAL] as $code) {
            $result[$code] ??= Money::of(0);
        }

        return $result;
    }
}
