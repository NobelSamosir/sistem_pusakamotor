import { useState } from 'react'
import { Link } from 'react-router-dom'
import useFetch from '../lib/useFetch'
import { monthEnd, monthStart, persen, rupiah, tanggal, today } from '../lib/format'
import { Alert, Button, Card, Input, Loading, PageHeader, Table, Tabs } from '../components/ui'

const tabs = [
  { value: 'stock', label: 'Stok kendaraan' },
  { value: 'purchases-sales', label: 'Pembelian dan penjualan' },
  { value: 'income-statement', label: 'Laba rugi' },
  { value: 'balance-sheet', label: 'Neraca' },
  { value: 'cash-flow', label: 'Arus kas' },
]

const Line = ({ label, value, bold, indent, negative }) => (
  <div className={`flex justify-between border-b border-slate-100 py-2 text-sm ${bold ? 'font-semibold text-slate-900' : 'text-slate-700'}`}>
    <span className={indent ? 'pl-6' : ''}>{label}</span>
    <span className="tabular-nums">{negative && Number(value) > 0 ? `(${rupiah(value)})` : rupiah(value)}</span>
  </div>
)

const Signed = ({ label, value, bold }) => {
  const v = Number(value)
  return (
    <div className={`flex justify-between border-b border-slate-100 py-2 text-sm ${bold ? 'font-semibold' : ''}`}>
      <span className={bold ? '' : 'pl-6'}>{label}</span>
      <span className="tabular-nums">{v < 0 ? `(${rupiah(-v)})` : rupiah(v)}</span>
    </div>
  )
}

function Stock({ d }) {
  return (
    <Table
      columns={[
        { key: 'vehicle_code', label: 'Kode', render: (r) => <Link className="text-teal-700" to={`/motor/${r.id}`}>{r.vehicle_code}</Link> },
        { key: 'unit', label: 'Unit' },
        { key: 'frame_number', label: 'Nomor rangka' },
        { key: 'status', label: 'Status' },
        { key: 'purchase_date', label: 'Tgl beli', render: (r) => tanggal(r.purchase_date) },
        { key: 'days_in_stock', label: 'Lama di stok', right: true, render: (r) => `${r.days_in_stock} hari` },
        { key: 'purchase_price', label: 'Harga beli', right: true, render: (r) => rupiah(r.purchase_price) },
        { key: 'hpp_costs', label: 'Biaya HPP', right: true, render: (r) => rupiah(r.hpp_costs) },
        { key: 'hpp', label: 'HPP', right: true, render: (r) => rupiah(r.hpp) },
      ]}
      rows={d.items}
      empty="Tidak ada motor di stok."
      footer={<tfoot><tr className="font-semibold"><td className="px-3 py-2" colSpan={8}>Total {d.total_units} unit</td><td className="px-3 py-2 text-right">{rupiah(d.total_hpp)}</td></tr></tfoot>}
    />
  )
}

function PurchasesSales({ d }) {
  return (
    <div className="space-y-6">
      <div>
        <h3 className="mb-2 font-semibold">Pembelian ({d.totals.purchase_count} unit)</h3>
        <Table
          columns={[
            { key: 'purchase_number', label: 'No.' },
            { key: 'purchase_date', label: 'Tanggal', render: (r) => tanggal(r.purchase_date) },
            { key: 'unit', label: 'Unit' },
            { key: 'frame_number', label: 'Nomor rangka' },
            { key: 'supplier_name', label: 'Pemasok' },
            { key: 'purchase_price', label: 'Harga beli', right: true, render: (r) => rupiah(r.purchase_price) },
          ]}
          rows={d.purchases}
          footer={<tfoot><tr className="font-semibold"><td className="px-3 py-2" colSpan={5}>Total pembelian</td><td className="px-3 py-2 text-right">{rupiah(d.totals.purchase_amount)}</td></tr></tfoot>}
        />
      </div>
      <div>
        <h3 className="mb-2 font-semibold">Penjualan ({d.totals.sale_count} unit)</h3>
        <Table
          columns={[
            { key: 'sale_number', label: 'No.' },
            { key: 'sale_date', label: 'Tanggal', render: (r) => tanggal(r.sale_date) },
            { key: 'unit', label: 'Unit' },
            { key: 'customer_name', label: 'Pelanggan' },
            { key: 'sale_price', label: 'Harga jual', right: true, render: (r) => rupiah(r.sale_price) },
            { key: 'hpp_amount', label: 'HPP', right: true, render: (r) => rupiah(r.hpp_amount) },
            { key: 'gross_profit', label: 'Laba', right: true, render: (r) => rupiah(r.gross_profit) },
            { key: 'margin', label: 'Margin', right: true, render: (r) => persen(r.margin) },
          ]}
          rows={d.sales}
          footer={<tfoot><tr className="font-semibold"><td className="px-3 py-2" colSpan={4}>Total penjualan</td><td className="px-3 py-2 text-right">{rupiah(d.totals.sale_amount)}</td><td className="px-3 py-2 text-right">{rupiah(d.totals.hpp_amount)}</td><td className="px-3 py-2 text-right">{rupiah(d.totals.gross_profit)}</td><td /></tr></tfoot>}
        />
      </div>
    </div>
  )
}

