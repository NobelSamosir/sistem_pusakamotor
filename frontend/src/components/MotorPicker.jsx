import useFetch from '../lib/useFetch'
import { Select } from './ui'

/**
 * Pilih unit yang masih ada di showroom (tersedia, perbaikan, dipesan), milik sendiri maupun titipan.
 * exclude: daftar status yang disembunyikan, misalnya ['dipesan'] untuk form uang muka.
 */
export default function MotorPicker({ value, onChange, label = 'Unit motor', exclude = [], ownership }) {
  const { data } = useFetch('/motorcycles', { in_showroom: 1, per_page: 200, ...(ownership ? { ownership_type: ownership } : {}) })
  const rows = (data?.data || []).filter((m) => !exclude.includes(m.status))
  const tag = (m) => [m.ownership_type === 'titipan' && 'titipan', m.status !== 'tersedia' && m.status].filter(Boolean).join(', ')
  return (
    <Select
      label={label}
      options={rows.map((m) => ({
        value: String(m.id),
        label: `${m.vehicle_code} · ${m.brand} ${m.model} ${m.year} · ${m.frame_number}${tag(m) ? ` (${tag(m)})` : ''}`,
      }))}
      value={value}
      onChange={(e) => onChange(e.target.value)}
      required
    />
  )
}
