<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membersihkan data uji fitur titip jual (konsinyasi) yang sudah dihapus dari sistem,
 * supaya migrasi revisi bisa dijalankan. Yang dihapus hanya data yang berkaitan dengan
 * motor titipan: motor, data titipan, penjualannya, uang muka, biaya, dan jurnalnya.
 * Jurnal dihapus utuh per transaksi sehingga neraca tetap seimbang.
 */
class TitipJualCleanup
{
    /** @return array<string,int> jumlah data per jenis */
    public function preview(): array
    {
        return $this->collect()['counts'];
    }

    /** @return array<string,int> jumlah data yang dihapus */
    public function run(): array
    {
        return DB::transaction(function () {
            $c = $this->collect();
            $ids = $c['ids'];
            if (! $ids['motorcycles']) {
                return $c['counts'];
            }

            DB::table('journal_lines')->whereIn('journal_entry_id', $ids['journals'])->delete();
            DB::table('journal_entries')->whereIn('id', $ids['journals'])->delete();
            DB::table('customer_deposits')->whereIn('id', $ids['deposits'])->delete();
            DB::table('vehicle_costs')->whereIn('id', $ids['costs'])->delete();
            DB::table('sales')->whereIn('id', $ids['sales'])->delete();
            DB::table('consignments')->whereIn('id', $ids['consignments'])->delete();
            DB::table('price_recommendations')->whereIn('motorcycle_id', $ids['motorcycles'])->delete();
            DB::table('price_predictions')->whereIn('motorcycle_id', $ids['motorcycles'])->delete();
            DB::table('motorcycles')->whereIn('id', $ids['motorcycles'])->delete();

            return $c['counts'];
        });
    }

    private function collect(): array
    {
        $empty = ['motorcycles' => [], 'consignments' => [], 'sales' => [], 'deposits' => [], 'costs' => [], 'journals' => []];
        if (! Schema::hasTable('consignments') || ! Schema::hasColumn('motorcycles', 'ownership_type')) {
            return ['ids' => $empty, 'counts' => array_map('count', $empty)];
        }

        $m = DB::table('motorcycles')->where('ownership_type', 'titipan')
            ->orWhereIn('id', DB::table('consignments')->select('motorcycle_id'))->pluck('id')->all();
        $ids = [
            'motorcycles' => $m,
            'consignments' => DB::table('consignments')->pluck('id')->all(),
            'sales' => DB::table('sales')->whereIn('motorcycle_id', $m)->pluck('id')->all(),
            'deposits' => DB::table('customer_deposits')->whereIn('motorcycle_id', $m)->pluck('id')->all(),
            'costs' => DB::table('vehicle_costs')->whereIn('motorcycle_id', $m)->pluck('id')->all(),
        ];
        $ids['journals'] = DB::table('journal_entries')
            ->where(fn ($q) => $q->whereIn('reference_type', ['sale', 'leasing_disbursement', 'leasing_commission'])->whereIn('reference_id', $ids['sales'] ?: [0]))
            ->orWhere(fn ($q) => $q->where('reference_type', 'consignment_payout')->whereIn('reference_id', $ids['consignments'] ?: [0]))
            ->orWhere(fn ($q) => $q->where('reference_type', 'deposit')->whereIn('reference_id', $ids['deposits'] ?: [0]))
            ->orWhere(fn ($q) => $q->where('reference_type', 'vehicle_cost')->whereIn('reference_id', $ids['costs'] ?: [0]))
            ->pluck('id')->all();

        return ['ids' => $ids, 'counts' => array_map('count', $ids)];
    }
}