function Income({ d }) {
  return (
    <div className="max-w-xl">
      <Line label="Pendapatan penjualan kendaraan" value={d.revenue} bold />
      <Line label="Harga pokok penjualan (HPP)" value={d.cogs} indent negative />
      <Line label="Laba kotor" value={d.gross_profit} bold />
      <Line label="Beban operasional" value={d.operating_expense} indent negative />
      <div className="mt-2 flex justify-between rounded-lg bg-teal-50 px-3 py-3 font-semibold text-teal-900">
        <span>Laba bersih</span><span className="tabular-nums">{rupiah(d.net_profit)}</span>
      </div>
    </div>
  )
}

function Balance({ d }) {
  return (
    <div>
      <div className="grid gap-8 md:grid-cols-2">
        <div>
          <h3 className="mb-2 font-semibold">Aset</h3>
          <Line label="Kas" value={d.assets.cash} indent />
          <Line label="Persediaan kendaraan" value={d.assets.inventory} indent />
          <Line label="Total aset" value={d.assets.total} bold />
        </div>
        <div>
          <h3 className="mb-2 font-semibold">Ekuitas</h3>
          <Line label="Modal pemilik" value={d.equity.capital} indent />
          <Line label="Laba ditahan / periode berjalan" value={d.equity.retained_profit} indent />
          <Line label="Total ekuitas" value={d.equity.total} bold />
        </div>
      </div>
      <p className={`mt-4 rounded-lg px-3 py-2 text-sm font-medium ${d.is_balanced ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`}>
        {d.is_balanced ? 'Seimbang: total aset sama dengan total ekuitas.' : 'Tidak seimbang, periksa jurnal.'}
      </p>
    </div>
  )
}

function CashFlow({ d }) {
  return (
    <div className="max-w-xl">
      <h3 className="mb-1 font-semibold">Arus kas dari aktivitas operasi</h3>
      <Signed label="Penerimaan dari penjualan motor" value={d.operating.sales_receipts} />
      <Signed label="Pembayaran pembelian motor" value={d.operating.motorcycle_purchases} />
      <Signed label="Pembayaran biaya kendaraan" value={d.operating.vehicle_costs} />
      <Signed label="Pembayaran beban operasional" value={d.operating.operating_expenses} />
      <Signed label="Arus kas bersih aktivitas operasi" value={d.operating.net} bold />
      <h3 className="mb-1 mt-5 font-semibold">Arus kas dari aktivitas pendanaan</h3>
      <Signed label="Setoran modal pemilik" value={d.financing.capital_contributions} />
      <Signed label="Arus kas bersih aktivitas pendanaan" value={d.financing.net} bold />
      <div className="mt-5" />
      <Signed label="Kenaikan (penurunan) kas" value={d.net_change} bold />
      <Signed label="Kas awal periode" value={d.opening_cash} bold />
      <div className="mt-2 flex justify-between rounded-lg bg-teal-50 px-3 py-3 font-semibold text-teal-900">
        <span>Kas akhir periode</span><span className="tabular-nums">{rupiah(d.closing_cash)}</span>
      </div>
    </div>
  )
}

export default function Reports() {
  const [tab, setTab] = useState('income-statement')
  const [from, setFrom] = useState(monthStart())
  const [to, setTo] = useState(monthEnd())
  const [date, setDate] = useState(today())
  const params = tab === 'balance-sheet' ? { date } : tab === 'stock' ? {} : { from, to }
  const { data, loading, error } = useFetch(`/reports/${tab}`, params)
  const title = tabs.find((t) => t.value === tab).label
  const periode = tab === 'balance-sheet' ? `Per ${tanggal(date)}` : tab === 'stock' ? `Per ${tanggal(today())}` : `Periode ${tanggal(from)} sampai ${tanggal(to)}`

  return (
    <div>
      <PageHeader title="Laporan" subtitle="Semua laporan keuangan dihitung dari jurnal umum." actions={<Button variant="secondary" onClick={() => window.print()}>Cetak / simpan PDF</Button>} />
      <Tabs tabs={tabs} value={tab} onChange={setTab} />
      <Card>
        <div className="no-print mb-5 flex flex-wrap items-end gap-3">
          {tab === 'balance-sheet' && <Input label="Per tanggal" type="date" value={date} onChange={(e) => setDate(e.target.value)} />}
          {!['balance-sheet', 'stock'].includes(tab) && (
            <>
              <Input label="Dari" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
              <Input label="Sampai" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
            </>
          )}
        </div>
        <div className="mb-5 text-center">
          <p className="font-semibold text-slate-800">Showroom Pusaka Motor</p>
          <p className="text-lg font-semibold">Laporan {title}</p>
          <p className="text-sm text-slate-500">{periode}</p>
        </div>
        <Alert>{error}</Alert>
        {loading || !data ? <Loading /> : (
          <>
            {tab === 'stock' && <Stock d={data} />}
            {tab === 'purchases-sales' && <PurchasesSales d={data} />}
            {tab === 'income-statement' && <Income d={data} />}
            {tab === 'balance-sheet' && <Balance d={data} />}
            {tab === 'cash-flow' && <CashFlow d={data} />}
          </>
        )}
      </Card>
    </div>
  )
}
