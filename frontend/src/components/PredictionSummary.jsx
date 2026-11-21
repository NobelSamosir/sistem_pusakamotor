import { rupiah } from '../lib/format'
import { Alert } from './ui'

export function Warnings({ warnings }) {
  if (!warnings?.length) return null
  return (
    <Alert type="warning">
      <p className="font-medium">Catatan keandalan model</p>
      <ul className="mt-1 list-disc pl-5">{warnings.map((w) => <li key={w.kode}>{w.pesan}</li>)}</ul>
    </Alert>
  )
}

export default function PredictionSummary({ p }) {
  return (
    <div className="grid gap-3 md:grid-cols-3">
      <div className="rounded-lg bg-slate-50 p-4"><p className="text-xs text-slate-500">Batas bawah (L)</p><p className="text-lg font-semibold">{rupiah(p.lower_bound)}</p></div>
      <div className="rounded-lg bg-teal-50 p-4"><p className="text-xs text-teal-800">Prediksi harga pasar</p><p className="text-lg font-semibold text-teal-900">{rupiah(p.predicted_price)}</p></div>
      <div className="rounded-lg bg-slate-50 p-4"><p className="text-xs text-slate-500">Batas atas (U)</p><p className="text-lg font-semibold">{rupiah(p.upper_bound)}</p></div>
    </div>
  )
}
