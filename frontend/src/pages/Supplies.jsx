import { useState } from 'react'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { rupiah, tanggal, today } from '../lib/format'
import { Alert, Button, Card, Input, Loading, MoneyInput, PageHeader, StatCard, Table, useFlash } from '../components/ui'

export default function Supplies() {
  const [form, setForm] = useState({ purchase_date: today(), description: '', vendor: '', amount: '' })
  const [error, setError] = useState('')
  const [ok, setOk] = useFlash()
  const { data, loading, reload } = useFetch('/supplies')

  const submit = async (e) => {
    e.preventDefault(); setError('')
    try {
      await api.post('/supplies', form)
      setOk('Pembelian perlengkapan tercatat: Perlengkapan (debit) / Kas (kredit).')
      setForm({ ...form, description: '', vendor: '', amount: '' })
      reload()
    } catch (err) { setError(errorMessage(err)) }
  }

  return (
    <div>
      <PageHeader
        title="Perlengkapan"
      />
      <div className="mb-6 grid gap-4 md:grid-cols-3">
        <StatCard label="Saldo perlengkapan" value={rupiah(data?.balance)} note="nilai bahan yang belum terpakai" tone="violet" />
      </div>
      <Alert>{error}</Alert>
      <Alert type="success">{ok}</Alert>
      <Card title="Beli perlengkapan">
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-4">
          <Input label="Tanggal" type="date" value={form.purchase_date} onChange={(e) => setForm({ ...form, purchase_date: e.target.value })} required />
          <Input label="Barang" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} placeholder="Cat Pylox 3 kaleng, dempul, amplas" required />
          <Input label="Toko (opsional)" value={form.vendor} onChange={(e) => setForm({ ...form, vendor: e.target.value })} />
          <MoneyInput label="Total harga" value={form.amount} onChange={(v) => setForm({ ...form, amount: v })} required />
          <div className="md:col-span-4 flex justify-end"><Button>Simpan pembelian</Button></div>
        </form>
      </Card>
      {loading ? <Loading /> : (
        <div className="mt-6 grid gap-6 lg:grid-cols-2">
          <Card title="Riwayat pembelian">
            <Table
              columns={[
                { key: 'purchase_date', label: 'Tanggal', render: (r) => tanggal(r.purchase_date) },
                { key: 'description', label: 'Barang' },
                { key: 'vendor', label: 'Toko', render: (r) => r.vendor || '-' },
                { key: 'amount', label: 'Nominal', right: true, render: (r) => rupiah(r.amount) },
              ]}
              rows={data?.purchases}
            />
          </Card>
          <Card title="Riwayat pemakaian">
            <Table
              columns={[
                { key: 'date', label: 'Tanggal', render: (r) => tanggal(r.date) },
                { key: 'description', label: 'Dipakai untuk' },
                { key: 'amount', label: 'Nominal', right: true, render: (r) => rupiah(r.amount) },
              ]}
              rows={data?.usage}
              empty="Belum ada pemakaian. Pilih sumber dana Perlengkapan saat mencatat biaya."
            />
          </Card>
        </div>
      )}
    </div>
  )
}
