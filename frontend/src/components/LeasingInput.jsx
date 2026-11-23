import useFetch from '../lib/useFetch'
import { Input } from './ui'

// Saran awal; nama apa pun yang diketik akan ikut muncul di daftar setelah dipakai.
const SARAN = ['Adira Finance', 'FIF Group', 'OTO', 'BAF', 'WOM Finance', 'Mandiri Utama Finance', 'MPM Finance', 'Mega Finance']

/** Nama perusahaan pembiayaan: pilih dari daftar atau ketik nama baru. */
export default function LeasingInput({ value, onChange, label = 'Perusahaan leasing', required = true }) {
  const { data } = useFetch('/leasing/companies')
  const pilihan = [...new Set([...(Array.isArray(data) ? data : []), ...SARAN])]
  return (
    <>
      <Input
        label={label}
        list="daftar-leasing"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder="Pilih atau ketik nama leasing"
        hint="Belum ada di daftar? Ketik saja nama leasing barunya."
        autoComplete="off"
        required={required}
      />
      <datalist id="daftar-leasing">
        {pilihan.map((n) => <option key={n} value={n} />)}
      </datalist>
    </>
  )
}
