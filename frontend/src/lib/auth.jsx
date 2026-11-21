import { createContext, useContext, useState } from 'react'
import api from './api'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    try { return JSON.parse(localStorage.getItem('pm_user')) } catch { return null }
  })

  const login = async (email, password) => {
    const { data } = await api.post('/login', { email, password })
    localStorage.setItem('pm_token', data.token)
    localStorage.setItem('pm_user', JSON.stringify(data.user))
    setUser(data.user)
  }

  const logout = async () => {
    try { await api.post('/logout') } catch { /* token mungkin sudah kedaluwarsa */ }
    localStorage.removeItem('pm_token')
    localStorage.removeItem('pm_user')
    setUser(null)
  }

  return <AuthContext.Provider value={{ user, login, logout }}>{children}</AuthContext.Provider>
}

export const useAuth = () => useContext(AuthContext)
