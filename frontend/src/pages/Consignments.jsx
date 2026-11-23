import { useState } from 'react'
import { Link } from 'react-router-dom'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { rupiah, tanggal, today, unitLabel } from '../lib/format'
import MotorFields, { emptyMotor, motorPayload } from '../components/MotorFields'
import PartyPicker from '../components/PartyPicker'
import { Alert, Badge, Button, Card, Input, MoneyInput, PageHeader, Pagination, Select, Table, Textarea, useFlash } from '../components/ui'

const emptyIdentity = { frame_number: '', engine_number: '', license_plate: '', bpkb_number: '', color: '', condition_notes: '' }

export default function Consignments() {
  const [motor, setMotor] = useState(emptyMotor)
  const [ident, setIdent] = useState(emptyIdentity)
  const [form, setForm] = useState({ consignor_id: '', received_date: today(), consignor_price: '', notes: '' })
  const [showForm, setShowForm] = useState(false)
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [error, setError] = useState('')
  const [ok, setOk] = useFlash()
  const [busy, setBusy] = useState(false)
  const list = useFetch('/consignments', { page, status })
  const setI = (k) => (e) => setIdent({ ...ident, [k]: e.target.value })

  const submit = async (e) => {
    e.preventDefault(); setError(''); setBusy(true)
    try {
      await api.post('/consignments', { motorcycle: { ...motorPayload(motor), ...ident }, ...form, consignor_id: Number(form.consignor_id) })
      setOk('Motor titipan tercatat. Unit muncul di daftar motor dan bisa dijual seperti biasa, tanpa jurnal karena bukan milik showroom.')
      setMotor(emptyMotor); setIdent(emptyIdentity); setForm({ ...form, consignor_price: '', notes: '' }); setShowForm(false)
      list.reload()
    } catch (err) { setError(errorMessage(err)) } finally { setBusy(false) }
  }

  const act = async (c, aksi) => {
    const teks = aksi === 'payout'
      ? `Bayarkan ${rupiah(c.consignor_price)} kepada ${c.consignor?.supplier_name}? Kas berkurang dan utang titipan lunas.`
      : `Kembalikan motor ${unitLabel(c.motorcycle)} kepada ${c.consignor?.supplier_name}? Unit keluar dari showroom.`
    if (!window.confirm(teks)) return
    setError('')
    try {
      await api.post(`/consignments/${c.id}/${aksi}`, { date: today() })
      setOk(aksi === 'payout' ? 'Hasil penjualan sudah dibayarkan ke pemilik motor.' : 'Motor dikembalikan ke pemiliknya.')
      list.reload()
    } catch (err) { setError(errorMessage(err)) }
  }

  return (
    <div>
      <PageHeader
        title="Titip Jual (Konsinyasi)"
        actions={<Button onClick={() => setShowForm(!showForm)}>{showForm ? 'Tutup form' : '+ Terima motor titipan'}</Button>}
      />
      <Alert>{error}</Alert>
      <Alert type="success">{ok}</Alert>
      {showForm && (
        <form onSubmit={submit} className="mb-6 space-y-6">
          <Card title="Pemilik dan kesepakatan harga">
            <div className="grid gap-4 md:grid-cols-3">
              <div className="md:col-span-3"><PartyPicker type="supplier" value={form.consignor_id} onChange={(v) => setForm({ ...form, consignor_id: v })} /></div>
              <Input label="Tanggal diterima" type="date" value={form.received_date} onChange={(e) => setForm({ ...form, received_date: e.target.value })} required />
              <MoneyInput label="Harga titipan (yang diterima pemilik)" hint="Showroom boleh menjual di atas harga ini, selisihnya menjadi komisi" value={form.consignor_price} onChange={(v) => setForm({ ...form, consignor_price: v })} required />
              <Textarea label="Catatan kesepakatan" value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
            </div>
          </Card>
          <Card title="Identitas unit">
            <div className="grid gap-4 md:grid-cols-3">
              <Input label="Nomor rangka" value={ident.frame_number} onChange={setI('frame_number')} required />
              <Input label="Nomor mesin" value={ident.engine_number} onChange={setI('engine_number')} required />
              <Input label="Nomor polisi" value={ident.license_plate} onChange={setI('license_plate')} />
              <Input label="Nomor BPKB" value={ident.bpkb_number} onChange={setI('bpkb_number')} />
              <Input label="Warna" value={ident.color} onChange={setI('color')} />
              <Textarea label="Catatan kondisi" value={ident.condition_notes} onChange={setI('condition_notes')} />
            </div>
          </Card>
          <Card title="Karakteristik motor">
            <MotorFields value={motor} onChange={setMotor} />
            <div className="mt-6 flex justify-end"><Button disabled={busy}>{busy ? 'Menyimpan...' : 'Simpan motor titipan'}</Button></div>
          </Card>
        </form>
      )}
      <Card
        title="Daftar titipan"
        actions={<Select placeholder="Semua status" options={['dititipkan', 'terjual', 'lunas', 'dikembalikan']} value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }} className="w-44 -mt-1" />}
      >
        <Table
          columns={[
            { key: 'consignment_number', label: 'No.' },
            { key: 'received_date', label: 'Diterima', render: (r) => tanggal(r.received_date) },
            { key: 'unit', label: 'Unit', render: (r) => <Link className="text-teal-700" to={`/motor/${r.motorcycle_id}`}>{r.motorcycle?.vehicle_code} · {unitLabel(r.motorcycle)}</Link> },
            { key: 'consignor', label: 'Pemilik', render: (r) => r.consignor?.supplier_name },
            { key: 'consignor_price', label: 'Harga titipan', right: true, render: (r) => rupiah(r.consignor_price) },
            { key: 'sold', label: 'Terjual', right: true, render: (r) => (r.motorcycle?.sale ? rupiah(r.motorcycle.sale.sale_price) : '-') },
            { key: 'status', label: 'Status', render: (r) => <Badge>{r.status}</Badge> },
            {
              key: 'aksi', label: '', render: (r) => (
                <span className="flex gap-1">
                  {r.status === 'terjual' && <Button variant="ghost" className="px-2 py-1" onClick={() => act(r, 'payout')}>Bayar pemilik</Button>}
                  {r.status === 'dititipkan' && <Button variant="ghost" className="px-2 py-1" onClick={() => act(r, 'return')}>Kembalikan unit</Button>}
                </span>
              ),
            },
          ]}
          rows={list.data?.data}
          empty="Belum ada motor titipan."
        />
        <Pagination meta={list.data} onPage={setPage} />
      </Card>
    </div>
  )
}
