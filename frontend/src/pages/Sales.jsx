import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { persen, rupiah, tanggal, today } from '../lib/format'
import MotorPicker from '../components/MotorPicker'
import IdentityFields, { emptyIdentity, identityPayload } from '../components/IdentityFields'
import LeasingInput from '../components/LeasingInput'
import { RowActions, useSave } from '../components/confirm'
import { Alert, Badge, Button, Card, Checkbox, Input, MoneyInput, PageHeader, Pagination, Select, Table, Tabs, Textarea } from '../components/ui'

const Info = ({ label, value, tone = '' }) => (
  <div><p className="text-slate-500">{label}</p><p className={`font-semibold ${tone}`}>{value}</p></div>
)

export default function Sales() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const [form, setForm] = useState({
    motorcycle_id: params.get('motor') || '', sale_date: today(),
    sale_price: params.get('harga') ? Number(params.get('harga')) : '', sale_type: 'tunai',
    leasing_company: '', down_payment: '', payment_method: 'tunai', notes: '',
  })
  const [buyer, setBuyer] = useState(emptyIdentity)
  const [error, setError] = useState('')
  const [ok, setOk] = useState('')
  const { busy, run } = useSave()
  const [page, setPage] = useState(1)
  const list = useFetch('/sales', { page })
  const unit = useFetch(form.motorcycle_id ? `/motorcycles/${form.motorcycle_id}` : null)

  const m = unit.data
  const kredit = form.sale_type === 'kredit'
  // Uang muka yang sudah terikat ke unit ini
  const depositUnit = (m?.deposits || []).find((d) => d.status === 'aktif')
  const deposit = depositUnit

  const hpp = Number(m?.hpp_current || 0)
  const price = Number(form.sale_price || 0)
  const dp = Number(form.down_payment || 0)
  const um = Number(deposit?.amount || 0)
  const laba = price && hpp ? price - hpp : null
  const kasSekarang = (kredit ? dp : price) - um
  const piutang = kredit ? price - dp : 0

  const submit = (e) => {
    e.preventDefault(); setError('')
    const rows = [
      ['Unit', m ? `${m.vehicle_code} · ${m.brand} ${m.model} ${m.year}` : '-'],
      ['Pembeli', deposit ? deposit.customer?.customer_name : buyer.name],
      ['Jenis', kredit ? `Kredit (${form.leasing_company || 'leasing'})` : 'Tunai'],
      ['Harga jual', rupiah(price)],
      ['Kas diterima hari ini', rupiah(Math.max(kasSekarang, 0))],
    ]
    if (um > 0) rows.push(['Uang muka dipakai', rupiah(um)])
    if (kredit) rows.push(['Piutang ke leasing', rupiah(piutang)])
    run({ title: 'Simpan penjualan ini?', rows, message: 'Status motor berubah menjadi terjual.' }, async () => {
    try {
      const body = { ...form, motorcycle_id: Number(form.motorcycle_id) }
      if (!kredit) { delete body.leasing_company; delete body.down_payment }
      // Pembeli yang sudah membayar uang muka sudah tercatat; selain itu identitas diisi di form ini
      if (deposit) body.customer_id = deposit.customer_id
      else body.buyer = identityPayload(buyer)
      await api.post('/sales', body)
      navigate(`/motor/${form.motorcycle_id}`)
    } catch (err) { setError(errorMessage(err)) }
    })
  }

  const hapus = (r) => run({
    title: 'Hapus penjualan ini?', danger: true, okLabel: 'Ya, hapus',
    rows: [['No.', r.sale_number], ['Unit', `${r.motorcycle.brand} ${r.motorcycle.model} ${r.motorcycle.year}`], ['Pelanggan', r.customer?.customer_name], ['Harga jual', rupiah(r.sale_price)]],
    message: 'Pakai ini hanya untuk penjualan yang salah input. Jurnal penjualan, pencairan leasing, dan komisinya ikut dihapus, motor kembali ke stok, dan uang muka (bila ada) aktif lagi.',
  }, async () => {
    setError('')
    try { await api.delete(`/sales/${r.id}`); setOk('Penjualan dan jurnalnya sudah dihapus. Motor kembali ke stok.'); list.reload() } catch (err) { setError(errorMessage(err)) }
  })

  return (
    <div>
      <PageHeader title="Penjualan Motor" />
      <Alert>{error}</Alert>
      <Alert type="success">{ok}</Alert>
      <Card>
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
          <div className="md:col-span-2">
            <Tabs
              tabs={[{ value: 'tunai', label: 'Penjualan tunai' }, { value: 'kredit', label: 'Penjualan kredit (leasing)' }]}
              value={form.sale_type}
              onChange={(v) => setForm({ ...form, sale_type: v })}
            />
          </div>
          <div className="md:col-span-2"><MotorPicker value={form.motorcycle_id} onChange={(v) => setForm({ ...form, motorcycle_id: v })} /></div>
          {m && (
            <div className="md:col-span-2 grid gap-3 rounded-lg bg-slate-50 p-4 text-sm md:grid-cols-4">
              <Info label="Nomor rangka" value={m.frame_number} />
              <Info label="Nomor mesin" value={m.engine_number} />
              <Info label="HPP unit" value={rupiah(hpp)} tone="text-teal-800" />
              <Info
                label="Laba / margin"
                value={laba === null ? '-' : `${rupiah(laba)} · ${persen(laba / price)}`}
                tone={laba !== null && laba < 0 ? 'text-rose-600' : 'text-emerald-700'}
              />
              {depositUnit && (
                <p className="md:col-span-4 rounded-md bg-sky-50 px-3 py-2 text-sky-800">
                  Unit ini sudah dipesan {depositUnit.customer?.customer_name} dengan uang muka {rupiah(depositUnit.amount)} ({depositUnit.deposit_number}, {tanggal(depositUnit.deposit_date)}). Uang muka otomatis dipotong dari pembayaran.
                </p>
              )}
            </div>
          )}
          <div className="md:col-span-2">
            {deposit ? (
              <div className="rounded-lg border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                Pembeli: <b>{deposit.customer?.customer_name}</b>{deposit.customer?.phone ? ` · ${deposit.customer.phone}` : ''} (sudah tercatat saat membayar uang muka {deposit.deposit_number})
              </div>
            ) : (
              <IdentityFields kind="customer" title="Identitas pembeli" value={buyer} onChange={setBuyer} />
            )}
          </div>
          <Input label="Tanggal jual" type="date" value={form.sale_date} onChange={(e) => setForm({ ...form, sale_date: e.target.value })} required />
          <MoneyInput label="Harga jual (on the road ke pelanggan)" value={form.sale_price} onChange={(v) => setForm({ ...form, sale_price: v })} required />
          {kredit && (
            <>
              <LeasingInput value={form.leasing_company} onChange={(v) => setForm({ ...form, leasing_company: v })} />
              <MoneyInput
                label="DP dari pelanggan"
                hint={um ? `Termasuk uang muka ${rupiah(um)} yang sudah dibayar` : 'Dibayar pelanggan langsung ke showroom'}
                value={form.down_payment}
                onChange={(v) => setForm({ ...form, down_payment: v })}
                required
              />
            </>
          )}
          <Select label="Metode bayar" options={[{ value: 'tunai', label: 'Tunai' }, { value: 'transfer', label: 'Transfer' }]} value={form.payment_method} onChange={(e) => setForm({ ...form, payment_method: e.target.value })} />
          {price > 0 && (
            <div className="md:col-span-2 grid gap-3 rounded-lg border border-teal-100 bg-teal-50/60 p-4 text-sm md:grid-cols-3">
              <Info label="Kas diterima hari ini" value={rupiah(Math.max(kasSekarang, 0))} tone="text-teal-800" />
              {um > 0 && <Info label="Uang muka dipakai" value={rupiah(um)} />}
              {kredit && <Info label={`Piutang ke ${form.leasing_company || 'leasing'} (menunggu cair)`} value={rupiah(piutang)} tone="text-sky-800" />}
            </div>
          )}
          <Textarea label="Keterangan" className="md:col-span-2" value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
          <div className="md:col-span-2 flex justify-end"><Button disabled={busy}>{busy ? 'Menyimpan...' : 'Simpan penjualan'}</Button></div>
        </form>
      </Card>
      <Card title="Riwayat penjualan" className="mt-6">
        <Table
          columns={[
            { key: 'sale_number', label: 'No.' },
            { key: 'sale_date', label: 'Tanggal', render: (r) => tanggal(r.sale_date) },
            { key: 'unit', label: 'Unit', render: (r) => `${r.motorcycle.brand} ${r.motorcycle.model} ${r.motorcycle.year}` },
            { key: 'customer', label: 'Pelanggan', render: (r) => r.customer?.customer_name },
            { key: 'type', label: 'Jenis', render: (r) => <Badge>{r.sale_type || 'tunai'}</Badge> },
            { key: 'leasing_company', label: 'Leasing', render: (r) => r.leasing_company || '-' },
            { key: 'sale_price', label: 'Harga jual', right: true, render: (r) => rupiah(r.sale_price) },
            { key: 'hpp_amount', label: 'HPP', right: true, render: (r) => rupiah(r.hpp_amount) },
            { key: 'gross_profit', label: 'Laba kotor', right: true, render: (r) => rupiah(r.gross_profit) },
            { key: 'aksi', label: '', render: (r) => <RowActions disabled={busy} onDelete={() => hapus(r)} /> },
          ]}
          rows={list.data?.data}
        />
        <Pagination meta={list.data} onPage={setPage} />
      </Card>
    </div>
  )
}
