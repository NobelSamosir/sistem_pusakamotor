import { useEffect, useState } from 'react'
import api from './api'

let cache = null

/** Kategori fitur dari data latih (isi dropdown), konteks showroom, PL default. */
export default function useOptions() {
  const [opts, setOpts] = useState(cache)
  const [error, setError] = useState('')

  useEffect(() => {
    if (cache) return
    api.get('/ml/options')
      .then((r) => { cache = r.data; setOpts(r.data) })
      .catch(() => setError('Daftar pilihan belum bisa dimuat. Pastikan layanan model (uvicorn) menyala.'))
  }, [])

  return { categories: opts?.metadata?.categories || {}, showroom: opts?.showroom, defaultMarkup: opts?.default_markup ?? 0.15, error }
}
