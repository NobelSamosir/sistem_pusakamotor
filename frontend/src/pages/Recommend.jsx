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


const tones = {
  'Layak dibeli': { box: 'border-emerald-200 bg-emerald-50', text: 'text-emerald-800' },
  'Margin tipis': { box: 'border-amber-200 bg-amber-50', text: 'text-amber-800' },
  'Tidak disarankan': { box: 'border-rose-200 bg-rose-50', text: 'text-rose-800' },
}

/** Skala harga kesepakatan: hijau = laba sesuai target, kuning = laba di bawah target, merah = berisiko rugi. */
function ZonaBeli({ maxAgreed, lossLimit, offer }) {
  const lo = Math.max(0, Math.min(maxAgreed * 0.8, offer * 0.95))
  const hi = Math.max(lossLimit * 1.12, offer * 1.05)
  const pos = (v) => `${Math.min(100, Math.max(0, ((v - lo) / (hi - lo)) * 100))}%`
  return (
    <div className="mt-6">
      <p className="mb-8 text-sm font-medium text-slate-700">Posisi harga kesepakatan</p>
      <div className="relative h-4 rounded-full">
        <div className="absolute inset-y-0 left-0 rounded-l-full bg-emerald-400" style={{ width: pos(maxAgreed) }} />
        <div className="absolute inset-y-0 bg-amber-300" style={{ left: pos(maxAgreed), width: `calc(${pos(lossLimit)} - ${pos(maxAgreed)})` }} />
        <div className="absolute inset-y-0 right-0 rounded-r-full bg-rose-400" style={{ left: pos(lossLimit) }} />
        <div className="absolute -top-7 -translate-x-1/2 text-center" style={{ left: pos(offer) }}>
          <p className="whitespace-nowrap text-xs font-semibold text-slate-800">Kesepakatan {rupiah(offer)}</p>
          <div className="mx-auto mt-0.5 h-9 w-1 rounded bg-slate-900" />
        </div>
        <div className="absolute top-6 -translate-x-1/2 text-center text-xs text-slate-600" style={{ left: pos(maxAgreed) }}>
          <div className="mx-auto mb-1 h-2 w-px bg-slate-500" />Maksimal<br /><b>{rupiah(maxAgreed)}</b>
        </div>
        <div className="absolute top-6 -translate-x-1/2 text-center text-xs text-slate-600" style={{ left: pos(lossLimit) }}>
          <div className="mx-auto mb-1 h-2 w-px bg-slate-500" />Batas rugi<br /><b>{rupiah(lossLimit)}</b>
        </div>
      </div>
      <div className="mt-16 flex flex-wrap gap-4 text-xs text-slate-600">
        <span className="flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-emerald-400" />Laba sesuai target</span>
        <span className="flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-amber-300" />Untung, tapi di bawah target</span>
        <span className="flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-rose-400" />Berisiko rugi</span>
      </div>
    </div>
  )
}

