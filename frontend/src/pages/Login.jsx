import { useState } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import { useAuth } from '../lib/auth'
import { errorMessage } from '../lib/api'
import { Alert, Button, Input } from '../components/ui'

export default function Login() {
  const { user, login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  if (user) return <Navigate to="/" replace />

  const submit = async (e) => {
    e.preventDefault()
    setBusy(true)
    setError('')
    try {
      await login(email, password)
      navigate('/')
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-900 to-teal-900 p-4">
      <form onSubmit={submit} className="w-full max-w-sm rounded-2xl bg-white p-8 shadow-xl">
        <div className="mb-6 text-center">
          <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-teal-700 font-bold text-white">PM</div>
          <h1 className="mt-4 text-xl font-semibold text-slate-800">Showroom Pusaka Motor</h1>
          <p className="text-sm text-slate-500">Sistem Informasi Akuntansi dan DSS Harga</p>
        </div>
        <Alert>{error}</Alert>
        <div className="space-y-4">
          <Input label="Email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoFocus />
          <Input label="Password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
          <Button className="w-full" disabled={busy}>{busy ? 'Memproses...' : 'Masuk'}</Button>
        </div>
      </form>
    </div>
  )
}
