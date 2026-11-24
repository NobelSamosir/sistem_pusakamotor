import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { rupiah, tanggal, today } from '../lib/format'
import MotorFields, { emptyMotor, motorPayload } from '../components/MotorFields'
import IdentityFields, { emptyIdentity as emptyParty, identityPayload } from '../components/IdentityFields'
import { Alert, Button, Card, Input, MoneyInput, PageHeader, Pagination, Select, Table, Textarea } from '../components/ui'

const emptyIdentity = { frame_number: '', engine_number: '', license_plate: '', bpkb_number: '', color: '', condition_notes: '' }

export default function Purchases() {
  const navigate = useNavigate()
  const fromDss = useLocation().state // dikirim dari halaman Rekomendasi Harga (DSS beli)
  const [motor, setMotor] = useState(fromDss?.motor || emptyMotor)
  const [ident, setIdent] = useState(emptyIdentity)
  const [form, setForm] = useState({
    purchase_date: today(), agreed_price: fromDss?.price || '', deduction: fromDss?.deduction || '', deduction_notes: fromDss?.deduction ? 'Servis/pajak sesuai DSS' : '', payment_method: 'tunai', notes: '',
  })
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [page, setPage] = useState(1)
  const list = useFetch('/purchases', { page })

  const setI = (k) => (e) => setIdent({ ...ident, [k]: e.target.value })
  const [seller, setSeller] = useState(emptyParty)
  const sepakat = Number(form.agreed_price || 0)
  const potongan = Number(form.deduction || 0)
  const dibayar = sepakat - potongan

  const submit = async (e) => {
    e.preventDefault()
    setBusy(true); setError('')
    try {
      const { data } = await api.post('/purchases', {
        motorcycle: { ...motorPayload(motor), ...ident },
        seller: identityPayload(seller),
        purchase_date: form.purchase_date,
        purchase_price: dibayar,
        agreed_price: sepakat,
        deduction_notes: potongan > 0 ? form.deduction_notes || null : null,
        payment_method: form.payment_method,
        notes: form.notes,
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
            <div className="md:col-span-2">
              <IdentityFields kind="supplier" title="Identitas penjual" value={seller} onChange={setSeller} />
            </div>
            <Input label="Tanggal beli" type="date" value={form.purchase_date} onChange={(e) => setForm({ ...form, purchase_date: e.target.value })} required />
            <MoneyInput label="Harga kesepakatan" value={form.agreed_price} onChange={(v) => setForm({ ...form, agreed_price: v })} required />
            <MoneyInput label="Potongan biaya (pajak, perbaikan)" value={form.deduction} onChange={(v) => setForm({ ...form, deduction: v })} />
            {potongan > 0 && (
              <Input label="Rincian potongan" className="md:col-span-2" value={form.deduction_notes} onChange={(e) => setForm({ ...form, deduction_notes: e.target.value })} placeholder="Pajak tahunan 300.000, ganti ban 200.000" />
            )}
            <div className="md:col-span-2 rounded-lg bg-teal-50 p-3 text-sm">
              <p className="text-teal-800">Dibayar ke penjual (dicatat di jurnal pembelian)</p>
              <p className={`text-lg font-semibold ${dibayar > 0 ? 'text-teal-900' : 'text-rose-600'}`}>{rupiah(dibayar)}</p>
              {potongan > 0 && <p className="mt-1 text-xs text-teal-800">Saat pajak atau perbaikan dibayar, catat di Biaya Kendaraan agar masuk HPP unit.</p>}
            </div>
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
            { key: 'supplier', label: 'Penjual', render: (r) => r.supplier?.supplier_name },
            { key: 'agreed_price', label: 'Kesepakatan', right: true, render: (r) => rupiah(r.agreed_price || r.purchase_price) },
            { key: 'purchase_price', label: 'Dibayar', right: true, render: (r) => rupiah(r.purchase_price) },
          ]}
          rows={list.data?.data}
        />
        <Pagination meta={list.data} onPage={setPage} />
      </Card>
    </div>
  )
}
