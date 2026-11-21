import { Checkbox, Field, Input, Select } from './ui'
import useOptions from '../lib/useOptions'

export const emptyMotor = {
  brand: '', model: '', year: '', transmission: '', engine_capacity: '', body_type: '',
  mileage: '', ownership_status: '', has_bpkb: true, has_stnk: true, has_faktur: false, has_buku_servis: false,
}

/**
 * Karakteristik motor = fitur model (Tabel 3.3).
 * Pilihan diambil dari data latih supaya ejaannya sama persis.
 */
export default function MotorFields({ value, onChange }) {
  const { categories: c, error } = useOptions()
  const set = (k) => (e) => onChange({ ...value, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value })

  return (
    <div>
      {error && <p className="mb-3 text-sm text-amber-700">{error}</p>}
      <div className="grid gap-4 md:grid-cols-3">
        <Field label="Merek">
          <input list="dl-brand" className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={value.brand} onChange={set('brand')} placeholder="Honda" required />
          <datalist id="dl-brand">{(c.brand || []).map((b) => <option key={b} value={b} />)}</datalist>
        </Field>
        <Field label="Model" hint="Pilih dari daftar agar dikenali model">
          <input list="dl-model" className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value={value.model} onChange={set('model')} placeholder="Vario" required />
          <datalist id="dl-model">{(c.model || []).map((m) => <option key={m} value={m} />)}</datalist>
        </Field>
        <Input label="Tahun" type="number" min="1970" max={new Date().getFullYear() + 1} value={value.year} onChange={set('year')} required />
        <Select label="Transmisi" options={c.transmission} value={value.transmission} onChange={set('transmission')} required />
        <Select label="Kapasitas mesin" options={c.engine_capacity} value={value.engine_capacity} onChange={set('engine_capacity')} required />
        <Select label="Tipe bodi" options={c.body_type} value={value.body_type} onChange={set('body_type')} required />
        <Input label="Jarak tempuh (km)" type="number" min="0" value={value.mileage} onChange={set('mileage')} required />
        <Select label="Status kepemilikan" options={c.ownership_status} value={value.ownership_status} onChange={set('ownership_status')} required />
        <Field label="Dokumen">
          <div className="mt-2 grid grid-cols-2 gap-2 text-sm">
            <Checkbox label="BPKB" checked={value.has_bpkb} onChange={set('has_bpkb')} />
            <Checkbox label="STNK" checked={value.has_stnk} onChange={set('has_stnk')} />
            <Checkbox label="Faktur" checked={value.has_faktur} onChange={set('has_faktur')} />
            <Checkbox label="Buku servis" checked={value.has_buku_servis} onChange={set('has_buku_servis')} />
          </div>
        </Field>
      </div>
    </div>
  )
}

/** Ubah isian form menjadi angka/boolean yang diharapkan API. */
export function motorPayload(m) {
  return { ...m, year: Number(m.year), mileage: Number(m.mileage) }
}
