import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { rupiah, tanggal, today } from '../lib/format'
import MotorPicker from '../components/MotorPicker'
import { RowActions, useSave } from '../components/confirm'
import { Alert, Badge, Button, Card, Checkbox, Input, MoneyInput, PageHeader, Pagination, Select, Table, sumberOptions, useFlash } from '../components/ui'

const kategori = [
  { value: 'servis', label: 'Servis' }, { value: 'sparepart', label: 'Sparepart' },
  { value: 'cat_body', label: 'Cat dan body' }, { value: 'administrasi', label: 'Administrasi' }, { value: 'lainnya', label: 'Lainnya' },
]

const namaKategori = { servis: 'Servis', sparepart: 'Sparepart', cat_body: 'Cat dan body', administrasi: 'Administrasi/pajak', lainnya: 'Lainnya' }

export default function Costs() {
  const [params] = useSearchParams()
  const [form, setForm] = useState({
    motorcycle_id: params.get('motor') || '', cost_date: today(), cost_category: 'servis', description: '', amount: '', hpp_included: true, source: 'kas',
  })
  const [edit, setEdit] = useState(null) // baris yang sedang diubah
  const { busy, run } = useSave()
  const [error, setError] = useState('')
  const [ok, setOk] = useFlash()
  const [page, setPage] = useState(1)
  const list = useFetch('/vehicle-costs', { page })
  const hpp = useFetch(form.motorcycle_id ? `/motorcycles/${form.motorcycle_id}` : null)
  const supplies = useFetch('/supplies')

  const ringkas = (f, unit) => [['Unit', unit], ['Tanggal', tanggal(f.cost_date)], ['Kategori', namaKategori[f.cost_category]], ['Keterangan', f.description], ['Sumber dana', f.source === 'perlengkapan' ? 'Stok perlengkapan' : 'Kas'], ['Masuk HPP', f.hpp_included ? 'Ya' : 'Tidak'], ['Nominal', rupiah(f.amount)]]
  const unitLabelOf = (m) => (m ? `${m.vehicle_code} · ${m.brand} ${m.model}` : '')
  const reset = () => { setEdit(null); setForm({ ...form, description: '', amount: '' }) }

  const submit = (e) => {
    e.preventDefault(); setError('')
    const unit = edit ? unitLabelOf(edit.motorcycle) : unitLabelOf(hpp.data)
    run({ title: edit ? 'Simpan perubahan biaya?' : 'Simpan biaya ini?', rows: ringkas(form, unit) }, async () => {
      try {
        if (edit) await api.put(`/vehicle-costs/${edit.id}`, form)
        else await api.post('/vehicle-costs', { ...form, motorcycle_id: Number(form.motorcycle_id) })
        setOk(edit ? 'Biaya diubah dan jurnalnya diperbarui.' : 'Biaya tersimpan dan jurnal otomatis dibuat.')
        reset(); list.reload(); hpp.reload(); supplies.reload()
      } catch (err) { setError(errorMessage(err)) }
    })
  }

  const ubah = (r) => {
    setEdit(r)
    setForm({ motorcycle_id: String(r.motorcycle_id), cost_date: r.cost_date.slice(0, 10), cost_category: r.cost_category, description: r.description || '', amount: Number(r.amount), hpp_included: !!r.hpp_included, source: r.source || 'kas' })
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const hapus = (r) => run({ title: 'Hapus biaya ini?', danger: true, okLabel: 'Ya, hapus', message: 'Jurnalnya ikut dihapus dan HPP unit berkurang.', rows: ringkas({ ...r, cost_date: r.cost_date }, unitLabelOf(r.motorcycle)) }, async () => {
    setError('')
    try { await api.delete(`/vehicle-costs/${r.id}`); setOk('Biaya dan jurnalnya sudah dihapus.'); if (edit?.id === r.id) reset(); list.reload(); hpp.reload(); supplies.reload() } catch (err) { setError(errorMessage(err)) }
  })

  return (
    <div>
      <PageHeader title="Biaya Kendaraan" />
      <Card title={edit ? 'Ubah biaya' : undefined}>
        <Alert>{error}</Alert>
        <Alert type="success">{ok}</Alert>
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-3">
          <div className="md:col-span-2">
            {edit
              ? <Input label="Unit motor" value={unitLabelOf(edit.motorcycle)} disabled />
              : <MotorPicker value={form.motorcycle_id} onChange={(v) => setForm({ ...form, motorcycle_id: v })} />}
          </div>
          <div className="rounded-lg bg-teal-50 px-4 py-3">
            <p className="text-xs text-teal-800">HPP berjalan unit ini</p>
            <p className="text-lg font-semibold text-teal-900">{hpp.data ? rupiah(hpp.data.hpp_current) : '-'}</p>
          </div>
          <Input label="Tanggal" type="date" value={form.cost_date} onChange={(e) => setForm({ ...form, cost_date: e.target.value })} required />
          <Select label="Kategori" options={kategori} value={form.cost_category} onChange={(e) => setForm({ ...form, cost_category: e.target.value })} required />
          <MoneyInput label="Nominal" value={form.amount} onChange={(v) => setForm({ ...form, amount: v })} required />
          <Select
            label="Sumber dana"
            options={sumberOptions}
            value={form.source}
            onChange={(e) => setForm({ ...form, source: e.target.value })}
            hint={form.source === 'perlengkapan' ? `Saldo perlengkapan ${rupiah(supplies.data?.balance)}. Contoh: cat, dempul, amplas yang dipakai sendiri.` : 'Dibayar tunai, misalnya servis di bengkel luar.'}
            required
          />
          <Input label="Keterangan" className="md:col-span-1" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} placeholder="Ganti oli dan kampas rem" />
          <div className="flex items-end pb-2">
            <Checkbox label="Masuk HPP unit" checked={form.hpp_included} onChange={(e) => setForm({ ...form, hpp_included: e.target.checked })} />
          </div>
          <div className="md:col-span-3 flex justify-end gap-2">
            {edit && <Button type="button" variant="secondary" onClick={reset}>Batal ubah</Button>}
            <Button disabled={busy}>{busy ? 'Menyimpan...' : edit ? 'Simpan perubahan' : 'Simpan biaya'}</Button>
          </div>
        </form>
      </Card>
      <Card title="Riwayat biaya" className="mt-6">
        <Table
          columns={[
            { key: 'cost_date', label: 'Tanggal', render: (r) => tanggal(r.cost_date) },
            { key: 'unit', label: 'Unit', render: (r) => `${r.motorcycle.vehicle_code} · ${r.motorcycle.brand} ${r.motorcycle.model}` },
            { key: 'cost_category', label: 'Kategori', render: (r) => namaKategori[r.cost_category] || r.cost_category },
            { key: 'description', label: 'Keterangan', render: (r) => r.description || '-' },
            { key: 'source', label: 'Sumber', render: (r) => (r.source === 'perlengkapan' ? 'Perlengkapan' : 'Kas') },
            { key: 'hpp_included', label: 'Masuk HPP', render: (r) => (r.hpp_included ? 'Ya' : 'Tidak') },
            { key: 'amount', label: 'Nominal', right: true, render: (r) => rupiah(r.amount) },
            { key: 'aksi', label: '', render: (r) => <RowActions disabled={busy} onEdit={() => ubah(r)} onDelete={() => hapus(r)} /> },
          ]}
          rows={list.data?.data}
        />
        <Pagination meta={list.data} onPage={setPage} />
      </Card>
    </div>
  )
}
