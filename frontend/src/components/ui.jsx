import { useEffect, useState } from 'react'

export function PageHeader({ title, subtitle, actions }) {
  return (
    <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 className="text-2xl font-semibold text-slate-800">{title}</h1>
        {subtitle && <p className="mt-1 text-sm text-slate-500">{subtitle}</p>}
      </div>
      {actions && <div className="no-print flex gap-2">{actions}</div>}
    </div>
  )
}

export function Card({ title, children, className = '', actions }) {
  return (
    <section className={`rounded-xl border border-slate-200 bg-white p-5 shadow-sm ${className}`}>
      {(title || actions) && (
        <div className="mb-4 flex items-center justify-between gap-2">
          {title && <h2 className="font-semibold text-slate-800">{title}</h2>}
          {actions}
        </div>
      )}
      {children}
    </section>
  )
}

const btn = {
  primary: 'bg-teal-700 text-white hover:bg-teal-800 disabled:bg-teal-300',
  secondary: 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50',
  danger: 'bg-rose-600 text-white hover:bg-rose-700',
  ghost: 'text-teal-700 hover:bg-teal-50',
}

export function Button({ variant = 'primary', className = '', ...props }) {
  return (
    <button
      className={`inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition disabled:cursor-not-allowed ${btn[variant]} ${className}`}
      {...props}
    />
  )
}

const inputCls = 'mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-100 disabled:bg-slate-50'

