import { Link, useParams } from 'react-router-dom'
import useFetch from '../lib/useFetch'
import { persen, rupiah, tanggal } from '../lib/format'
import { Alert, Badge, Button, Card, Loading, PageHeader, Table } from '../components/ui'

const Row = ({ k, v }) => (
  <div className="flex justify-between border-b border-slate-100 py-2 text-sm">
    <span className="text-slate-500">{k}</span><span className="text-right font-medium text-slate-800">{v}</span>
  </div>
)

export default function MotorDetail() {
  const { id } = useParams()
  const { data: m, loading, error } = useFetch(`/motorcycles/${id}`)
  if (loading) return <Loading />
  if (error) return <Alert>{error}</Alert>

  const dok = [m.has_bpkb && 'BPKB', m.has_stnk && 'STNK', m.has_faktur && 'Faktur', m.has_buku_servis && 'Buku servis'].filter(Boolean).join(', ') || 'Tidak ada'
  const sold = m.status === 'terjual'
  const gone = sold
  const deposit = (m.deposits || []).find((x) => x.status === 'aktif')

  return (
    <div>
      <PageHeader
        title={`${m.brand} ${m.model} ${m.year}`}
        subtitle={`${m.vehicle_code} · Nomor rangka ${m.frame_number}`}
        actions={!gone && (
          <>
            <Link to={`/biaya?motor=${m.id}`}><Button variant="secondary">+ Biaya</Button></Link>
            <Link to={`/rekomendasi?tab=jual&motor=${m.id}`}><Button variant="secondary">Rekomendasi harga jual</Button></Link>
            <Link to={`/penjualan?motor=${m.id}`}><Button>Catat penjualan</Button></Link>
          </>
        )}
      />
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
