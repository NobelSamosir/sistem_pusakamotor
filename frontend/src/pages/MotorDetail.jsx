import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import api, { errorMessage } from '../lib/api'
import MotorFields, { emptyMotor, motorPayload } from '../components/MotorFields'
import { useSave } from '../components/confirm'
import useFetch from '../lib/useFetch'
import { persen, rupiah, tanggal } from '../lib/format'
import { Alert, Badge, Button, Card, Input, Loading, PageHeader, Table, Textarea } from '../components/ui'

const IDENT = ['frame_number', 'engine_number', 'license_plate', 'bpkb_number', 'color', 'condition_notes']

const Row = ({ k, v }) => (
  <div className="flex justify-between border-b border-slate-100 py-2 text-sm">
    <span className="text-slate-500">{k}</span><span className="text-right font-medium text-slate-800">{v}</span>
  </div>
)

export default function MotorDetail() {
  const { id } = useParams()
  const { data: m, loading, error, reload } = useFetch(`/motorcycles/${id}`)
  const [edit, setEdit] = useState(null)
  const [msg, setMsg] = useState({ ok: '', err: '' })
  const { busy, run } = useSave()
  if (loading) return <Loading />
  if (error) return <Alert>{error}</Alert>

  const dok = [m.has_bpkb && 'BPKB', m.has_stnk && 'STNK', m.has_faktur && 'Faktur', m.has_buku_servis && 'Buku servis'].filter(Boolean).join(', ') || 'Tidak ada'
  const sold = m.status === 'terjual'
  const gone = sold
  const deposit = (m.deposits || []).find((x) => x.status === 'aktif')

  const mulaiUbah = () => {
    const v = {}
    Object.keys(emptyMotor).forEach((k) => { v[k] = m[k] ?? emptyMotor[k] })
    IDENT.forEach((k) => { v[k] = m[k] || '' })
    setEdit(v); setMsg({ ok: '', err: '' })
  }
  const setE = (k) => (e) => setEdit({ ...edit, [k]: e.target.value })
  const simpan = (e) => {
    e.preventDefault()
    run({ title: 'Simpan perubahan data motor?', rows: [['Unit', `${edit.brand} ${edit.model} ${edit.year}`], ['Nomor rangka', edit.frame_number], ['Nomor mesin', edit.engine_number]], message: 'Perubahan ini tidak mengubah jurnal.' }, async () => {
      try {
        const body = { ...motorPayload(edit) }
        IDENT.forEach((k) => { if (body[k] === '') body[k] = null })
        await api.put(`/motorcycles/${m.id}`, body)
        setEdit(null); setMsg({ ok: 'Data motor diubah.', err: '' }); reload()
      } catch (err) { setMsg({ ok: '', err: errorMessage(err) }) }
    })
  }

  return (
    <div>
      <PageHeader
        title={`${m.brand} ${m.model} ${m.year}`}
        subtitle={`${m.vehicle_code} · Nomor rangka ${m.frame_number}`}
        actions={!gone && (
          <>
            <Button variant="secondary" onClick={mulaiUbah}>Ubah data motor</Button>
            <Link to={`/biaya?motor=${m.id}`}><Button variant="secondary">+ Biaya</Button></Link>
            <Link to={`/rekomendasi?tab=jual&motor=${m.id}`}><Button variant="secondary">Rekomendasi harga jual</Button></Link>
            <Link to={`/penjualan?motor=${m.id}`}><Button>Catat penjualan</Button></Link>
          </>
        )}
      />
      <Alert>{msg.err}</Alert>
      <Alert type="success">{msg.ok}</Alert>
      {edit && (
        <Card title="Ubah data motor" className="mb-6 ring-2 ring-teal-200">
          <form onSubmit={simpan} className="space-y-5">
            <div className="grid gap-4 md:grid-cols-3">
              <Input label="Nomor rangka" value={edit.frame_number} onChange={setE('frame_number')} required />
              <Input label="Nomor mesin" value={edit.engine_number} onChange={setE('engine_number')} required />
              <Input label="Nomor polisi" value={edit.license_plate} onChange={setE('license_plate')} />
              <Input label="Nomor BPKB" value={edit.bpkb_number} onChange={setE('bpkb_number')} />
              <Input label="Warna" value={edit.color} onChange={setE('color')} />
              <Textarea label="Catatan kondisi" value={edit.condition_notes} onChange={setE('condition_notes')} />
            </div>
            <MotorFields value={edit} onChange={setEdit} />
            <div className="flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setEdit(null)}>Batal ubah</Button>
              <Button disabled={busy}>{busy ? 'Menyimpan...' : 'Simpan perubahan'}</Button>
            </div>
          </form>
        </Card>
      )}
      {deposit && (
        <Alert type="warning">
          Dipesan {deposit.customer?.customer_name} dengan uang muka {rupiah(deposit.amount)} ({deposit.deposit_number}, {tanggal(deposit.deposit_date)}).
        </Alert>
      )}
      <div className="grid gap-6 lg:grid-cols-3">
        <Card title="Identitas dan karakteristik">
          <Row k="Status" v={<Badge>{m.status}</Badge>} />
          <Row k="Nomor mesin" v={m.engine_number} />
          <Row k="Nomor polisi" v={m.license_plate || '-'} />
          <Row k="Nomor BPKB" v={m.bpkb_number || '-'} />
          <Row k="Warna" v={m.color || '-'} />
          <Row k="Transmisi" v={m.transmission} />
          <Row k="Kapasitas mesin" v={m.engine_capacity} />
          <Row k="Tipe bodi" v={m.body_type} />
          <Row k="Jarak tempuh" v={`${Number(m.mileage).toLocaleString('id-ID')} km`} />
          <Row k="Kepemilikan" v={m.ownership_status} />
          <Row k="Dokumen" v={dok} />
        </Card>

        <Card title="HPP unit (identifikasi khusus)" className="lg:col-span-2">
          <Row k={`Harga beli (${tanggal(m.purchase?.purchase_date)}, dari ${m.purchase?.supplier?.supplier_name || '-'})`} v={rupiah(m.purchase?.purchase_price)} />
          <Table
            columns={[
              { key: 'cost_date', label: 'Tanggal', render: (r) => tanggal(r.cost_date) },
              { key: 'cost_category', label: 'Kategori' },
              { key: 'description', label: 'Keterangan', render: (r) => r.description || '-' },
              { key: 'hpp', label: 'Masuk HPP', render: (r) => (r.hpp_included ? 'Ya' : 'Tidak (beban)') },
              { key: 'amount', label: 'Nominal', right: true, render: (r) => rupiah(r.amount) },
            ]}
            rows={m.costs}
            empty="Belum ada biaya kendaraan."
          />
          <div className="mt-3 flex justify-between rounded-lg bg-teal-50 px-4 py-3 font-semibold text-teal-900">
            <span>HPP {sold ? 'saat dijual' : 'berjalan'}</span>
            <span>{rupiah(sold ? m.sale?.hpp_amount : m.hpp_current)}</span>
          </div>
          {sold && m.sale && (
            <div className="mt-4 grid gap-3 md:grid-cols-4">
              <div><p className="text-xs text-slate-500">Harga jual</p><p className="font-semibold">{rupiah(m.sale.sale_price)}</p></div>
              <div><p className="text-xs text-slate-500">Laba kotor</p><p className="font-semibold text-emerald-700">{rupiah(m.sale.gross_profit)}</p></div>
              <div><p className="text-xs text-slate-500">Margin</p><p className="font-semibold">{persen(m.sale.gross_profit / m.sale.sale_price)}</p></div>
              <div><p className="text-xs text-slate-500">Pembeli</p><p className="font-semibold">{m.sale.customer?.customer_name} · {tanggal(m.sale.sale_date)}</p></div>
              {m.sale.sale_type === 'kredit' && (
                <p className="md:col-span-4 text-sm text-sky-800">
                  Kredit melalui {m.sale.leasing_company}: DP {rupiah(m.sale.down_payment)}, dibiayai {rupiah(m.sale.financed_amount)}, pencairan {m.sale.disbursement_status}.
                </p>
              )}
            </div>
          )}
        </Card>
      </div>

      <Card title="Riwayat rekomendasi harga" className="mt-6">
        <Table
          columns={[
            { key: 'created_at', label: 'Tanggal', render: (r) => tanggal(r.created_at) },
            { key: 'recommendation_type', label: 'Jenis', render: (r) => (r.recommendation_type === 'jual' ? 'Harga jual' : 'Harga beli') },
            { key: 'target_markup', label: 'PL', render: (r) => persen(r.target_markup, 0) },
            { key: 'decision_status', label: 'Status', render: (r) => <Badge>{r.decision_status}</Badge> },
            { key: 'recommended_price', label: 'Rekomendasi', right: true, render: (r) => rupiah(r.recommended_price) },
          ]}
          rows={m.recommendations}
          empty="Belum ada rekomendasi untuk unit ini."
        />
      </Card>
    </div>
  )
}
