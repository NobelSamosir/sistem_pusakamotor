import { useState } from 'react'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { monthEnd, monthStart, rupiah, tanggal, today } from '../lib/format'
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

  const saveCapital = async (e) => {
    e.preventDefault(); setError('')
    try {
      await api.post('/journals/capital', cap)
      setOk('Setoran modal tercatat: Kas (debit) / Modal Pemilik (kredit).')
      setCap({ ...cap, amount: '' }); list.reload()
    } catch (err) { setError(errorMessage(err)) }
  }

  const saveWithdrawal = async (e) => {
    e.preventDefault(); setError('')
    if (!window.confirm('Catat pengambilan uang pribadi pemilik? Kas akan berkurang.')) return
    try {
      await api.post('/journals/withdrawal', prv)
      setOk('Prive tercatat: Prive Pemilik (debit) / Kas (kredit).')
      setPrv({ ...prv, amount: '' }); list.reload()
    } catch (err) { setError(errorMessage(err)) }
  }

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
          <div className="flex items-end"><Button className="w-full">Catat setoran</Button></div>
        </form>
      </Card>
      <Card title="Prive (pemilik mengambil uang pribadi)">
        <form onSubmit={saveWithdrawal} className="grid gap-4 md:grid-cols-4">
          <Input label="Tanggal" type="date" value={prv.entry_date} onChange={(e) => setPrv({ ...prv, entry_date: e.target.value })} required />
          <MoneyInput label="Nominal" value={prv.amount} onChange={(v) => setPrv({ ...prv, amount: v })} required />
          <Input label="Keterangan" value={prv.description} onChange={(e) => setPrv({ ...prv, description: e.target.value })} />
          <div className="flex items-end"><Button variant="danger" className="w-full">Catat prive</Button></div>
        </form>
      </Card>
      </div>
      <Card title="Daftar jurnal" className="mt-6">
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
                  <th className="px-3 py-2 text-right">Debit</th><th className="px-3 py-2 text-right">Kredit</th>
                </tr>
              </thead>
              <tbody>
                {entries.length === 0 && <tr><td colSpan={5} className="py-6 text-center text-slate-400">Belum ada jurnal di periode ini.</td></tr>}
                {entries.map((e) => e.lines.map((l, i) => (
                  <tr key={l.id} className={i === e.lines.length - 1 ? 'border-b border-slate-200' : ''}>
                    <td className="px-3 py-1.5 align-top">{i === 0 ? tanggal(e.entry_date) : ''}</td>
                    <td className="px-3 py-1.5 align-top">{i === 0 && (<><p className="font-medium">{e.journal_number}</p><p className="text-xs text-slate-500">{ref[e.reference_type]} · {e.description}</p></>)}</td>
                    <td className={`px-3 py-1.5 ${Number(l.credit) > 0 ? 'pl-10' : ''}`}>{l.account.account_code} {l.account.account_name}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{Number(l.debit) > 0 ? rupiah(l.debit) : ''}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{Number(l.credit) > 0 ? rupiah(l.credit) : ''}</td>
                  </tr>
                )))}
              </tbody>
              <tfoot>
                <tr className="font-semibold"><td colSpan={3} className="px-3 py-2">Total halaman ini</td><td className="px-3 py-2 text-right">{rupiah(totalD)}</td><td className="px-3 py-2 text-right">{rupiah(totalK)}</td></tr>
              </tfoot>
            </table>
          </div>
        )}
        <Pagination meta={list.data} onPage={setPage} />
      </Card>
    </div>
  )
}