function HasilBeli({ rec, onRecord }) {
  const p = rec.prediction
  const r = rec.rules || {}
  const L = Number(p.lower_bound), P = Number(p.predicted_price), U = Number(p.upper_bound)
  const pl = Number(rec.target_markup)
  const offer = Number(rec.offer_price)
  const servis = Number(rec.estimated_service_cost)
  const cut = Number(rec.seller_deduction || 0)
  const maxAgreed = Number(rec.max_purchase_price)
  const maxPaid = Number(r.max_paid_to_seller ?? maxAgreed - cut)
  const lossAgreed = Number(r.loss_limit ?? L - servis + cut)
  const paid = offer - cut
  const modal = paid + servis
  const target = modal * (1 + pl)
  const capTarget = Number(r.service_cap_target ?? L / (1 + pl) - paid)
  const capRugi = Number(r.service_cap_breakeven ?? L - paid)
  const plTeks = persen(pl, 0)
  const t = tones[rec.decision_status] || tones['Tidak disarankan']
  const posisiTarget = target < L ? 'di bawah harga pasar terendah, jadi mudah laku'
    : target <= U ? 'masih di dalam range harga pasar'
      : 'di atas harga pasar tertinggi, jadi sulit laku'
  const skenario = [['Laku di harga pasar terendah (L)', L], ['Laku di harga prediksi', P], ['Laku di harga pasar tertinggi (U)', U]]
  const labaL = L - modal

  return (
    <Card title="Hasil DSS pembelian" className="mt-6" actions={<Badge>{rec.decision_status}</Badge>}>
      <Warnings warnings={p.warnings} />
      <PredictionSummary p={p} />

      <div className={`mt-5 rounded-xl border p-5 ${t.box}`}>
        <div className="grid gap-4 md:grid-cols-2">
          <div>
            <p className="text-sm text-slate-600">Uang ke penjual paling banyak</p>
            <p className="text-3xl font-bold text-slate-900">{maxPaid > 0 ? rupiah(maxPaid) : '-'}</p>
            <p className="text-xs text-slate-600">dengan perkiraan servis/pajak {rupiah(servis)}</p>
          </div>
          <div>
            <p className="text-sm text-slate-600">Harga kesepakatan maksimal</p>
            <p className="text-3xl font-bold text-slate-900">{maxPaid > 0 ? rupiah(maxAgreed) : '-'}</p>
            <p className="text-xs text-slate-600">{cut > 0 ? `bila ${rupiah(cut)} dipotong dari uang penjual` : 'tanpa potongan dari uang penjual'}</p>
          </div>
        </div>
        <p className="mt-3 text-sm text-slate-700">
          Batas ini membuat laba tetap minimal {plTeks} walaupun motor hanya laku di harga pasar terendah ({rupiah(L)}).
        </p>
        <p className={`mt-3 font-semibold ${t.text}`}>
          {rec.decision_status === 'Layak dibeli' && `Kesepakatan ${rupiah(offer)} masih ${rupiah(maxAgreed - offer)} di bawah batas. Layak dibeli; penjual menerima ${rupiah(paid)}.`}
          {rec.decision_status === 'Margin tipis' && `Kesepakatan ${rupiah(offer)} melebihi batas ${rupiah(offer - maxAgreed)}. Masih untung, tetapi laba di bawah ${plTeks}. Tawar turun ke ${rupiah(maxAgreed)} (penjual menerima ${rupiah(maxPaid)}).`}
          {rec.decision_status === 'Tidak disarankan' && (maxPaid > 0
            ? `Kesepakatan ${rupiah(offer)} terlalu tinggi. Bila motor hanya laku di harga pasar terendah, showroom rugi ${rupiah(-labaL)}. Tawar turun ke ${rupiah(maxAgreed)} atau lewati motor ini.`
            : 'Perkiraan biaya servis/pajak terlalu besar dibanding harga pasar motor ini, jadi tidak ada harga beli yang aman.')}
        </p>
      </div>

      <ZonaBeli maxAgreed={maxAgreed} lossLimit={lossAgreed} offer={offer} />

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <div>
          <p className="mb-2 text-sm font-medium text-slate-700">Hitungan bila dibeli di harga kesepakatan</p>
          <table className="w-full text-sm">
            <tbody className="divide-y divide-slate-100">
              <tr><td className="py-2 text-slate-600">Harga kesepakatan dengan penjual</td><td className="py-2 text-right">{rupiah(offer)}</td></tr>
              <tr><td className="py-2 text-slate-600">- Potongan dari uang penjual</td><td className="py-2 text-right">{rupiah(cut)}</td></tr>
              <tr><td className="py-2 font-semibold">= Uang yang diterima penjual</td><td className="py-2 text-right font-semibold">{rupiah(paid)}</td></tr>
              <tr><td className="py-2 text-slate-600">+ Perkiraan servis/pajak (dibayar showroom)</td><td className="py-2 text-right">{rupiah(servis)}</td></tr>
              <tr className="font-semibold"><td className="py-2">= Perkiraan modal motor (HPP)</td><td className="py-2 text-right">{rupiah(modal)}</td></tr>
              <tr className="font-semibold text-teal-900"><td className="py-2">Harga jual agar laba {plTeks} (modal x {(1 + pl).toLocaleString('id-ID')})</td><td className="py-2 text-right">{rupiah(target)}</td></tr>
            </tbody>
          </table>
          <p className="mt-2 text-xs text-slate-600">Harga jual {rupiah(target)} itu {posisiTarget} ({rupiah(L)} sampai {rupiah(U)}).</p>
        </div>
        <div>
          <p className="mb-2 text-sm font-medium text-slate-700">Perkiraan laba bila disepakati {rupiah(offer)}</p>
          <table className="w-full text-sm">
            <thead><tr className="text-xs text-slate-500"><th className="py-1 text-left font-medium">Bila motor</th><th className="py-1 text-right font-medium">Harga jual</th><th className="py-1 text-right font-medium">Laba</th><th className="py-1 text-right font-medium">% dari modal</th></tr></thead>
            <tbody className="divide-y divide-slate-100">
              {skenario.map(([nama, harga]) => {
                const laba = harga - modal
                return (
                  <tr key={nama}>
                    <td className="py-2 text-slate-600">{nama}</td>
                    <td className="py-2 text-right">{rupiah(harga)}</td>
                    <td className={`py-2 text-right font-semibold ${laba < 0 ? 'text-rose-600' : 'text-emerald-700'}`}>{rupiah(laba)}</td>
                    <td className={`py-2 text-right ${laba / modal < pl ? 'text-amber-700' : 'text-slate-700'}`}>{persen(laba / modal)}</td>
                  </tr>
                )
              })}
            </tbody>
          </table>
          <p className="mt-2 text-xs text-slate-600">Persen dihitung terhadap modal, sama seperti PL {plTeks}.</p>
        </div>
      </div>

      <div className="mt-6 rounded-lg border border-sky-100 bg-sky-50 p-4 text-sm text-sky-900">
        <p className="font-semibold">Kalau biaya servis/pajak ternyata berbeda dari perkiraan</p>
        <p className="mt-1">
          Dengan membayar penjual {rupiah(paid)}, laba tetap minimal {plTeks} selama total servis/pajak <b>tidak lebih dari {rupiah(Math.max(capTarget, 0))}</b>,
          dan showroom tidak rugi selama <b>tidak lebih dari {rupiah(Math.max(capRugi, 0))}</b> (bila motor laku di harga pasar terendah).
        </p>
        <p className="mt-1 text-xs text-sky-800">Biaya sebenarnya dicatat di menu Biaya Kendaraan saat dibayar, dan HPP motor mengikuti biaya sebenarnya.</p>
      </div>

      {rec.recommended_price && (
        <div className="mt-6 flex items-center justify-end gap-3">
          <span className="text-sm text-slate-600">Rekomendasi: sepakat <b>{rupiah(rec.recommended_price)}</b>, bayar penjual <b>{rupiah(Number(rec.recommended_price) - cut)}</b></span>
          <Button onClick={onRecord}>Catat pembelian dengan harga ini</Button>
        </div>
      )}
    </Card>
  )
}

