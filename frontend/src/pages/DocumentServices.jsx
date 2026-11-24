import { useState } from 'react'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { rupiah, tanggal, today } from '../lib/format'
import IdentityFields, { emptyIdentity, identityPayload } from '../components/IdentityFields'
import { RowActions, useSave } from '../components/confirm'
import { Alert, Button, Card, Input, MoneyInput, PageHeader, Pagination, Table, useFlash } from '../components/ui'

const kosong = () => ({ service_date: today(), description: '', amount_received: '', amount_paid: '' })

export default function DocumentServices() {
  const [form, setForm] = useState(kosong())
  const [cust, setCust] = useState(emptyIdentity)
  const [editId, setEditId] = useState(null)
  const [page, setPage] = useState(1)
  const [error, setError] = useState('')
  const [ok, setOk] = useFlash()
  const list = useFetch('/document-services', { page })
  const { busy, run } = useSave()
  const pendapatan = Number(form.amount_received || 0) - Number(form.amount_paid || 0)
  const ringkas = (f, nama) => [['Tanggal', tanggal(f.service_date)], ['Keterangan', f.description], ['Pelanggan', nama], ['Uang diterima', rupiah(f.amount_received)], ['Disetor / biaya', rupiah(f.amount_paid || 0)], ['Pendapatan', rupiah(Number(f.amount_received) - Number(f.amount_paid || 0))]]

  const reset = () => { setForm({ ...kosong(), service_date: form.service_date }); setCust(emptyIdentity); setEditId(null) }

  const submit = (e) => {
    e.preventDefault(); setError('')
    run({ title: editId ? 'Simpan perubahan?' : 'Simpan pendapatan ini?', rows: ringkas(form, cust.name) }, async () => {
      try {
        const body = { ...form, amount_paid: form.amount_paid || 0, customer: cust.name.trim() ? identityPayload(cust) : null }
        if (editId) await api.put(`/document-services/${editId}`, body)
        else await api.post('/document-services', body)
        setOk(`${rupiah(pendapatan)} tercatat sebagai pendapatan lain-lain.`)
        reset(); list.reload()
      } catch (err) { setError(errorMessage(err)) }
    })
  }

  const ubah = (r) => {
    setEditId(r.id)
    setForm({ service_date: r.service_date.slice(0, 10), description: r.description || '', amount_received: Number(r.amount_received), amount_paid: Number(r.amount_paid) || '' })
    setCust(r.customer ? { ...emptyIdentity, id: r.customer_id, name: r.customer.customer_name } : emptyIdentity)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
  const hapus = (r) => run({ title: 'Hapus pendapatan ini?', danger: true, okLabel: 'Ya, hapus', message: 'Jurnalnya ikut dihapus.', rows: ringkas(r, r.customer?.customer_name) }, async () => {
    setError('')
    try { await api.delete(`/document-services/${r.id}`); setOk('Pendapatan dan jurnalnya sudah dihapus.'); if (editId === r.id) reset(); list.reload() } catch (err) { setError(errorMessage(err)) }
  })

  return (
    <div>
      <PageHeader title="Pendapatan Lain-lain" />
      <Alert>{error}</Alert>
      <Alert type="success">{ok}</Alert>
      <Card title={editId ? 'Ubah pendapatan lain' : 'Catat pendapatan lain'}>
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-3">
          <Input label="Tanggal" type="date" value={form.service_date} onChange={(e) => setForm({ ...form, service_date: e.target.value })} required />
          <Input
            label="Keterangan pendapatan"
            className="md:col-span-2"
            value={form.description}
            onChange={(e) => setForm({ ...form, description: e.target.value })}
            placeholder="Contoh: jasa bayar pajak motor Pak Joko, bonus tahunan Adira, komisi pinjaman BPKB"
            required
          />
          <MoneyInput label="Uang diterima" value={form.amount_received} onChange={(v) => setForm({ ...form, amount_received: v })} required />
          <MoneyInput label="Disetor / biaya (opsional)" hint="Misalnya uang pajak yang disetor ke Samsat" value={form.amount_paid} onChange={(v) => setForm({ ...form, amount_paid: v })} />
          <div className="rounded-lg bg-teal-50 p-3">
            <p className="text-xs text-teal-800">Pendapatan lain-lain</p>
            <p className={`text-lg font-semibold ${pendapatan < 0 ? 'text-rose-600' : 'text-teal-900'}`}>{rupiah(pendapatan)}</p>
          </div>
          <div className="md:col-span-3">
            <IdentityFields kind="customer" title="Pelanggan (opsional)" value={cust} onChange={setCust} required={false} compact />
          </div>
          <div className="md:col-span-3 flex justify-end gap-2">
            {editId && <Button type="button" variant="secondary" onClick={reset}>Batal ubah</Button>}
            <Button disabled={busy}>{busy ? 'Menyimpan...' : editId ? 'Simpan perubahan' : 'Simpan'}</Button>
          </div>
        </form>
      </Card>
      <Card title="Riwayat pendapatan lain" className="mt-6">
        <Table
          columns={[
            { key: 'service_number', label: 'No.' },
            { key: 'service_date', label: 'Tanggal', render: (r) => tanggal(r.service_date) },
            { key: 'description', label: 'Keterangan', render: (r) => r.description || '-' },
            { key: 'customer', label: 'Pelanggan', render: (r) => r.customer?.customer_name || '-' },
            { key: 'amount_received', label: 'Diterima', right: true, render: (r) => rupiah(r.amount_received) },
            { key: 'amount_paid', label: 'Disetor', right: true, render: (r) => rupiah(r.amount_paid) },
            { key: 'fee', label: 'Pendapatan', right: true, render: (r) => rupiah(r.fee) },
            { key: 'aksi', label: '', render: (r) => <RowActions disabled={busy} onEdit={() => ubah(r)} onDelete={() => hapus(r)} /> },
          ]}
          rows={list.data?.data}
          empty="Belum ada pendapatan lain."
        />
        <Pagination meta={list.data} onPage={setPage} />
      </Card>
    </div>
  )
}
