import { useState } from 'react'
import { Link } from 'react-router-dom'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { rupiah, tanggal, today, unitLabel } from '../lib/format'
import { Alert, Badge, Button, Card, Input, MoneyInput, PageHeader, Pagination, Select, StatCard, Table, useFlash } from '../components/ui'

export default function Leasing() {
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [error, setError] = useState('')
  const [ok, setOk] = useFlash()
  const [komisi, setKomisi] = useState(null) // { sale, received_at, amount }
  const list = useFetch('/leasing', { page, status })
  const dash = useFetch('/dashboard')
  const rows = list.data?.data || []

  const cair = async (s) => {
    if (!window.confirm(`Dana ${rupiah(s.financed_amount)} dari ${s.leasing_company} sudah masuk ke kas/rekening showroom?`)) return
    setError('')
    try {
      await api.post(`/leasing/${s.id}/disburse`, { disbursed_at: today() })
      setOk('Pencairan tercatat: Kas (debit) / Piutang Perusahaan Pembiayaan (kredit).')
      list.reload(); dash.reload()
    } catch (err) { setError(errorMessage(err)) }
  }

  const simpanKomisi = async (e) => {
    e.preventDefault(); setError('')
    try {
      await api.post(`/leasing/${komisi.sale.id}/commission`, { received_at: komisi.received_at, amount: komisi.amount })
      setOk('Komisi leasing tercatat sebagai pendapatan.')
      setKomisi(null); list.reload()
    } catch (err) { setError(errorMessage(err)) }
  }

  const o = dash.data?.outstanding

  return (
    <div>
      <PageHeader
        title="Penjualan Kredit (Leasing)"
      />
      <div className="mb-6 grid gap-4 md:grid-cols-3">
        <StatCard label="Piutang leasing belum cair" value={rupiah(o?.leasing_receivable)} note={`${o?.leasing_waiting ?? 0} transaksi menunggu`} tone="sky" />
        <StatCard label="Total transaksi kredit" value={list.data?.total ?? '-'} note="seluruh periode" />
        <StatCard label="Kas saat ini" value={rupiah(o?.cash)} tone="emerald" />
      </div>
      <Alert>{error}</Alert>
      <Alert type="success">{ok}</Alert>
      {komisi && (
        <Card title={`Komisi dari ${komisi.sale.leasing_company} (${komisi.sale.sale_number})`} className="mb-6">
          <form onSubmit={simpanKomisi} className="grid gap-4 md:grid-cols-4">
            <Input label="Tanggal diterima" type="date" value={komisi.received_at} onChange={(e) => setKomisi({ ...komisi, received_at: e.target.value })} required />
            <MoneyInput label="Nominal komisi" value={komisi.amount} onChange={(v) => setKomisi({ ...komisi, amount: v })} required />
            <div className="flex items-end gap-2 md:col-span-2">
              <Button>Simpan komisi</Button>
              <Button type="button" variant="secondary" onClick={() => setKomisi(null)}>Batal</Button>
            </div>
          </form>
        </Card>
      )}
      <Card
        title="Daftar penjualan kredit"
        actions={<Select placeholder="Semua" options={[{ value: 'menunggu', label: 'Menunggu cair' }, { value: 'cair', label: 'Sudah cair' }]} value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }} className="w-44 -mt-1" />}
      >
        <Table
          columns={[
            { key: 'sale_number', label: 'No.' },
            { key: 'sale_date', label: 'Tanggal', render: (r) => tanggal(r.sale_date) },
            { key: 'unit', label: 'Unit', render: (r) => <Link className="text-teal-700" to={`/motor/${r.motorcycle_id}`}>{unitLabel(r.motorcycle)}</Link> },
            { key: 'customer', label: 'Pelanggan', render: (r) => r.customer?.customer_name },
            { key: 'leasing_company', label: 'Leasing' },
            { key: 'down_payment', label: 'DP', right: true, render: (r) => rupiah(r.down_payment) },
            { key: 'financed_amount', label: 'Dibiayai', right: true, render: (r) => rupiah(r.financed_amount) },
            { key: 'disbursement_status', label: 'Pencairan', render: (r) => (<><Badge>{r.disbursement_status}</Badge>{r.disbursed_at && <p className="text-xs text-slate-500">{tanggal(r.disbursed_at)}</p>}</>) },
            { key: 'leasing_commission', label: 'Komisi', right: true, render: (r) => (r.leasing_commission ? rupiah(r.leasing_commission) : '-') },
            {
              key: 'aksi', label: '', render: (r) => (
                <span className="flex gap-1">
                  {r.disbursement_status === 'menunggu' && <Button variant="ghost" className="px-2 py-1" onClick={() => cair(r)}>Tandai cair</Button>}
                  {!r.commission_received_at && <Button variant="ghost" className="px-2 py-1" onClick={() => setKomisi({ sale: r, received_at: today(), amount: '' })}>+ Komisi</Button>}
                </span>
              ),
            },
          ]}
          rows={rows}
          empty="Belum ada penjualan kredit."
        />
        <Pagination meta={list.data} onPage={setPage} />
      </Card>
    </div>
  )
}
