import { useCallback, useEffect, useState } from 'react'
import api, { errorMessage } from './api'

/** Ambil data GET sederhana: const { data, loading, error, reload } = useFetch('/dashboard') */
export default function useFetch(url, params) {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(Boolean(url))
  const [error, setError] = useState('')
  const key = JSON.stringify(params || {})

  const reload = useCallback(async () => {
    if (!url) return
    setLoading(true)
    setError('')
    try {
      const res = await api.get(url, { params: JSON.parse(key) })
      setData(res.data)
    } catch (e) {
      setError(errorMessage(e))
    } finally {
      setLoading(false)
    }
  }, [url, key])

  useEffect(() => { reload() }, [reload])

  return { data, loading, error, reload, setData }
}
