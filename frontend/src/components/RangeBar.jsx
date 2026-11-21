import { rupiah } from '../lib/format'

/**
 * Pita range harga pasar [L ; U] dengan penanda HPP, harga target, atau harga tawaran.
 * markers: [{ label, value, tone }]
 */
export default function RangeBar({ lower, predicted, upper, markers = [] }) {
  const L = Number(lower), U = Number(upper), P = Number(predicted)
  const values = [L, U, ...markers.map((m) => Number(m.value))].filter((v) => !Number.isNaN(v))
  const min = Math.min(...values), max = Math.max(...values)
  const pad = (max - min) * 0.12 || L * 0.1
  const lo = min - pad, hi = max + pad
  const pos = (v) => `${((v - lo) / (hi - lo)) * 100}%`
  const tone = { amber: 'bg-amber-500', rose: 'bg-rose-500', sky: 'bg-sky-600', slate: 'bg-slate-700' }

  return (
    <div className="pt-8 pb-12">
      <div className="relative h-3 rounded-full bg-slate-200">
        <div className="absolute h-3 rounded-full bg-teal-500/70" style={{ left: pos(L), width: `calc(${pos(U)} - ${pos(L)})` }} />
        <div className="absolute -top-1 h-5 w-0.5 bg-teal-900" style={{ left: pos(P) }} />
        {markers.map((m) => (
          <div key={m.label} className="absolute -top-7" style={{ left: pos(m.value) }}>
            <div className="-translate-x-1/2 whitespace-nowrap text-center text-xs font-medium text-slate-700">{m.label}</div>
            <div className={`mx-auto mt-1 h-8 w-1 -translate-x-1/2 rounded ${tone[m.tone || 'slate']}`} />
          </div>
        ))}
        <div className="absolute top-5 -translate-x-1/2 text-center text-xs text-slate-500" style={{ left: pos(L) }}>L<br />{rupiah(L)}</div>
        <div className="absolute top-5 -translate-x-1/2 text-center text-xs text-slate-500" style={{ left: pos(U) }}>U<br />{rupiah(U)}</div>
      </div>
    </div>
  )
}
