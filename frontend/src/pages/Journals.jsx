import { useState } from 'react'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { monthEnd, monthStart, rupiah, tanggal, today } from '../lib/format'
import { RowActions, useSave } from '../components/confirm'
import { Alert, Button, Card, Input, Loading, MoneyInput, PageHeader, Pagination, useFlash } from '../components/ui'

const ref = {
  purchase: 'Pembelian', vehicle_cost: 'Biaya kendaraan', sale: 'Penjualan', operating_expense: 'Beban', manual: 'Setoran modal',
  prive: 'Prive', leasing_disbursement: 'Pencairan leasing', leasing_commission: 'Komisi leasing', deposit: 'Uang muka',
  supply_purchase: 'Pembelian perlengkapan', document_service: 'Pendapatan lain',
}

export default function Journals() {
  const [from, setFrom] = useState(monthStart())
  const [to, setTo] = useState(monthEnd())
  const [page, setPage] = useState(1)
  const list = useFetch('/journals', { from, to, page })
  const [cap, setCap] = useState({ entry_date: today(), amount: '', description: 'Setoran modal pemilik' })
  const [prv, setPrv] = useState({ entry_date: today(), amount: '', description: 'Pengambilan pribadi pemilik' })
  const [error, setError] = useState('')
  const [ok, setOk] = useFlash()
  const [edit, setEdit] = useState(null)
  const { busy, run } = useSave()

  const baris = (f) => [['Tanggal', tanggal(f.entry_date)], ['Nominal', rupiah(f.amount)], ['Keterangan', f.description]]

  const saveCapital = (e) => {
    e.preventDefault(); setError('')
    run({ title: 'Catat setoran modal?', rows: baris(cap), message: 'Kas bertambah, Modal Pemilik bertambah.' }, async () => {
      try {
        await api.post('/journals/capital', cap)
        setOk('Setoran modal tercatat: Kas (debit) / Modal Pemilik (kredit).')
        setCap({ ...cap, amount: '' }); list.reload()
      } catch (err) { setError(errorMessage(err)) }
    })
  }

  const saveWithdrawal = (e) => {
    e.preventDefault(); setError('')
    run({ title: 'Catat prive pemilik?', rows: baris(prv), message: 'Kas berkurang.', danger: true, okLabel: 'Ya, catat' }, async () => {
      try {
        await api.post('/journals/withdrawal', prv)
        setOk('Prive tercatat: Prive Pemilik (debit) / Kas (kredit).')
        setPrv({ ...prv, amount: '' }); list.reload()
      } catch (err) { setError(errorMessage(err)) }
    })
  }

  const bisaUbah = (e) => e.reference_type === 'manual' || e.reference_type === 'prive'
  const nominal = (e) => e.lines.reduce((t, l) => t + Number(l.debit), 0)
  const ubah = (e) => { setEdit({ id: e.id, number: e.journal_number, prive: e.reference_type === 'prive', entry_date: e.entry_date.slice(0, 10), amount: nominal(e), description: e.description || '' }); window.scrollTo({ top: 0, behavior: 'smooth' }) }
  const simpanUbah = (ev) => {
    ev.preventDefault(); setError('')
    run({ title: `Simpan perubahan ${edit.number}?`, rows: baris(edit) }, async () => {
      try {
        await api.put(`/journals/${edit.id}`, { entry_date: edit.entry_date, amount: edit.amount, description: edit.description })
        setOk('Jurnal diubah.'); setEdit(null); list.reload()
      } catch (err) { setError(errorMessage(err)) }
    })
  }
  const hapus = (e) => run({ title: `Hapus jurnal ${e.journal_number}?`, danger: true, okLabel: 'Ya, hapus', rows: [['Tanggal', tanggal(e.entry_date)], ['Jenis', ref[e.reference_type]], ['Nominal', rupiah(nominal(e))], ['Keterangan', e.description]] }, async () => {
    setError('')
    try { await api.delete(`/journals/${e.id}`); setOk('Jurnal sudah dihapus.'); if (edit?.id === e.id) setEdit(null); list.reload() } catch (err) { setError(errorMessage(err)) }
  })

  const entries = list.data?.data || []
  const totalD = entries.flatMap((e) => e.lines).reduce((t, l) => t + Number(l.debit), 0)
  const totalK = entries.flatMap((e) => e.lines).reduce((t, l) => t + Number(l.credit), 0)

  return (
    <div>
      <PageHeader title="Jurnal Umum" />
      <Alert>{error}</Alert>
      <Alert type="success">{ok}</Alert>
      <div className="grid gap-6 xl:grid-cols-2">
      <Card title="Setoran modal pemilik">
        <form onSubmit={saveCapital} className="grid gap-4 md:grid-cols-4">
          <Input label="Tanggal" type="date" value={cap.entry_date} onChange={(e) => setCap({ ...cap, entry_date: e.target.value })} required />
          <MoneyInput label="Nominal" value={cap.amount} onChange={(v) => setCap({ ...cap, amount: v })} required />
          <Input label="Keterangan" value={cap.description} onChange={(e) => setCap({ ...cap, description: e.target.value })} />
          <div className="flex items-end"><Button className="w-full" disabled={busy}>Catat setoran</Button></div>
        </form>
      </Card>
      <Card title="Prive (pemilik mengambil uang pribadi)">
        <form onSubmit={saveWithdrawal} className="grid gap-4 md:grid-cols-4">
          <Input label="Tanggal" type="date" value={prv.entry_date} onChange={(e) => setPrv({ ...prv, entry_date: e.target.value })} required />
          <MoneyInput label="Nominal" value={prv.amount} onChange={(v) => setPrv({ ...prv, amount: v })} required />
          <Input label="Keterangan" value={prv.description} onChange={(e) => setPrv({ ...prv, description: e.target.value })} />
          <div className="flex items-end"><Button variant="danger" className="w-full" disabled={busy}>Catat prive</Button></div>
        </form>
      </Card>
      </div>
      {edit && (
        <Card title={`Ubah ${edit.prive ? 'prive' : 'setoran modal'} ${edit.number}`} className="mt-6 ring-2 ring-teal-200">
          <form onSubmit={simpanUbah} className="grid gap-4 md:grid-cols-4">
            <Input label="Tanggal" type="date" value={edit.entry_date} onChange={(e) => setEdit({ ...edit, entry_date: e.target.value })} required />
            <MoneyInput label="Nominal" value={edit.amount} onChange={(v) => setEdit({ ...edit, amount: v })} required />
            <Input label="Keterangan" value={edit.description} onChange={(e) => setEdit({ ...edit, description: e.target.value })} />
            <div className="flex items-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setEdit(null)}>Batal</Button>
              <Button disabled={busy}>Simpan</Button>
            </div>
          </form>
        </Card>
      )}
      <Card title="Daftar jurnal" className="mt-6">
        <p className="mb-3 text-xs text-slate-500">Jurnal dari transaksi (pembelian, penjualan, biaya, dan lainnya) diubah atau dihapus lewat halaman transaksinya, supaya data dan jurnal tetap sama. Setoran modal dan prive bisa diubah di sini.</p>
        <div className="mb-4 flex gap-3">
          <Input label="Dari" type="date" value={from} onChange={(e) => { setFrom(e.target.value); setPage(1) }} />
          <Input label="Sampai" type="date" value={to} onChange={(e) => { setTo(e.target.value); setPage(1) }} />
        </div>
        {list.loading ? <Loading /> : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                  <th className="px-3 py-2">Tanggal</th><th className="px-3 py-2">No. / Sumber</th><th className="px-3 py-2">Akun</th>
                  <th className="px-3 py-2 text-right">Debit</th><th className="px-3 py-2 text-right">Kredit</th><th className="px-3 py-2" />
                </tr>
              </thead>
              <tbody>
                {entries.length === 0 && <tr><td colSpan={6} className="py-6 text-center text-slate-400">Belum ada jurnal di periode ini.</td></tr>}
                {entries.map((e) => e.lines.map((l, i) => (
                  <tr key={l.id} className={i === e.lines.length - 1 ? 'border-b border-slate-200' : ''}>
                    <td className="px-3 py-1.5 align-top">{i === 0 ? tanggal(e.entry_date) : ''}</td>
                    <td className="px-3 py-1.5 align-top">{i === 0 && (<><p className="font-medium">{e.journal_number}</p><p className="text-xs text-slate-500">{ref[e.reference_type]} · {e.description}</p></>)}</td>
                    <td className={`px-3 py-1.5 ${Number(l.credit) > 0 ? 'pl-10' : ''}`}>{l.account.account_code} {l.account.account_name}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{Number(l.debit) > 0 ? rupiah(l.debit) : ''}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{Number(l.credit) > 0 ? rupiah(l.credit) : ''}</td>
                    <td className="px-3 py-1.5 align-top">{i === 0 && bisaUbah(e) && <RowActions disabled={busy} onEdit={() => ubah(e)} onDelete={() => hapus(e)} />}</td>
                  </tr>
                )))}
              </tbody>
              <tfoot>
                <tr className="font-semibold"><td colSpan={3} className="px-3 py-2">Total halaman ini</td><td className="px-3 py-2 text-right">{rupiah(totalD)}</td><td className="px-3 py-2 text-right">{rupiah(totalK)}</td><td /></tr>
              </tfoot>
            </table>
          </div>
        )}
        <Pagination meta={list.data} onPage={setPage} />
      </Card>
    </div>
  )
}
