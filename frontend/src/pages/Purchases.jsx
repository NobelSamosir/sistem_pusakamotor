import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { rupiah, tanggal, today } from '../lib/format'
import MotorFields, { emptyMotor, motorPayload } from '../components/MotorFields'
import PartyPicker from '../components/PartyPicker'
import { Alert, Button, Card, Input, MoneyInput, PageHeader, Pagination, Select, Table, Textarea } from '../components/ui'

const emptyIdentity = { frame_number: '', engine_number: '', license_plate: '', bpkb_number: '', color: '', condition_notes: '' }

export default function Purchases() {
  const navigate = useNavigate()
  const fromDss = useLocation().state // dikirim dari halaman Rekomendasi Harga (DSS beli)
  const [motor, setMotor] = useState(fromDss?.motor || emptyMotor)
  const [ident, setIdent] = useState(emptyIdentity)
  const [form, setForm] = useState({
    supplier_id: '', purchase_date: today(), purchase_price: fromDss?.price || '', payment_method: 'tunai', notes: '',
  })
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [page, setPage] = useState(1)
  const list = useFetch('/purchases', { page })

  const setI = (k) => (e) => setIdent({ ...ident, [k]: e.target.value })

  const submit = async (e) => {
    e.preventDefault()
    setBusy(true); setError('')
    try {
      const { data } = await api.post('/purchases', {
        motorcycle: { ...motorPayload(motor), ...ident },
        ...form,
        supplier_id: Number(form.supplier_id),
        recommendation_id: fromDss?.recommendationId || null,
      })
      navigate(`/motor/${data.motorcycle_id}`)
    } catch (err) { setError(errorMessage(err)) } finally { setBusy(false) }
  }

  return (
    <div>
      <PageHeader title="Pembelian Motor" />
      {fromDss && (
        <Alert type="info">
          Data diisi dari hasil DSS beli ({fromDss.status}). Lengkapi nomor rangka, nomor mesin, dan pemasok, lalu simpan.
        </Alert>
      )}
      <form onSubmit={submit} className="space-y-6">
        <Card title="Identitas unit">
          <div className="grid gap-4 md:grid-cols-3">
            <Input label="Nomor rangka" value={ident.frame_number} onChange={setI('frame_number')} required hint="Otomatis jadi huruf besar tanpa spasi" />
            <Input label="Nomor mesin" value={ident.engine_number} onChange={setI('engine_number')} required />
            <Input label="Nomor polisi" value={ident.license_plate} onChange={setI('license_plate')} />
            <Input label="Nomor BPKB" value={ident.bpkb_number} onChange={setI('bpkb_number')} />
            <Input label="Warna" value={ident.color} onChange={setI('color')} />
            <Textarea label="Catatan kondisi" value={ident.condition_notes} onChange={setI('condition_notes')} />
          </div>
        </Card>
        <Card title="Karakteristik motor (fitur model)">
          <MotorFields value={motor} onChange={setMotor} />
        </Card>
        <Card title="Transaksi pembelian">
          <Alert>{error}</Alert>
          <div className="grid gap-4 md:grid-cols-2">
            <PartyPicker type="supplier" value={form.supplier_id} onChange={(v) => setForm({ ...form, supplier_id: v })} />
            <Input label="Tanggal beli" type="date" value={form.purchase_date} onChange={(e) => setForm({ ...form, purchase_date: e.target.value })} required />
            <MoneyInput label="Harga beli" value={form.purchase_price} onChange={(v) => setForm({ ...form, purchase_price: v })} required />
            <Select label="Metode bayar" options={[{ value: 'tunai', label: 'Tunai' }, { value: 'transfer', label: 'Transfer' }]} value={form.payment_method} onChange={(e) => setForm({ ...form, payment_method: e.target.value })} />
            <Textarea label="Keterangan" className="md:col-span-2" value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
          </div>
          <div className="mt-5 flex justify-end">
            <Button disabled={busy}>{busy ? 'Menyimpan...' : 'Simpan pembelian'}</Button>
          </div>
        </Card>
      </form>

      <Card title="Riwayat pembelian" className="mt-6">
        <Table
          columns={[
            { key: 'purchase_number', label: 'No.' },
            { key: 'purchase_date', label: 'Tanggal', render: (r) => tanggal(r.purchase_date) },
            { key: 'unit', label: 'Unit', render: (r) => <Link className="text-teal-700" to={`/motor/${r.motorcycle_id}`}>{r.motorcycle.brand} {r.motorcycle.model} {r.motorcycle.year}</Link> },
            { key: 'supplier', label: 'Pemasok', render: (r) => r.supplier?.supplier_name },
            { key: 'purchase_price', label: 'Harga', right: true, render: (r) => rupiah(r.purchase_price) },
          ]}
          rows={list.data?.data}
        />
        <Pagination meta={list.data} onPage={setPage} />
      </Card>
    </div>
  )
}
