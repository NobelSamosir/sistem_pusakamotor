import { useState } from 'react'
import { Link } from 'react-router-dom'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { rupiah, tanggal, today, unitLabel } from '../lib/format'
import MotorPicker from '../components/MotorPicker'
import IdentityFields, { emptyIdentity, identityPayload } from '../components/IdentityFields'
import { RowActions, useSave } from '../components/confirm'
import { Alert, Badge, Button, Card, Input, MoneyInput, PageHeader, Pagination, Select, Table, Textarea, useFlash } from '../components/ui'

const kosong = () => ({ motorcycle_id: '', deposit_date: today(), amount: '', notes: '' })

export default function Deposits() {
  const [form, setForm] = useState(kosong())
  const [buyer, setBuyer] = useState(emptyIdentity)
  const [edit, setEdit] = useState(null)
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [error, setError] = useState('')
  const [ok, setOk] = useFlash()
  const list = useFetch('/deposits', { page, status })
  const { busy, run } = useSave()

  const reset = () => { setForm({ ...kosong(), deposit_date: form.deposit_date }); setBuyer(emptyIdentity); setEdit(null) }

  const submit = (e) => {
    e.preventDefault(); setError('')
    const rows = [['Tanggal', tanggal(form.deposit_date)], ['Pembeli', buyer.name], ['Nominal', rupiah(form.amount)]]
    if (edit) rows.unshift(['Unit', `${edit.motorcycle?.vehicle_code} · ${unitLabel(edit.motorcycle)}`])
    run({ title: edit ? 'Simpan perubahan uang muka?' : 'Simpan uang muka?', rows }, async () => {
      try {
        if (edit) {
          await api.put(`/deposits/${edit.id}`, { deposit_date: form.deposit_date, amount: form.amount, notes: form.notes, buyer: identityPayload(buyer) })
          setOk('Uang muka diubah dan jurnalnya disesuaikan.')
        } else {
          await api.post('/deposits', { ...form, motorcycle_id: Number(form.motorcycle_id), buyer: identityPayload(buyer) })
          setOk('Uang muka tercatat. Motor berstatus dipesan dan tidak bisa dipesan pelanggan lain.')
        }
        reset(); list.reload()
      } catch (err) { setError(errorMessage(err)) }
    })
  }

  const ubah = (r) => {
    setEdit(r)
    setForm({ motorcycle_id: r.motorcycle_id, deposit_date: r.deposit_date.slice(0, 10), amount: Number(r.amount), notes: r.notes || '' })
    setBuyer({ ...emptyIdentity, id: r.customer_id, name: r.customer?.customer_name || '', phone: r.customer?.phone || '' })
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const baris = (d) => [['Pelanggan', d.customer?.customer_name], ['Unit', `${d.motorcycle?.vehicle_code || ''} · ${unitLabel(d.motorcycle)}`], ['Nominal', rupiah(d.amount)]]

  const hapus = (d) => run({ title: 'Hapus uang muka ini?', danger: true, okLabel: 'Ya, hapus', rows: baris(d), message: 'Pakai ini hanya untuk data yang salah input. Jurnalnya ikut dihapus dan motor kembali tersedia.' }, async () => {
    setError('')
    try { await api.delete(`/deposits/${d.id}`); setOk('Uang muka dan jurnalnya sudah dihapus.'); if (edit?.id === d.id) reset(); list.reload() } catch (err) { setError(errorMessage(err)) }
  })

  const close = (d, aksi) => run({
    title: aksi === 'refund' ? 'Kembalikan uang muka ke pelanggan?' : 'Nyatakan uang muka hangus?',
    danger: aksi !== 'refund',
    okLabel: aksi === 'refund' ? 'Ya, kembalikan' : 'Ya, hangus',
    rows: baris(d),
    message: aksi === 'refund'
      ? 'Kas berkurang dan motor kembali tersedia.'
      : 'Nominal dicatat sebagai pendapatan lain-lain dan motor kembali tersedia.',
  }, async () => {
    setError('')
    try {
      await api.post(`/deposits/${d.id}/${aksi}`, { date: today() })
      setOk(aksi === 'refund' ? 'Uang muka dikembalikan.' : 'Uang muka dinyatakan hangus.')
      list.reload()
    } catch (err) { setError(errorMessage(err)) }
  })

  return (
    <div>
      <PageHeader title="Uang Muka Pelanggan" />
      <Alert>{error}</Alert>
      <Alert type="success">{ok}</Alert>
      <Card title={edit ? `Ubah uang muka ${edit.deposit_number}` : 'Terima uang muka'}>
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
          <div className="md:col-span-2">
            {edit
              ? <Input label="Unit" value={`${edit.motorcycle?.vehicle_code} · ${unitLabel(edit.motorcycle)}`} readOnly disabled />
              : <MotorPicker value={form.motorcycle_id} exclude={['dipesan']} onChange={(v) => setForm({ ...form, motorcycle_id: v })} />}
          </div>
          <div className="md:col-span-2"><IdentityFields kind="customer" title="Identitas pembeli" value={buyer} onChange={setBuyer} /></div>
          <Input label="Tanggal" type="date" value={form.deposit_date} onChange={(e) => setForm({ ...form, deposit_date: e.target.value })} required />
          <MoneyInput label="Nominal uang muka" value={form.amount} onChange={(v) => setForm({ ...form, amount: v })} required />
          <Textarea label="Keterangan" value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
          <div className="md:col-span-2 flex justify-end gap-2">
            {edit && <Button type="button" variant="secondary" onClick={reset}>Batal ubah</Button>}
            <Button disabled={busy}>{busy ? 'Menyimpan...' : edit ? 'Simpan perubahan' : 'Simpan uang muka'}</Button>
          </div>
        </form>
      </Card>
      <Card
        title="Daftar uang muka"
        className="mt-6"
        actions={<Select placeholder="Semua status" options={['aktif', 'dipakai', 'dikembalikan', 'hangus']} value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }} className="w-44 -mt-1" />}
      >
        <Table
          columns={[
            { key: 'deposit_number', label: 'No.' },
            { key: 'deposit_date', label: 'Tanggal', render: (r) => tanggal(r.deposit_date) },
            { key: 'unit', label: 'Unit', render: (r) => (r.motorcycle_id
              ? <Link className="text-teal-700" to={`/motor/${r.motorcycle_id}`}>{r.motorcycle?.vehicle_code} · {unitLabel(r.motorcycle)}</Link>
              : <span>{r.unit_description || '-'}</span>) },
            { key: 'customer', label: 'Pelanggan', render: (r) => r.customer?.customer_name },
            { key: 'amount', label: 'Nominal', right: true, render: (r) => rupiah(r.amount) },
            { key: 'status', label: 'Status', render: (r) => <Badge>{r.status}</Badge> },
            {
              key: 'aksi', label: '', render: (r) => (
                <span className="flex items-center justify-end gap-1 whitespace-nowrap">
                  {r.status === 'aktif' && r.motorcycle_id && <Link to={`/penjualan?motor=${r.motorcycle_id}`}><Button variant="ghost" className="px-2 py-1 text-xs">Jual</Button></Link>}
                  {r.status === 'aktif' && <Button variant="ghost" className="px-2 py-1 text-xs" disabled={busy} onClick={() => close(r, 'refund')}>Kembalikan</Button>}
                  {r.status === 'aktif' && <Button variant="ghost" className="px-2 py-1 text-xs" disabled={busy} onClick={() => close(r, 'forfeit')}>Hangus</Button>}
                  {r.status !== 'dipakai' && <RowActions disabled={busy} onEdit={r.status === 'aktif' ? () => ubah(r) : null} onDelete={() => hapus(r)} />}
                </span>
              ),
            },
          ]}
          rows={list.data?.data}
          empty="Belum ada uang muka."
        />
        <Pagination meta={list.data} onPage={setPage} />
      </Card>
    </div>
  )
}
