import useFetch from '../lib/useFetch'
import { Input, Select } from './ui'

export const emptyIdentity = { id: null, name: '', phone: '', id_number: '', address: '', type: 'perorangan' }

/**
 * Identitas penjual (kind="supplier") atau pembeli (kind="customer"), diisi langsung di form transaksi.
 * Mengetik nama yang pernah tercatat akan mengisi data lainnya otomatis.
 */
export default function IdentityFields({ kind, title, value, onChange, required = true, compact = false }) {
  const isSup = kind === 'supplier'
  const { data } = useFetch(isSup ? '/suppliers' : '/customers')
  const list = Array.isArray(data) ? data : []
  const nameKey = isSup ? 'supplier_name' : 'customer_name'
  const typeKey = isSup ? 'supplier_type' : 'customer_type'
  const listId = `daftar-${kind}`
  const set = (k) => (e) => onChange({ ...value, [k]: e.target.value })

  const onName = (e) => {
    const name = e.target.value
    const found = list.filter((p) => p[nameKey].toLowerCase() === name.trim().toLowerCase())
    if (found.length === 1) {
      const p = found[0]
      onChange({ id: p.id, name: p[nameKey], phone: p.phone || '', id_number: p.id_number || '', address: p.address || '', type: p[typeKey] || 'perorangan' })
    } else {
      onChange({ ...value, id: null, name })
    }
  }

  return (
    <fieldset className="rounded-lg border border-slate-200 p-4">
      <legend className="px-1 text-sm font-semibold text-slate-700">{title}</legend>
      <div className={`grid gap-4 ${compact ? 'md:grid-cols-2' : 'md:grid-cols-2 xl:grid-cols-4'}`}>
        <Input
          label={required ? 'Nama' : 'Nama (opsional)'}
          list={listId}
          value={value.name}
          onChange={onName}
          autoComplete="off"
          required={required}
          hint={value.id ? 'Sudah pernah tercatat, data terisi otomatis' : undefined}
        />
        <Input label="No. HP" inputMode="tel" value={value.phone} onChange={set('phone')} />
        {!compact && (
          <>
            <Input
              label="NIK (nomor KTP)"
              inputMode="numeric"
              maxLength={16}
              pattern="\d{16}"
              title="NIK terdiri dari 16 angka"
              value={value.id_number}
              onChange={(e) => onChange({ ...value, id_number: e.target.value.replace(/\D/g, '') })}
              hint="16 angka, boleh dikosongkan"
            />
            {isSup ? (
              <Select
                label="Jenis penjual"
                options={[{ value: 'perorangan', label: 'Perorangan' }, { value: 'dealer', label: 'Showroom / dealer lain' }]}
                value={value.type}
                onChange={set('type')}
              />
            ) : <div className="hidden xl:block" />}
            <Input label="Alamat" className="md:col-span-2 xl:col-span-4" value={value.address} onChange={set('address')} />
          </>
        )}
      </div>
      <datalist id={listId}>
        {list.map((p) => <option key={p.id} value={p[nameKey]} label={[p.phone, p.address].filter(Boolean).join(' · ')} />)}
      </datalist>
    </fieldset>
  )
}

/** Bentuk yang dikirim ke API. */
export const identityPayload = (v) => ({
  id: v.id || null,
  name: v.name.trim(),
  phone: v.phone || null,
  id_number: v.id_number || null,
  address: v.address || null,
  type: v.type || 'perorangan',
})
