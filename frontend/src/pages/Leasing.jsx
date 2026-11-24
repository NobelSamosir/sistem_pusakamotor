import { useState } from 'react'
import { Link } from 'react-router-dom'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { rupiah, tanggal, today, unitLabel } from '../lib/format'
import { useSave } from '../components/confirm'
import { Alert, Badge, Button, Card, Input, MoneyInput, PageHeader, Pagination, Select, StatCard, Table, useFlash } from '../components/ui'

export default function Leasing() {
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [error, setError] = useState('')
  const [ok, setOk] = useFlash()
  const [cair, setCair] = useState(null) // { sale, disbursed_at, commission }
  const [komisi, setKomisi] = useState(null) // { sale, received_at, amount }
  const list = useFetch('/leasing', { page, status })
  const dash = useFetch('/dashboard')
  const o = dash.data?.outstanding

  const save = useSave()
  const jalankan = (opts, fn, pesan, selesai) => save.run(opts, async () => {
    setError('')
    try { await fn(); setOk(pesan); selesai(); list.reload(); dash.reload() } catch (err) { setError(errorMessage(err)) }
  })

  const simpanCair = (e) => {
    e.preventDefault()
    const rows = [['Penjualan', cair.sale.sale_number], ['Leasing', cair.sale.leasing_company], ['Dana cair', rupiah(cair.sale.financed_amount)], ['Tanggal cair', tanggal(cair.disbursed_at)]]
    if (Number(cair.commission) > 0) rows.push(['Komisi', rupiah(cair.commission)])
    jalankan(
      { title: 'Catat pencairan dana leasing?', rows },
      () => api.post(`/leasing/${cair.sale.id}/disburse`, { disbursed_at: cair.disbursed_at, commission: cair.commission || null }),
      cair.commission ? 'Pencairan dan komisi tercatat.' : 'Pencairan tercatat: Kas (debit) / Piutang Perusahaan Pembiayaan (kredit).',
      () => setCair(null),
    )
  }

  const simpanKomisi = (e) => {
    e.preventDefault()
    jalankan(
      { title: 'Catat komisi leasing?', rows: [['Penjualan', komisi.sale.sale_number], ['Leasing', komisi.sale.leasing_company], ['Tanggal', tanggal(komisi.received_at)], ['Komisi', rupiah(komisi.amount)]] },
      () => api.post(`/leasing/${komisi.sale.id}/commission`, { received_at: komisi.received_at, amount: komisi.amount }),
      'Komisi leasing tercatat sebagai pendapatan.',
      () => setKomisi(null),
    )
  }


  return (
    <div>
      <PageHeader title="Penjualan Kredit (Leasing)" />
      <div className="mb-6 grid gap-4 md:grid-cols-3">
        <StatCard label="Piutang leasing belum cair" value={rupiah(o?.leasing_receivable)} note={`${o?.leasing_waiting ?? 0} transaksi menunggu`} tone="sky" />
        <StatCard label="Total transaksi kredit" value={list.data?.total ?? '-'} note="seluruh periode" />
        <StatCard label="Kas saat ini" value={rupiah(o?.cash)} tone="emerald" />
      </div>
      <Alert>{error}</Alert>
      <Alert type="success">{ok}</Alert>

      {cair && (
        <Card title={`Pencairan dari ${cair.sale.leasing_company} (${cair.sale.sale_number})`} className="mb-6">
          <form onSubmit={simpanCair} className="grid gap-4 md:grid-cols-4">
            <Input label="Tanggal cair" type="date" value={cair.disbursed_at} onChange={(e) => setCair({ ...cair, disbursed_at: e.target.value })} required />
            <div className="text-sm">
              <p className="mb-1.5 font-medium text-slate-700">Dana pokok</p>
              <p className="py-2 text-lg font-semibold">{rupiah(cair.sale.financed_amount)}</p>
            </div>
            <MoneyInput label="Komisi ikut cair (opsional)" hint="Kosongkan jika komisi dibayar terpisah" value={cair.commission} onChange={(v) => setCair({ ...cair, commission: v })} />
            <div className="flex items-end gap-2">
              <Button disabled={save.busy}>Simpan</Button>
              <Button type="button" variant="secondary" onClick={() => setCair(null)}>Batal</Button>
            </div>
          </form>
        </Card>
      )}

      {komisi && (
        <Card title={`Komisi dari ${komisi.sale.leasing_company} (${komisi.sale.sale_number})`} className="mb-6">
          <form onSubmit={simpanKomisi} className="grid gap-4 md:grid-cols-4">
            <Input label="Tanggal diterima" type="date" value={komisi.received_at} onChange={(e) => setKomisi({ ...komisi, received_at: e.target.value })} required />
            <MoneyInput label="Nominal komisi" value={komisi.amount} onChange={(v) => setKomisi({ ...komisi, amount: v })} required />
            <div className="flex items-end gap-2 md:col-span-2">
              <Button disabled={save.busy}>Simpan komisi</Button>
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
                  {r.disbursement_status === 'menunggu' && (
                    <Button variant="ghost" className="px-2 py-1" onClick={() => { setKomisi(null); setCair({ sale: r, disbursed_at: today(), commission: '' }) }}>Catat cair</Button>
                  )}
                  {!r.commission_received_at && (
                    <Button variant="ghost" className="px-2 py-1" onClick={() => { setCair(null); setKomisi({ sale: r, received_at: today(), amount: '' }) }}>+ Komisi</Button>
                  )}
                </span>
              ),
            },
          ]}
          rows={list.data?.data}
          empty="Belum ada penjualan kredit."
        />
        <Pagination meta={list.data} onPage={setPage} />
      </Card>

    </div>
  )
}
