import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import api, { errorMessage } from '../lib/api'
import useOptions from '../lib/useOptions'
import { persen, rupiah } from '../lib/format'
import MotorFields, { emptyMotor, motorPayload } from '../components/MotorFields'
import MotorPicker from '../components/MotorPicker'
import PredictionSummary, { Warnings } from '../components/PredictionSummary'
import RangeBar from '../components/RangeBar'
import { Alert, Badge, Button, Card, Input, MoneyInput, PageHeader, Tabs } from '../components/ui'

const Info = ({ label, value, strong }) => (
  <div className="rounded-lg bg-slate-50 p-4">
    <p className="text-xs text-slate-500">{label}</p>
    <p className={`mt-1 ${strong ? 'text-xl font-semibold text-teal-900' : 'font-semibold'}`}>{value}</p>
  </div>
)

function Beli() {
  const navigate = useNavigate()
  const { defaultMarkup } = useOptions()
  const [motor, setMotor] = useState(emptyMotor)
  const [pl, setPl] = useState('')
  const [service, setService] = useState('')
  const [offer, setOffer] = useState('')
  const [rec, setRec] = useState(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const plValue = pl === '' ? defaultMarkup * 100 : Number(pl)

  const submit = async (e) => {
    e.preventDefault(); setBusy(true); setError('')
    try {
      const { data } = await api.post('/dss/purchase', {
        ...motorPayload(motor), target_markup: plValue / 100, estimated_service_cost: service || 0, offer_price: offer,
      })
      setRec(data)
    } catch (err) { setError(errorMessage(err)) } finally { setBusy(false) }
  }

  const p = rec?.prediction
  const lossLimit = p ? Number(p.lower_bound) - Number(rec.estimated_service_cost) : null

  return (
    <>
      <Card title="Data calon motor">
        <Alert>{error}</Alert>
        <form onSubmit={submit}>
          <MotorFields value={motor} onChange={setMotor} />
          <div className="mt-5 grid gap-4 border-t border-slate-100 pt-5 md:grid-cols-3">
            <Input label="Persentase laba diharapkan / PL (%)" type="number" min="0" max="100" step="0.5" placeholder={String(defaultMarkup * 100)} value={pl} onChange={(e) => setPl(e.target.value)} hint="Persen laba terhadap HPP" />
            <MoneyInput label="Estimasi biaya servis" value={service} onChange={setService} />
            <MoneyInput label="Harga tawaran penjual" value={offer} onChange={setOffer} required />
          </div>
          <div className="mt-5 flex justify-end"><Button disabled={busy}>{busy ? 'Menghitung...' : 'Hitung rekomendasi beli'}</Button></div>
        </form>
      </Card>
      {rec && (
        <Card title="Hasil DSS pembelian" className="mt-6" actions={<Badge>{rec.decision_status}</Badge>}>
          <Warnings warnings={p.warnings} />
          <PredictionSummary p={p} />
          <RangeBar lower={p.lower_bound} predicted={p.predicted_price} upper={p.upper_bound}
            markers={[{ label: 'Tawaran', value: rec.offer_price, tone: 'amber' }, { label: 'HB maks', value: rec.max_purchase_price, tone: 'sky' }]} />
          <div className="grid gap-3 md:grid-cols-4">
            <Info label="Harga beli maksimal (HB maks)" value={rupiah(rec.max_purchase_price)} />
            <Info label="Batas rugi (L - estimasi servis)" value={rupiah(lossLimit)} />
            <Info label="Rekomendasi harga beli" value={rec.recommended_price ? rupiah(rec.recommended_price) : 'Tidak disarankan membeli'} strong />
            <Info label="Perkiraan laba bila laku di L" value={rec.expected_profit ? `${rupiah(rec.expected_profit)} (${persen(rec.expected_margin)})` : '-'} />
          </div>
          <p className="mt-4 text-sm text-slate-600">
            {rec.decision_status === 'Layak dibeli' && 'Harga tawaran masih di bawah harga beli maksimal, target laba tercapai bila motor laku di harga pasar terendah.'}
            {rec.decision_status === 'Margin tipis' && `Tawaran di atas HB maks. Coba tawar sampai ${rupiah(rec.max_purchase_price)} agar target laba ${persen(rec.target_markup, 0)} tetap tercapai.`}
            {rec.decision_status === 'Tidak disarankan' && 'Tawaran terlalu tinggi: motor berisiko rugi bila hanya laku di harga pasar terendah.'}
          </p>
          {rec.recommended_price && (
            <div className="mt-5 flex justify-end">
              <Button onClick={() => navigate('/pembelian', {
                state: { motor, price: Number(rec.recommended_price), recommendationId: rec.id, status: rec.decision_status },
              })}>Catat pembelian dengan harga ini</Button>
            </div>
          )}
        </Card>
      )}
    </>
  )
}

function Jual({ initialMotor }) {
  const navigate = useNavigate()
  const { defaultMarkup } = useOptions()
  const [motorId, setMotorId] = useState(initialMotor || '')
  const [pl, setPl] = useState('')
  const [rec, setRec] = useState(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const plValue = pl === '' ? defaultMarkup * 100 : Number(pl)

  const submit = async (e) => {
    e.preventDefault(); setBusy(true); setError('')
    try {
      const { data } = await api.post(`/dss/sale/${motorId}`, { target_markup: plValue / 100 })
      setRec(data)
    } catch (err) { setError(errorMessage(err)) } finally { setBusy(false) }
  }

  const p = rec?.prediction
  const ket = {
    'Di bawah pasar': 'Harga target lebih rendah dari harga pasar terendah, jadi sistem menyarankan menjual di batas bawah (L).',
    'Sesuai pasar': 'Harga target berada di dalam range harga pasar, jadi harga target dapat langsung dipakai.',
    'Di atas pasar': 'Harga target melebihi harga pasar tertinggi, jadi sistem menyarankan batas atas (U) agar motor tetap kompetitif.',
    'Berisiko rugi': 'HPP sudah melebihi harga pasar tertinggi. Tidak ada rekomendasi otomatis; keputusan harga ada pada pemilik.',
  }

  return (
    <>
      <Card title="Pilih unit di stok">
        <Alert>{error}</Alert>
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-3">
          <div className="md:col-span-2"><MotorPicker value={motorId} onChange={(v) => { setMotorId(v); setRec(null) }} /></div>
          <Input label="PL (%)" type="number" min="0" max="100" step="0.5" placeholder={String(defaultMarkup * 100)} value={pl} onChange={(e) => setPl(e.target.value)} hint="Persen laba terhadap HPP" />
          <div className="md:col-span-3 flex justify-end"><Button disabled={busy || !motorId}>{busy ? 'Menghitung...' : 'Hitung rekomendasi jual'}</Button></div>
        </form>
      </Card>
      {rec && (
        <Card title="Hasil DSS penjualan" className="mt-6" actions={<Badge>{rec.decision_status}</Badge>}>
          <Warnings warnings={p.warnings} />
          <PredictionSummary p={p} />
          <RangeBar lower={p.lower_bound} predicted={p.predicted_price} upper={p.upper_bound}
            markers={[{ label: 'HPP', value: rec.hpp, tone: 'rose' }, { label: 'Harga target', value: rec.target_price, tone: 'amber' }]} />
          <div className="grid gap-3 md:grid-cols-4">
            <Info label="HPP unit (dari SIA)" value={rupiah(rec.hpp)} />
            <Info label={`Harga target (HPP x ${(1 + Number(rec.target_markup)).toLocaleString('id-ID')})`} value={rupiah(rec.target_price)} />
            <Info label="Rekomendasi harga jual" value={rec.recommended_price ? rupiah(rec.recommended_price) : 'Keputusan pemilik'} strong />
            <Info label="Perkiraan laba / margin" value={rec.expected_profit ? `${rupiah(rec.expected_profit)} (${persen(rec.expected_margin)})` : '-'} />
          </div>
          <p className="mt-4 text-sm text-slate-600">{ket[rec.decision_status]}</p>
          {rec.recommended_price && (
            <div className="mt-5 flex justify-end">
              <Button onClick={() => navigate(`/penjualan?motor=${motorId}&harga=${Math.round(rec.recommended_price)}`)}>Catat penjualan dengan harga ini</Button>
            </div>
          )}
        </Card>
      )}
    </>
  )
}

export default function Recommend() {
  const [params, setParams] = useSearchParams()
  const tab = params.get('tab') || 'beli'
  return (
    <div>
      <PageHeader title="Rekomendasi Harga" subtitle="DSS menggabungkan range harga pasar dari model dengan HPP dan target laba." />
      <Tabs tabs={[{ value: 'beli', label: 'Harga beli' }, { value: 'jual', label: 'Harga jual' }]} value={tab} onChange={(v) => setParams({ tab: v })} />
      {tab === 'beli' ? <Beli /> : <Jual initialMotor={params.get('motor')} />}
    </div>
  )
}
