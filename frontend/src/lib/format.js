export const rupiah = (v) =>
  v === null || v === undefined || v === '' ? '-' : 'Rp' + Math.round(Number(v)).toLocaleString('id-ID')

export const persen = (v, digits = 2) =>
  v === null || v === undefined ? '-' : (Number(v) * 100).toLocaleString('id-ID', { maximumFractionDigits: digits }) + '%'

export const tanggal = (v) => {
  if (!v) return '-'
  const d = new Date(String(v).slice(0, 10) + 'T00:00:00')
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

export const today = () => new Date().toLocaleDateString('en-CA')
export const monthStart = () => today().slice(0, 8) + '01'
export const monthEnd = () => {
  const d = new Date()
  return new Date(d.getFullYear(), d.getMonth() + 1, 0).toLocaleDateString('en-CA')
}

export const unitLabel = (m) => (m ? `${m.brand} ${m.model} ${m.year}` : '-')
