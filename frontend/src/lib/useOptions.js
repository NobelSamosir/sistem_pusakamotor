import { useCallback, useEffect, useState } from 'react'
import api from './api'

let cache = null

/** Kategori fitur dari data latih (isi dropdown), konteks showroom, PL default. */
export default function useOptions() {
  const [opts, setOpts] = useState(cache)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(!cache)

  const load = useCallback(() => {
    setError(''); setLoading(true)
    api.get('/ml/options')
      .then((r) => { cache = r.data; setOpts(r.data) })
      .catch((e) => {
        const status = e?.response?.status
        setError(status === 422
          ? `${e.response.data?.message || 'Layanan model tidak aktif.'} Setelah itu tekan Coba lagi.`
          : 'Daftar pilihan belum bisa dimuat karena backend atau database tidak merespons. Periksa koneksi internet dan Terminal 1 (php artisan serve), lalu tekan Coba lagi.')
      })
      .finally(() => setLoading(false))
  }, [])

  useEffect(() => { if (!cache) load() }, [load])

  return { categories: opts?.metadata?.categories || {}, showroom: opts?.showroom, defaultMarkup: opts?.default_markup ?? 0.15, error, loading, reload: load }
}