function Beli() {
  const navigate = useNavigate()
  const { defaultMarkup } = useOptions()
  const [motor, setMotor] = useState(emptyMotor)
  const [pl, setPl] = useState('')
  const [service, setService] = useState('')
  const [cut, setCut] = useState('')
  const [offer, setOffer] = useState('')
  const [rec, setRec] = useState(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const plValue = pl === '' ? defaultMarkup * 100 : Number(pl)

  const submit = async (e) => {
    e.preventDefault(); setBusy(true); setError('')
    try {
      const { data } = await api.post('/dss/purchase', {
        ...motorPayload(motor), target_markup: plValue / 100, estimated_service_cost: service || 0, seller_deduction: cut || 0, offer_price: offer,
      })
      setRec(data)
    } catch (err) { setError(errorMessage(err)) } finally { setBusy(false) }
  }

  return (
    <>
      <Card title="Data calon motor">
        <Alert>{error}</Alert>
        <form onSubmit={submit}>
          <MotorFields value={motor} onChange={setMotor} />
          <div className="mt-5 grid gap-4 border-t border-slate-100 pt-5 md:grid-cols-2 xl:grid-cols-4">
            <Input label="Persentase laba diharapkan / PL (%)" type="number" min="0" max="100" step="0.5" placeholder={String(defaultMarkup * 100)} value={pl} onChange={(e) => setPl(e.target.value)} hint="Persen laba terhadap modal (HPP)" />
            <MoneyInput label="Perkiraan biaya servis/pajak" value={service} onChange={setService} hint="Perkiraan showroom, biaya sebenarnya bisa berbeda" />
            <MoneyInput label="Harga kesepakatan / tawaran penjual" value={offer} onChange={setOffer} required />
            <div>
              <MoneyInput label="Potongan dari uang penjual" value={cut} onChange={setCut} hint="Kosongkan bila penjual minta harga bersih" />
              {service && String(service) !== String(cut) && (
                <button type="button" className="mt-1 text-xs font-medium text-teal-700 hover:underline" onClick={() => setCut(service)}>Samakan dengan perkiraan servis</button>
              )}
            </div>
          </div>
          <div className="mt-5 flex justify-end"><Button disabled={busy}>{busy ? 'Menghitung...' : 'Hitung rekomendasi beli'}</Button></div>
        </form>
      </Card>
      {rec && <HasilBeli rec={rec} motor={motor} onRecord={() => navigate('/pembelian', {
        state: { motor, price: Number(rec.recommended_price), deduction: Number(rec.seller_deduction) || '', recommendationId: rec.id, status: rec.decision_status },
      })} />}
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
      <PageHeader title="Rekomendasi Harga" />
      <Tabs tabs={[{ value: 'beli', label: 'Harga beli' }, { value: 'jual', label: 'Harga jual' }]} value={tab} onChange={(v) => setParams({ tab: v })} />
      {tab === 'beli' ? <Beli /> : <Jual initialMotor={params.get('motor')} />}
    </div>
  )
}
