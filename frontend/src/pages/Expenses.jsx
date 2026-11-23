import { useState } from 'react'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { rupiah, tanggal, today } from '../lib/format'
import { Alert, Button, Card, Input, MoneyInput, PageHeader, Pagination, Select, Table, sumberOptions, useFlash } from '../components/ui'

const kategori = [
  { value: 'listrik', label: 'Listrik' }, { value: 'gaji', label: 'Gaji' }, { value: 'sewa', label: 'Sewa' },
  { value: 'iklan', label: 'Iklan' }, { value: 'kebersihan', label: 'Kebersihan dan ATK' }, { value: 'lainnya', label: 'Lainnya' },
]

export default function Expenses() {
  const [form, setForm] = useState({ expense_date: today(), category: 'listrik', description: '', amount: '', source: 'kas' })
  const [error, setError] = useState('')
  const [ok, setOk] = useFlash()
  const [page, setPage] = useState(1)
  const list = useFetch('/operating-expenses', { page })

  const submit = async (e) => {
    e.preventDefault(); setError('')
    try {
      await api.post('/operating-expenses', form)
      setOk('Beban tersimpan dan jurnal otomatis dibuat.')
      setForm({ ...form, description: '', amount: '' })
      list.reload()
    } catch (err) { setError(errorMessage(err)) }
  }

  return (
    <div>
      <PageHeader title="Beban Operasional" />
      <Card>
        <Alert>{error}</Alert>
        <Alert type="success">{ok}</Alert>
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-5">
          <Input label="Tanggal" type="date" value={form.expense_date} onChange={(e) => setForm({ ...form, expense_date: e.target.value })} required />
          <Select label="Kategori" options={kategori} value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} required />
          <MoneyInput label="Nominal" value={form.amount} onChange={(v) => setForm({ ...form, amount: v })} required />
          <Select label="Sumber dana" options={sumberOptions} value={form.source} onChange={(e) => setForm({ ...form, source: e.target.value })} required />
          <Input label="Keterangan" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
          <div className="md:col-span-5 flex justify-end"><Button>Simpan beban</Button></div>
        </form>
      </Card>
      <Card title="Riwayat beban" className="mt-6">
        <Table
          columns={[
            { key: 'expense_date', label: 'Tanggal', render: (r) => tanggal(r.expense_date) },
            { key: 'category', label: 'Kategori' },
            { key: 'description', label: 'Keterangan', render: (r) => r.description || '-' },
            { key: 'source', label: 'Sumber', render: (r) => (r.source === 'perlengkapan' ? 'Perlengkapan' : 'Kas') },
            { key: 'amount', label: 'Nominal', right: true, render: (r) => rupiah(r.amount) },
          ]}
          rows={list.data?.data}
        />
        <Pagination meta={list.data} onPage={setPage} />
      </Card>
    </div>
  )
}
