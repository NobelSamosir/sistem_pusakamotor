import { useState } from 'react'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { rupiah, tanggal, today } from '../lib/format'
import PartyPicker from '../components/PartyPicker'
import { Alert, Button, Card, Input, MoneyInput, PageHeader, Pagination, Select, Table, useFlash } from '../components/ui'

const jenis = [
  { value: 'perpanjang_stnk', label: 'Perpanjang STNK (5 tahunan)' },
  { value: 'pajak_tahunan', label: 'Bayar pajak tahunan' },
  { value: 'balik_nama', label: 'Balik nama (BBN)' },
  { value: 'mutasi', label: 'Mutasi kendaraan' },
  { value: 'lainnya', label: 'Lainnya' },
]
const namaJenis = Object.fromEntries(jenis.map((j) => [j.value, j.label]))

export default function DocumentServices() {
  const kosong = { service_date: today(), service_type: 'pajak_tahunan', customer_id: '', license_plate: '', description: '', amount_received: '', amount_paid: '' }
  const [form, setForm] = useState(kosong)
  const [page, setPage] = useState(1)
  const [error, setError] = useState('')
  const [ok, setOk] = useFlash()
  const list = useFetch('/document-services', { page })
  const upah = Number(form.amount_received || 0) - Number(form.amount_paid || 0)

  const submit = async (e) => {
    e.preventDefault(); setError('')
    try {
      await api.post('/document-services', { ...form, customer_id: form.customer_id ? Number(form.customer_id) : null })
      setOk(`Jasa tercatat. Upah ${rupiah(upah)} masuk sebagai pendapatan jasa pengurusan surat.`)
      setForm({ ...kosong, service_date: form.service_date })
      list.reload()
    } catch (err) { setError(errorMessage(err)) }
  }

  return (
    <div>
      <PageHeader title="Jasa Pengurusan Surat" />
      <Alert>{error}</Alert>
      <Alert type="success">{ok}</Alert>
      <Card title="Catat jasa">
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-3">
          <Input label="Tanggal" type="date" value={form.service_date} onChange={(e) => setForm({ ...form, service_date: e.target.value })} required />
          <Select label="Jenis jasa" options={jenis} value={form.service_type} onChange={(e) => setForm({ ...form, service_type: e.target.value })} required />
          <Input label="Nomor polisi" value={form.license_plate} onChange={(e) => setForm({ ...form, license_plate: e.target.value })} placeholder="T 1234 AB" />
          <div className="md:col-span-2">
            <PartyPicker type="customer" label="Pelanggan (opsional)" required={false} value={form.customer_id} onChange={(v) => setForm({ ...form, customer_id: v })} />
          </div>
          <Input label="Keterangan" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
          <MoneyInput label="Uang diterima dari pelanggan" value={form.amount_received} onChange={(v) => setForm({ ...form, amount_received: v })} required />
          <MoneyInput label="Disetor ke Samsat / biro jasa" value={form.amount_paid} onChange={(v) => setForm({ ...form, amount_paid: v })} required />
          <div className="rounded-lg bg-teal-50 p-3">
            <p className="text-xs text-teal-800">Upah jasa (pendapatan)</p>
            <p className={`text-lg font-semibold ${upah > 0 ? 'text-teal-900' : 'text-rose-600'}`}>{rupiah(upah)}</p>
          </div>
          <div className="md:col-span-3 flex justify-end"><Button>Simpan</Button></div>
        </form>
      </Card>
      <Card title="Riwayat jasa" className="mt-6">
        <Table
          columns={[
            { key: 'service_number', label: 'No.' },
            { key: 'service_date', label: 'Tanggal', render: (r) => tanggal(r.service_date) },
            { key: 'service_type', label: 'Jenis', render: (r) => namaJenis[r.service_type] || r.service_type },
            { key: 'license_plate', label: 'Nopol', render: (r) => r.license_plate || '-' },
            { key: 'customer', label: 'Pelanggan', render: (r) => r.customer?.customer_name || '-' },
            { key: 'amount_received', label: 'Diterima', right: true, render: (r) => rupiah(r.amount_received) },
            { key: 'amount_paid', label: 'Disetor', right: true, render: (r) => rupiah(r.amount_paid) },
            { key: 'fee', label: 'Upah jasa', right: true, render: (r) => rupiah(r.fee) },
          ]}
          rows={list.data?.data}
          empty="Belum ada jasa pengurusan surat."
        />
        <Pagination meta={list.data} onPage={setPage} />
      </Card>
    </div>
  )
}
