import { useState } from 'react'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { rupiah, tanggal, today } from '../lib/format'
import { RowActions, useSave } from '../components/confirm'
import { Alert, Button, Card, Input, MoneyInput, PageHeader, Pagination, Select, Table, sumberOptions, useFlash } from '../components/ui'

const kategori = [
  { value: 'listrik', label: 'Listrik' }, { value: 'gaji', label: 'Gaji' }, { value: 'sewa', label: 'Sewa' },
  { value: 'iklan', label: 'Iklan' }, { value: 'kebersihan', label: 'Kebersihan dan ATK' }, { value: 'lainnya', label: 'Lainnya' },
]
const namaKategori = Object.fromEntries(kategori.map((k) => [k.value, k.label]))
const kosong = () => ({ expense_date: today(), category: 'listrik', description: '', amount: '', source: 'kas' })

export default function Expenses() {
  const [form, setForm] = useState(kosong())
  const [editId, setEditId] = useState(null)
  const [error, setError] = useState('')
  const [ok, setOk] = useFlash()
  const [page, setPage] = useState(1)
  const list = useFetch('/operating-expenses', { page })
  const { busy, run } = useSave()

  const ringkas = (f) => [['Tanggal', tanggal(f.expense_date)], ['Kategori', namaKategori[f.category]], ['Keterangan', f.description], ['Sumber dana', f.source === 'perlengkapan' ? 'Stok perlengkapan' : 'Kas'], ['Nominal', rupiah(f.amount)]]

  const submit = (e) => {
    e.preventDefault(); setError('')
    run({ title: editId ? 'Simpan perubahan beban?' : 'Simpan beban ini?', rows: ringkas(form), message: editId ? 'Jurnal lama diganti dengan jurnal baru.' : 'Jurnal dibuat otomatis.' }, async () => {
      try {
        if (editId) await api.put(`/operating-expenses/${editId}`, form)
        else await api.post('/operating-expenses', form)
        setOk(editId ? 'Beban diubah dan jurnalnya diperbarui.' : 'Beban tersimpan dan jurnal otomatis dibuat.')
        setForm({ ...kosong(), expense_date: form.expense_date, category: form.category }); setEditId(null)
        list.reload()
      } catch (err) { setError(errorMessage(err)) }
    })
  }

  const edit = (r) => {
    setEditId(r.id)
    setForm({ expense_date: r.expense_date.slice(0, 10), category: r.category, description: r.description || '', amount: Number(r.amount), source: r.source || 'kas' })
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const hapus = (r) => run({ title: 'Hapus beban ini?', danger: true, okLabel: 'Ya, hapus', rows: ringkas({ ...r, expense_date: r.expense_date }), message: 'Jurnalnya ikut dihapus.' }, async () => {
    setError('')
    try { await api.delete(`/operating-expenses/${r.id}`); setOk('Beban dan jurnalnya sudah dihapus.'); if (editId === r.id) { setEditId(null); setForm(kosong()) } list.reload() } catch (err) { setError(errorMessage(err)) }
  })

  return (
    <div>
      <PageHeader title="Beban Operasional" />
      <Card title={editId ? 'Ubah beban' : undefined}>
        <Alert>{error}</Alert>
        <Alert type="success">{ok}</Alert>
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-5">
          <Input label="Tanggal" type="date" value={form.expense_date} onChange={(e) => setForm({ ...form, expense_date: e.target.value })} required />
          <Select label="Kategori" options={kategori} value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} required />
          <MoneyInput label="Nominal" value={form.amount} onChange={(v) => setForm({ ...form, amount: v })} required />
          <Select label="Sumber dana" options={sumberOptions} value={form.source} onChange={(e) => setForm({ ...form, source: e.target.value })} required />
          <Input label="Keterangan" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
          <div className="md:col-span-5 flex justify-end gap-2">
            {editId && <Button type="button" variant="secondary" onClick={() => { setEditId(null); setForm(kosong()) }}>Batal ubah</Button>}
            <Button disabled={busy}>{busy ? 'Menyimpan...' : editId ? 'Simpan perubahan' : 'Simpan beban'}</Button>
          </div>
        </form>
      </Card>
      <Card title="Riwayat beban" className="mt-6">
        <Table
          columns={[
            { key: 'expense_date', label: 'Tanggal', render: (r) => tanggal(r.expense_date) },
            { key: 'category', label: 'Kategori', render: (r) => namaKategori[r.category] || r.category },
            { key: 'description', label: 'Keterangan', render: (r) => r.description || '-' },
            { key: 'source', label: 'Sumber', render: (r) => (r.source === 'perlengkapan' ? 'Perlengkapan' : 'Kas') },
            { key: 'amount', label: 'Nominal', right: true, render: (r) => rupiah(r.amount) },
            { key: 'aksi', label: '', render: (r) => <RowActions disabled={busy} onEdit={() => edit(r)} onDelete={() => hapus(r)} /> },
          ]}
          rows={list.data?.data}
        />
        <Pagination meta={list.data} onPage={setPage} />
      </Card>
    </div>
  )
}
