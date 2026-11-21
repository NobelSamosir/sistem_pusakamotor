import axios from 'axios'

// Semua permintaan ke Laravel lewat /api (diteruskan oleh proxy Vite)
const api = axios.create({ baseURL: '/api', headers: { Accept: 'application/json' } })

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('pm_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (res) => res,
  (err) => {
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
  const d = err?.response?.data
  if (d?.errors) return Object.values(d.errors).flat().join(' ')
  if (d?.message) return d.message
  if (err?.code === 'ERR_NETWORK') return 'Tidak bisa terhubung ke server. Pastikan php artisan serve sedang berjalan.'
  return 'Terjadi kesalahan. Coba lagi.'
}

export default api
