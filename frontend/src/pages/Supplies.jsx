import { useState } from 'react'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { rupiah, tanggal, today } from '../lib/format'
import { RowActions, useSave } from '../components/confirm'
import { Alert, Button, Card, Input, Loading, MoneyInput, PageHeader, StatCard, Table, useFlash } from '../components/ui'

const kosong = () => ({ purchase_date: today(), description: '', vendor: '', amount: '' })

export default function Supplies() {
  const [form, setForm] = useState(kosong())
  const [editId, setEditId] = useState(null)
  const [error, setError] = useState('')
  const [ok, setOk] = useFlash()
  const { data, loading, reload } = useFetch('/supplies')
  const { busy, run } = useSave()
  const ringkas = (f) => [['Tanggal', tanggal(f.purchase_date)], ['Barang', f.description], ['Toko', f.vendor], ['Total harga', rupiah(f.amount)]]

  const submit = (e) => {
    e.preventDefault(); setError('')
    run({ title: editId ? 'Simpan perubahan pembelian perlengkapan?' : 'Simpan pembelian perlengkapan?', rows: ringkas(form) }, async () => {
      try {
        if (editId) await api.put(`/supplies/${editId}`, form)
        else await api.post('/supplies', form)
        setOk(editId ? 'Pembelian perlengkapan diubah.' : 'Pembelian perlengkapan tercatat: Perlengkapan (debit) / Kas (kredit).')
        setForm({ ...kosong(), purchase_date: form.purchase_date }); setEditId(null)
        reload()
      } catch (err) { setError(errorMessage(err)) }
    })
  }

  const ubah = (r) => {
    setEditId(r.id)
    setForm({ purchase_date: r.purchase_date.slice(0, 10), description: r.description, vendor: r.vendor || '', amount: Number(r.amount) })
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
  const hapus = (r) => run({ title: 'Hapus pembelian perlengkapan ini?', danger: true, okLabel: 'Ya, hapus', rows: ringkas(r), message: 'Jurnalnya ikut dihapus.' }, async () => {
    setError('')
    try { await api.delete(`/supplies/${r.id}`); setOk('Pembelian perlengkapan dan jurnalnya sudah dihapus.'); reload() } catch (err) { setError(errorMessage(err)) }
  })

  return (
    <div>
      <PageHeader title="Perlengkapan" />
      <div className="mb-6 grid gap-4 md:grid-cols-3">
        <StatCard label="Saldo perlengkapan" value={rupiah(data?.balance)} note="nilai bahan yang belum terpakai" tone="violet" />
      </div>
      <Alert>{error}</Alert>
      <Alert type="success">{ok}</Alert>
      <Card title={editId ? 'Ubah pembelian perlengkapan' : 'Beli perlengkapan'}>
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-4">
          <Input label="Tanggal" type="date" value={form.purchase_date} onChange={(e) => setForm({ ...form, purchase_date: e.target.value })} required />
          <Input label="Barang" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} placeholder="Cat Pylox 3 kaleng, dempul, amplas" required />
          <Input label="Toko (opsional)" value={form.vendor} onChange={(e) => setForm({ ...form, vendor: e.target.value })} />
          <MoneyInput label="Total harga" value={form.amount} onChange={(v) => setForm({ ...form, amount: v })} required />
          <div className="md:col-span-4 flex justify-end gap-2">
            {editId && <Button type="button" variant="secondary" onClick={() => { setEditId(null); setForm(kosong()) }}>Batal ubah</Button>}
            <Button disabled={busy}>{busy ? 'Menyimpan...' : editId ? 'Simpan perubahan' : 'Simpan pembelian'}</Button>
          </div>
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
                { key: 'aksi', label: '', render: (r) => <RowActions disabled={busy} onEdit={() => ubah(r)} onDelete={() => hapus(r)} /> },
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
              empty="Belum ada pemakaian. Pilih sumber dana Stok perlengkapan saat mencatat biaya."
            />
            <p className="mt-2 text-xs text-slate-500">Pemakaian diubah atau dihapus dari menu Biaya Kendaraan atau Beban Operasional.</p>
          </Card>
        </div>
      )}
    </div>
  )
}
