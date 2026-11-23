import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { persen, rupiah, tanggal, today } from '../lib/format'
import MotorPicker from '../components/MotorPicker'
import PartyPicker from '../components/PartyPicker'
import { Alert, Badge, Button, Card, Input, MoneyInput, PageHeader, Pagination, Select, Table, Tabs, Textarea } from '../components/ui'

const Info = ({ label, value, tone = '' }) => (
  <div><p className="text-slate-500">{label}</p><p className={`font-semibold ${tone}`}>{value}</p></div>
)

export default function Sales() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const [form, setForm] = useState({
    motorcycle_id: params.get('motor') || '', customer_id: '', sale_date: today(),
    sale_price: params.get('harga') ? Number(params.get('harga')) : '', sale_type: 'tunai',
    leasing_company: '', down_payment: '', payment_method: 'tunai', notes: '',
  })
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const list = useFetch('/sales', { page })
  const unit = useFetch(form.motorcycle_id ? `/motorcycles/${form.motorcycle_id}` : null)

  const m = unit.data
  const titipan = m?.ownership_type === 'titipan'
  const deposit = (m?.deposits || []).find((d) => d.status === 'aktif')
  const kredit = form.sale_type === 'kredit'

  // Uang muka aktif mengikat pelanggan: isi otomatis supaya tidak salah pilih.
  useEffect(() => {
    if (deposit) setForm((f) => ({ ...f, customer_id: String(deposit.customer_id) }))
  }, [deposit?.id])

  const hpp = Number(m?.hpp_current || 0)
  const price = Number(form.sale_price || 0)
  const dp = Number(form.down_payment || 0)
  const um = Number(deposit?.amount || 0)
  const laba = price && hpp ? price - hpp : null
  const kasSekarang = (kredit ? dp : price) - um
  const piutang = kredit ? price - dp : 0

  const submit = async (e) => {
    e.preventDefault(); setError('')
    const pesan = kredit
      ? `Simpan penjualan kredit? Kas diterima sekarang ${rupiah(kasSekarang)}, piutang ke ${form.leasing_company || 'leasing'} ${rupiah(piutang)}.`
      : 'Simpan penjualan? Status motor akan berubah menjadi terjual.'
    if (!window.confirm(pesan)) return
    try {
      const body = { ...form, motorcycle_id: Number(form.motorcycle_id), customer_id: Number(form.customer_id) }
      if (!kredit) { delete body.leasing_company; delete body.down_payment }
      await api.post('/sales', body)
      navigate(`/motor/${form.motorcycle_id}`)
    } catch (err) { setError(errorMessage(err)) }
  }

  return (
    <div>
      <PageHeader
        title="Penjualan Motor"
      />
      <Card>
        <Alert>{error}</Alert>
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
              {titipan ? (
                <>
                  <Info label={`Harga titipan (${m.consignment?.consignor?.supplier_name || 'pemilik'})`} value={rupiah(hpp)} tone="text-violet-800" />
                  <Info label="Komisi titip jual" value={laba === null ? '-' : rupiah(laba)} tone={laba !== null && laba < 0 ? 'text-rose-600' : 'text-emerald-700'} />
                </>
              ) : (
                <>
                  <Info label="HPP unit" value={rupiah(hpp)} tone="text-teal-800" />
                  <Info
                    label="Laba / margin"
                    value={laba === null ? '-' : `${rupiah(laba)} · ${persen(laba / price)}`}
                    tone={laba !== null && laba < 0 ? 'text-rose-600' : 'text-emerald-700'}
                  />
                </>
              )}
              {titipan && (
                <p className="md:col-span-4 text-xs text-violet-800">
                  Unit titipan: hasil jual sebesar harga titipan menjadi utang kepada pemilik motor, selisihnya menjadi pendapatan komisi showroom.
                </p>
              )}
              {deposit && (
                <p className="md:col-span-4 rounded-md bg-sky-50 px-3 py-2 text-sky-800">
                  Unit ini sudah dipesan {deposit.customer?.customer_name} dengan uang muka {rupiah(deposit.amount)} ({deposit.deposit_number}, {tanggal(deposit.deposit_date)}). Uang muka otomatis dipotong dari pembayaran.
                </p>
              )}
            </div>
          )}
          <PartyPicker type="customer" value={form.customer_id} onChange={(v) => setForm({ ...form, customer_id: v })} />
          <Input label="Tanggal jual" type="date" value={form.sale_date} onChange={(e) => setForm({ ...form, sale_date: e.target.value })} required />
          <MoneyInput label="Harga jual (on the road ke pelanggan)" value={form.sale_price} onChange={(v) => setForm({ ...form, sale_price: v })} required />
          {kredit ? (
            <>
              <Input label="Perusahaan leasing" value={form.leasing_company} onChange={(e) => setForm({ ...form, leasing_company: e.target.value })} placeholder="FIF, Adira, WOM, BAF..." required />
              <MoneyInput
                label="DP dari pelanggan"
                hint={um ? `Termasuk uang muka ${rupiah(um)} yang sudah dibayar` : 'Dibayar pelanggan langsung ke showroom'}
                value={form.down_payment}
                onChange={(v) => setForm({ ...form, down_payment: v })}
                required
              />
            </>
          ) : null}
          <Select label="Metode bayar" options={[{ value: 'tunai', label: 'Tunai' }, { value: 'transfer', label: 'Transfer' }]} value={form.payment_method} onChange={(e) => setForm({ ...form, payment_method: e.target.value })} />
          {price > 0 && (
            <div className="md:col-span-2 grid gap-3 rounded-lg border border-teal-100 bg-teal-50/60 p-4 text-sm md:grid-cols-3">
              <Info label="Kas diterima hari ini" value={rupiah(Math.max(kasSekarang, 0))} tone="text-teal-800" />
              {um > 0 && <Info label="Uang muka dipakai" value={rupiah(um)} />}
              {kredit && <Info label={`Piutang ke ${form.leasing_company || 'leasing'} (menunggu cair)`} value={rupiah(piutang)} tone="text-sky-800" />}
            </div>
          )}
          <Textarea label="Keterangan" className="md:col-span-2" value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
          <div className="md:col-span-2 flex justify-end"><Button>Simpan penjualan</Button></div>
        </form>
      </Card>
      <Card title="Riwayat penjualan" className="mt-6">
        <Table
          columns={[
            { key: 'sale_number', label: 'No.' },
            { key: 'sale_date', label: 'Tanggal', render: (r) => tanggal(r.sale_date) },
            { key: 'unit', label: 'Unit', render: (r) => `${r.motorcycle.brand} ${r.motorcycle.model} ${r.motorcycle.year}` },
            { key: 'customer', label: 'Pelanggan', render: (r) => r.customer?.customer_name },
            {
              key: 'type', label: 'Jenis', render: (r) => (
                <span className="flex flex-wrap gap-1">
                  <Badge>{r.sale_type || 'tunai'}</Badge>
                  {r.is_consignment && <Badge>titipan</Badge>}
                </span>
              ),
            },
            { key: 'sale_price', label: 'Harga jual', right: true, render: (r) => rupiah(r.sale_price) },
            { key: 'hpp_amount', label: 'HPP / titipan', right: true, render: (r) => rupiah(r.is_consignment ? r.consignor_payable : r.hpp_amount) },
            { key: 'gross_profit', label: 'Laba / komisi', right: true, render: (r) => rupiah(r.is_consignment ? r.consignment_commission : r.gross_profit) },
          ]}
          rows={list.data?.data}
        />
        <Pagination meta={list.data} onPage={setPage} />
      </Card>
    </div>
  )
}
