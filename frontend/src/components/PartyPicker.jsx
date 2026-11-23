import { useState } from 'react'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { Button, Select } from './ui'

/** Pilih pemasok/pelanggan, dengan tombol tambah cepat tanpa pindah halaman. */
export default function PartyPicker({ type, value, onChange, label, required = true }) {
  const isSupplier = type === 'supplier'
  const url = isSupplier ? '/suppliers' : '/customers'
  const nameKey = isSupplier ? 'supplier_name' : 'customer_name'
  const { data, reload } = useFetch(url)
  const [adding, setAdding] = useState(false)
  const [name, setName] = useState('')
  const [phone, setPhone] = useState('')
  const [err, setErr] = useState('')

  const save = async () => {
    setErr('')
    try {
      const { data: created } = await api.post(url, { [nameKey]: name, phone: phone || null })
      await reload()
      onChange(String(created.id))
      setAdding(false); setName(''); setPhone('')
    } catch (e) { setErr(errorMessage(e)) }
  }

  return (
    <div>
      <div className="flex items-end gap-2">
        <Select
          className="flex-1"
          label={label || (isSupplier ? 'Pemasok (penjual motor)' : 'Pelanggan (pembeli)')}
          options={(data || []).map((p) => ({ value: String(p.id), label: p[nameKey] + (p.phone ? ` · ${p.phone}` : '') }))}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          required={required}
        />
        <Button type="button" variant="secondary" onClick={() => setAdding(!adding)}>{adding ? 'Batal' : '+ Baru'}</Button>
      </div>
      {adding && (
        <div className="mt-2 flex flex-wrap items-end gap-2 rounded-lg bg-slate-50 p-3">
          <input className="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nama" value={name} onChange={(e) => setName(e.target.value)} />
          <input className="w-40 rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="No. HP (opsional)" value={phone} onChange={(e) => setPhone(e.target.value)} />
          <Button type="button" onClick={save} disabled={!name}>Simpan</Button>
          {err && <p className="w-full text-sm text-rose-600">{err}</p>}
        </div>
      )}
    </div>
  )
}
