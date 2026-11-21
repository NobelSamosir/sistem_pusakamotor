import useFetch from '../lib/useFetch'
import { Select } from './ui'

/** Pilih unit yang belum terjual, ditampilkan dengan nomor rangka untuk dicocokkan ke fisik motor. */
export default function MotorPicker({ value, onChange, label = 'Unit motor' }) {
  const a = useFetch('/motorcycles', { status: 'tersedia', per_page: 200 })
  const b = useFetch('/motorcycles', { status: 'perbaikan', per_page: 200 })
  const rows = [...(a.data?.data || []), ...(b.data?.data || [])]
  return (
    <Select
      label={label}
      options={rows.map((m) => ({ value: String(m.id), label: `${m.vehicle_code} · ${m.brand} ${m.model} ${m.year} · ${m.frame_number}` }))}
      value={value}
      onChange={(e) => onChange(e.target.value)}
      required
    />
  )
}
