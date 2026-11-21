import { useState } from 'react'
import { Link } from 'react-router-dom'
import useFetch from '../lib/useFetch'
import { rupiah, tanggal } from '../lib/format'
import { Alert, Badge, Button, Card, Loading, PageHeader, Pagination, Select, Table } from '../components/ui'

export default function Motors() {
  const [q, setQ] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const { data, loading, error } = useFetch('/motorcycles', { q: search, status, page })

  return (
    <div>
      <PageHeader
        title="Data Motor"
        subtitle="Setiap unit tercatat lewat pembelian dan dikenali dari nomor rangka serta nomor mesin."
        actions={<Link to="/pembelian"><Button>+ Catat pembelian</Button></Link>}
      />
      <Card>
        <form className="mb-4 flex flex-wrap items-end gap-3" onSubmit={(e) => { e.preventDefault(); setPage(1); setSearch(q) }}>
          <div className="min-w-64 flex-1">
            <label>Cari</label>
            <input className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nomor rangka, nomor mesin, kode, model, nopol" value={q} onChange={(e) => setQ(e.target.value)} />
          </div>
          <Select label="Status" placeholder="Semua" options={['tersedia', 'perbaikan', 'terjual']} value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }} className="w-44" />
          <Button variant="secondary">Cari</Button>
        </form>
        <Alert>{error}</Alert>
        {loading ? <Loading /> : (
          <>
            <Table
              columns={[
                { key: 'vehicle_code', label: 'Kode', render: (r) => <Link to={`/motor/${r.id}`} className="font-medium text-teal-700">{r.vehicle_code}</Link> },
                { key: 'unit', label: 'Unit', render: (r) => `${r.brand} ${r.model} ${r.year}` },
                { key: 'frame_number', label: 'Nomor rangka' },
                { key: 'license_plate', label: 'Nopol', render: (r) => r.license_plate || '-' },
                { key: 'date', label: 'Tgl beli', render: (r) => tanggal(r.purchase?.purchase_date) },
                { key: 'price', label: 'Harga beli', right: true, render: (r) => rupiah(r.purchase?.purchase_price) },
                { key: 'status', label: 'Status', render: (r) => <Badge>{r.status}</Badge> },
              ]}
              rows={data?.data}
            />
            <Pagination meta={data} onPage={setPage} />
          </>
        )}
      </Card>
    </div>
  )
}