export function Field({ label, hint, children, className = '' }) {
  return (
    <div className={className}>
      <label>{label}</label>
      {children}
      {hint && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
    </div>
  )
}

export function Input({ label, hint, className, ...props }) {
  return (
    <Field label={label} hint={hint} className={className}>
      <input className={inputCls} {...props} />
    </Field>
  )
}

/** Input uang: menampilkan pemisah ribuan, mengirim angka murni. */
export function MoneyInput({ label, hint, value, onChange, className, ...props }) {
  const shown = value === '' || value === null || value === undefined ? '' : Number(value).toLocaleString('id-ID')
  return (
    <Field label={label} hint={hint} className={className}>
      <div className="relative">
        <span className="pointer-events-none absolute left-3 top-1/2 mt-0.5 -translate-y-1/2 text-sm text-slate-400">Rp</span>
        <input
          className={`${inputCls} pl-9`}
          inputMode="numeric"
          value={shown}
          onChange={(e) => {
            const digits = e.target.value.replace(/\D/g, '')
            onChange(digits === '' ? '' : Number(digits))
          }}
          {...props}
        />
      </div>
    </Field>
  )
}

export function Select({ label, hint, options = [], placeholder = 'Pilih...', className, ...props }) {
  return (
    <Field label={label} hint={hint} className={className}>
      <select className={inputCls} {...props}>
        <option value="">{placeholder}</option>
        {options.map((o) =>
          typeof o === 'object'
            ? <option key={o.value} value={o.value}>{o.label}</option>
            : <option key={o} value={o}>{o}</option>,
        )}
      </select>
    </Field>
  )
}

export function Textarea({ label, className, ...props }) {
  return (
    <Field label={label} className={className}>
      <textarea className={inputCls} rows={2} {...props} />
    </Field>
  )
}

export function Checkbox({ label, ...props }) {
  return (
    <label className="inline-flex items-center gap-2 font-normal">
      <input type="checkbox" className="h-4 w-4 rounded border-slate-300 accent-teal-700" {...props} />
      {label}
    </label>
  )
}

export function Alert({ type = 'error', children }) {
  if (!children) return null
  const cls = {
    error: 'border-rose-200 bg-rose-50 text-rose-700',
    success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    warning: 'border-amber-200 bg-amber-50 text-amber-800',
    info: 'border-sky-200 bg-sky-50 text-sky-800',
  }[type]
  return <div className={`mb-4 rounded-lg border px-4 py-3 text-sm ${cls}`}>{children}</div>
}

const badgeCls = {
  tersedia: 'bg-emerald-100 text-emerald-700',
  perbaikan: 'bg-amber-100 text-amber-700',
  terjual: 'bg-slate-200 text-slate-600',
  dipesan: 'bg-sky-100 text-sky-700',
  dikembalikan: 'bg-slate-100 text-slate-500',
  aktif: 'bg-sky-100 text-sky-700',
  dipakai: 'bg-emerald-100 text-emerald-700',
  hangus: 'bg-rose-100 text-rose-700',
  dititipkan: 'bg-violet-100 text-violet-700',
  titipan: 'bg-violet-100 text-violet-700',
  lunas: 'bg-emerald-100 text-emerald-700',
  menunggu: 'bg-amber-100 text-amber-800',
  cair: 'bg-emerald-100 text-emerald-700',
  kredit: 'bg-sky-100 text-sky-700',
  tunai: 'bg-slate-100 text-slate-600',
  'Layak dibeli': 'bg-emerald-100 text-emerald-700',
  'Sesuai pasar': 'bg-emerald-100 text-emerald-700',
  'Margin tipis': 'bg-amber-100 text-amber-800',
  'Di bawah pasar': 'bg-sky-100 text-sky-700',
  'Di atas pasar': 'bg-amber-100 text-amber-800',
  'Tidak disarankan': 'bg-rose-100 text-rose-700',
  'Berisiko rugi': 'bg-rose-100 text-rose-700',
}

export function Badge({ children }) {
  return (
    <span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ${badgeCls[children] || 'bg-slate-100 text-slate-600'}`}>
      {children}
    </span>
  )
}

export function Table({ columns, rows: rawRows, empty = 'Belum ada data.', footer }) {
  const rows = Array.isArray(rawRows) ? rawRows : []
  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
            {columns.map((c) => (
              <th key={c.key} className={`px-3 py-2 font-medium ${c.right ? 'text-right' : ''}`}>{c.label}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {(!rows || rows.length === 0) && (
            <tr><td colSpan={columns.length} className="px-3 py-6 text-center text-slate-400">{empty}</td></tr>
          )}
          {rows?.map((r, i) => (
            <tr key={r.id ?? i} className="border-b border-slate-100 hover:bg-slate-50">
              {columns.map((c) => (
                <td key={c.key} className={`px-3 py-2 ${c.right ? 'text-right tabular-nums' : ''}`}>
                  {c.render ? c.render(r) : r[c.key]}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
        {footer}
      </table>
    </div>
  )
}

export function Loading({ text = 'Memuat data...' }) {
  return <p className="py-6 text-center text-sm text-slate-400">{text}</p>
}

export function Tabs({ tabs, value, onChange }) {
  return (
    <div className="no-print mb-5 flex flex-wrap gap-1 rounded-xl bg-slate-200/70 p-1">
      {tabs.map((t) => (
        <button
          key={t.value}
          onClick={() => onChange(t.value)}
          className={`rounded-lg px-4 py-2 text-sm font-medium ${value === t.value ? 'bg-white text-teal-800 shadow-sm' : 'text-slate-600 hover:text-slate-800'}`}
        >
          {t.label}
        </button>
      ))}
    </div>
  )
}

export function Pagination({ meta, onPage }) {
  if (!meta || meta.last_page <= 1) return null
  return (
    <div className="mt-4 flex items-center justify-between text-sm text-slate-500">
      <span>Halaman {meta.current_page} dari {meta.last_page} ({meta.total} data)</span>
      <div className="flex gap-2">
        <Button variant="secondary" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>Sebelumnya</Button>
        <Button variant="secondary" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)}>Berikutnya</Button>
      </div>
    </div>
  )
}

export function StatCard({ label, value, note, tone = 'teal' }) {
  const tones = { teal: 'text-teal-700', sky: 'text-sky-700', amber: 'text-amber-700', emerald: 'text-emerald-700', violet: 'text-violet-700', rose: 'text-rose-700' }
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <p className="text-sm text-slate-500">{label}</p>
      <p className={`mt-2 text-2xl font-semibold tabular-nums ${tones[tone]}`}>{value}</p>
      {note && <p className="mt-1 text-xs text-slate-400">{note}</p>}
    </div>
  )
}

/** Hilangkan pesan sukses setelah beberapa detik. */
export function useFlash(ms = 4000) {
  const [msg, setMsg] = useState('')
  useEffect(() => {
    if (!msg) return
    const t = setTimeout(() => setMsg(''), ms)
    return () => clearTimeout(t)
  }, [msg, ms])
  return [msg, setMsg]
}

/** Pilihan sumber dana biaya: kas atau stok perlengkapan. */
export const sumberOptions = [
  { value: 'kas', label: 'Kas (bayar tunai)' },
  { value: 'perlengkapan', label: 'Stok perlengkapan' },
]
