import axios from 'axios'

// Semua permintaan ke Laravel lewat /api (diteruskan oleh proxy Vite)
const api = axios.create({ baseURL: '/api', headers: { Accept: 'application/json' } })

// Pengaman simpan ganda: permintaan simpan/ubah/hapus yang sama persis tidak dikirim dua kali
// selama yang pertama belum selesai (misalnya tombol terklik berulang saat koneksi lambat).
const pending = new Set()
const keyOf = (c) => `${c.method}:${c.url}:${typeof c.data === 'string' ? c.data : JSON.stringify(c.data ?? '')}`

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('pm_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  if (['post', 'put', 'delete'].includes(config.method) && !config.url.endsWith('/login')) {
    const key = keyOf(config)
    if (pending.has(key)) {
      const e = new Error('duplikat'); e.duplicate = true
      return Promise.reject(e)
    }
    pending.add(key); config.pendingKey = key
  }
  return config
})

api.interceptors.response.use(
  (res) => { pending.delete(res.config.pendingKey); return res },
  (err) => {
    if (err.config?.pendingKey) pending.delete(err.config.pendingKey)
    if (err.response?.status === 401 && !err.config.url.endsWith('/login')) {
      localStorage.removeItem('pm_token')
      localStorage.removeItem('pm_user')
      window.location.href = '/login'
    }
    return Promise.reject(err)
  },
)

/** Ambil pesan error yang ramah dari respons Laravel (422 validasi / aturan bisnis). */
export function errorMessage(err) {
  if (err?.duplicate) return 'Data yang sama sedang disimpan, tunggu sebentar.'
  const d = err?.response?.data
  if (d?.errors) return Object.values(d.errors).flat().join(' ')
  if (d?.message) return d.message
  if (err?.code === 'ERR_NETWORK') return 'Tidak bisa terhubung ke server. Pastikan php artisan serve sedang berjalan.'
  return 'Terjadi kesalahan. Coba lagi.'
}

export default api
