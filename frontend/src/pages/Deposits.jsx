import { useState } from 'react'
import { Link } from 'react-router-dom'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { rupiah, tanggal, today, unitLabel } from '../lib/format'
import MotorPicker from '../components/MotorPicker'
import PartyPicker from '../components/PartyPicker'
import { Alert, Badge, Button, Card, Input, MoneyInput, PageHeader, Pagination, Select, Table, Tabs, Textarea, useFlash } from '../components/ui'

export default function Deposits() {
  const [form, setForm] = useState({ motorcycle_id: '', unit_description: '', customer_id: '', deposit_date: today(), amount: '', notes: '' })
  const [adaUnit, setAdaUnit] = useState(true)
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [error, setError] = useState('')
  const [ok, setOk] = useFlash()
  const list = useFetch('/deposits', { page, status })

  const submit = async (e) => {
    e.preventDefault(); setError('')
    try {
      await api.post('/deposits', {
        ...form,
        motorcycle_id: adaUnit ? Number(form.motorcycle_id) : null,
        unit_description: adaUnit ? null : form.unit_description,
        customer_id: Number(form.customer_id),
      })
      setOk(adaUnit
        ? 'Uang muka tercatat. Motor berstatus dipesan dan tidak bisa dipesan pelanggan lain.'
        : 'Uang muka pesanan tercatat. Setelah unitnya dibeli, pilih uang muka ini di halaman Penjualan.')
      setForm({ ...form, motorcycle_id: '', unit_description: '', amount: '', notes: '' })
      list.reload()
    } catch (err) { setError(errorMessage(err)) }
  }

  const close = async (d, aksi) => {
    const teks = aksi === 'refund'
      ? `Kembalikan uang muka ${rupiah(d.amount)} kepada ${d.customer?.customer_name}? Kas berkurang dan motor kembali tersedia.`
      : `Nyatakan uang muka ${rupiah(d.amount)} hangus (pelanggan batal)? Nominal dicatat sebagai pendapatan lain-lain dan motor kembali tersedia.`
    if (!window.confirm(teks)) return
    setError('')
    try {
      await api.post(`/deposits/${d.id}/${aksi}`, { date: today() })
      setOk(aksi === 'refund' ? 'Uang muka dikembalikan.' : 'Uang muka dinyatakan hangus.')
      list.reload()
    } catch (err) { setError(errorMessage(err)) }
  }

  return (
    <div>
      <PageHeader
        title="Uang Muka Pelanggan"
      />
      <Alert>{error}</Alert>
      <Alert type="success">{ok}</Alert>
      <Card title="Terima uang muka">
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
          <div className="md:col-span-2">
            <Tabs
              tabs={[{ value: 'ada', label: 'Unit ada di showroom' }, { value: 'pesan', label: 'Unit belum ada (dicari dulu)' }]}
              value={adaUnit ? 'ada' : 'pesan'}
              onChange={(v) => setAdaUnit(v === 'ada')}
            />
          </div>
          <div className="md:col-span-2">
            {adaUnit
              ? <MotorPicker value={form.motorcycle_id} exclude={['dipesan']} onChange={(v) => setForm({ ...form, motorcycle_id: v })} />
              : <Input label="Unit yang dipesan" value={form.unit_description} onChange={(e) => setForm({ ...form, unit_description: e.target.value })} placeholder="Honda PCX 2021 warna hitam" required />}
          </div>
          <PartyPicker type="customer" value={form.customer_id} onChange={(v) => setForm({ ...form, customer_id: v })} />
          <Input label="Tanggal" type="date" value={form.deposit_date} onChange={(e) => setForm({ ...form, deposit_date: e.target.value })} required />
          <MoneyInput label="Nominal uang muka" value={form.amount} onChange={(v) => setForm({ ...form, amount: v })} required />
          <Textarea label="Keterangan" value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
          <div className="md:col-span-2 flex justify-end"><Button>Simpan uang muka</Button></div>
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
              : <span>{r.unit_description} <span className="text-xs text-violet-700">(unit belum ada)</span></span>) },
            { key: 'customer', label: 'Pelanggan', render: (r) => r.customer?.customer_name },
            { key: 'amount', label: 'Nominal', right: true, render: (r) => rupiah(r.amount) },
            { key: 'status', label: 'Status', render: (r) => <Badge>{r.status}</Badge> },
            {
              key: 'aksi', label: '', render: (r) => r.status === 'aktif' && (
                <span className="flex gap-1">
                  <Link to={r.motorcycle_id ? `/penjualan?motor=${r.motorcycle_id}` : `/penjualan?pelanggan=${r.customer_id}`}><Button variant="ghost" className="px-2 py-1">Jual</Button></Link>
                  <Button variant="ghost" className="px-2 py-1" onClick={() => close(r, 'refund')}>Kembalikan</Button>
                  <Button variant="ghost" className="px-2 py-1 text-rose-600" onClick={() => close(r, 'forfeit')}>Hangus</Button>
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
