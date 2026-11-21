import { Link } from 'react-router-dom'
import { Bar, BarChart, CartesianGrid, Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import useFetch from '../lib/useFetch'
import { rupiah, tanggal } from '../lib/format'
import { Alert, Badge, Card, Loading, PageHeader, StatCard, Table } from '../components/ui'

const bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
const juta = (v) => (v / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt'

export default function Dashboard() {
  const { data: d, loading, error } = useFetch('/dashboard')
  if (loading) return <Loading />
  if (error) return <Alert>{error}</Alert>

  const chart = d.chart.map((c) => ({
    bulan: bulan[Number(c.month.slice(5)) - 1],
    Penjualan: Number(c.sales_amount),
    Pembelian: Number(c.purchase_amount),
  }))
  const status = [
    { name: 'Tersedia', value: d.motorcycles.tersedia, color: '#0f766e' },
    { name: 'Perbaikan', value: d.motorcycles.perbaikan, color: '#d97706' },
    { name: 'Terjual', value: d.motorcycles.terjual, color: '#94a3b8' },
  ].filter((s) => s.value > 0)

  return (
    <div>
      <PageHeader title="Dashboard" subtitle={`Ringkasan bulan ${bulan[Number(d.month.slice(5)) - 1]} ${d.month.slice(0, 4)}`} />
      <div className="grid gap-4 md:grid-cols-4">
        <StatCard label="Total motor" value={d.motorcycles.total} note={`${d.motorcycles.tersedia} tersedia di stok`} />
        <StatCard label="Pembelian bulan ini" value={rupiah(d.this_month.purchase_amount)} note={`${d.this_month.purchase_units} unit`} tone="sky" />
        <StatCard label="Penjualan bulan ini" value={rupiah(d.this_month.sale_amount)} note={`${d.this_month.sale_units} unit`} tone="emerald" />
        <StatCard label="Laba bersih bulan ini" value={rupiah(d.this_month.net_profit)} note="dari laporan laba rugi" tone="amber" />
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-3">
        <Card title="Penjualan dan pembelian 12 bulan terakhir" className="lg:col-span-2">
          <div className="h-72">
            <ResponsiveContainer>
              <BarChart data={chart}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
                <XAxis dataKey="bulan" tick={{ fontSize: 12 }} />
                <YAxis tickFormatter={juta} tick={{ fontSize: 12 }} width={60} />
                <Tooltip formatter={(v) => rupiah(v)} />
                <Legend />
                <Bar dataKey="Penjualan" fill="#0f766e" radius={[4, 4, 0, 0]} />
                <Bar dataKey="Pembelian" fill="#94a3b8" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </Card>
        <Card title="Status motor">
          {status.length === 0 ? <p className="text-sm text-slate-400">Belum ada motor.</p> : (
            <div className="h-72">
              <ResponsiveContainer>
                <PieChart>
                  <Pie data={status} dataKey="value" nameKey="name" innerRadius={55} outerRadius={90} paddingAngle={2}>
                    {status.map((s) => <Cell key={s.name} fill={s.color} />)}
                  </Pie>
                  <Tooltip />
                  <Legend />
                </PieChart>
              </ResponsiveContainer>
            </div>
          )}
        </Card>
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <Card title="Motor terbaru" actions={<Link to="/motor" className="text-sm text-teal-700">Lihat semua</Link>}>
          <Table
            columns={[
              { key: 'vehicle_code', label: 'Kode', render: (r) => <Link to={`/motor/${r.id}`} className="text-teal-700">{r.vehicle_code}</Link> },
              { key: 'unit', label: 'Unit', render: (r) => `${r.brand} ${r.model} ${r.year}` },
              { key: 'status', label: 'Status', render: (r) => <Badge>{r.status}</Badge> },
            ]}
            rows={d.latest_motorcycles}
          />
        </Card>
        <Card title="Penjualan terbaru">
          <Table
            columns={[
              { key: 'sale_date', label: 'Tanggal', render: (r) => tanggal(r.sale_date) },
              { key: 'unit', label: 'Unit', render: (r) => `${r.brand} ${r.model}` },
              { key: 'sale_price', label: 'Harga', right: true, render: (r) => rupiah(r.sale_price) },
              { key: 'gross_profit', label: 'Laba', right: true, render: (r) => rupiah(r.gross_profit) },
            ]}
            rows={d.latest_sales}
            empty="Belum ada penjualan."
          />
        </Card>
      </div>
    </div>
  )
}
