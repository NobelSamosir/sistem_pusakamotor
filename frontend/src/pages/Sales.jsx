import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { persen, rupiah, tanggal, today } from '../lib/format'
import MotorPicker from '../components/MotorPicker'
import PartyPicker from '../components/PartyPicker'
import { Alert, Button, Card, Input, MoneyInput, PageHeader, Pagination, Select, Table, Textarea } from '../components/ui'

export default function Sales() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const [form, setForm] = useState({
    motorcycle_id: params.get('motor') || '', customer_id: '', sale_date: today(),
    sale_price: params.get('harga') ? Number(params.get('harga')) : '', payment_method: 'tunai', notes: '',
  })
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const list = useFetch('/sales', { page })
  const unit = useFetch(form.motorcycle_id ? `/motorcycles/${form.motorcycle_id}` : null)

  const hpp = Number(unit.data?.hpp_current || 0)
  const price = Number(form.sale_price || 0)
  const laba = price && hpp ? price - hpp : null

  const submit = async (e) => {
    e.preventDefault(); setError('')
    if (!window.confirm('Simpan penjualan? Status motor akan berubah menjadi terjual.')) return
    try {
      await api.post('/sales', { ...form, motorcycle_id: Number(form.motorcycle_id), customer_id: Number(form.customer_id) })
      navigate(`/motor/${form.motorcycle_id}`)
    } catch (err) { setError(errorMessage(err)) }
  }

  return (
    <div>
      <PageHeader title="Penjualan Motor" subtitle="HPP dibekukan saat penjualan, lalu jurnal pendapatan dan HPP dibuat otomatis." />
      <Card>
        <Alert>{error}</Alert>
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
          <div className="md:col-span-2"><MotorPicker value={form.motorcycle_id} onChange={(v) => setForm({ ...form, motorcycle_id: v })} /></div>
          {unit.data && (
            <div className="md:col-span-2 grid gap-3 rounded-lg bg-slate-50 p-4 text-sm md:grid-cols-4">
              <div><p className="text-slate-500">Nomor rangka</p><p className="font-medium">{unit.data.frame_number}</p></div>
              <div><p className="text-slate-500">Nomor mesin</p><p className="font-medium">{unit.data.engine_number}</p></div>
              <div><p className="text-slate-500">HPP unit</p><p className="font-semibold text-teal-800">{rupiah(hpp)}</p></div>
              <div>
                <p className="text-slate-500">Laba / margin</p>
                <p className={`font-semibold ${laba !== null && laba < 0 ? 'text-rose-600' : 'text-emerald-700'}`}>
                  {laba === null ? '-' : `${rupiah(laba)} · ${persen(laba / price)}`}
                </p>
              </div>
            </div>
          )}
          <PartyPicker type="customer" value={form.customer_id} onChange={(v) => setForm({ ...form, customer_id: v })} />
          <Input label="Tanggal jual" type="date" value={form.sale_date} onChange={(e) => setForm({ ...form, sale_date: e.target.value })} required />
          <MoneyInput label="Harga jual (keputusan akhir pemilik)" value={form.sale_price} onChange={(v) => setForm({ ...form, sale_price: v })} required />
          <Select label="Metode bayar" options={[{ value: 'tunai', label: 'Tunai' }, { value: 'transfer', label: 'Transfer' }]} value={form.payment_method} onChange={(e) => setForm({ ...form, payment_method: e.target.value })} />
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
            { key: 'sale_price', label: 'Harga jual', right: true, render: (r) => rupiah(r.sale_price) },
            { key: 'hpp_amount', label: 'HPP', right: true, render: (r) => rupiah(r.hpp_amount) },
            { key: 'gross_profit', label: 'Laba', right: true, render: (r) => rupiah(r.gross_profit) },
          ]}
          rows={list.data?.data}
        />
        <Pagination meta={list.data} onPage={setPage} />
      </Card>
    </div>
  )
}
