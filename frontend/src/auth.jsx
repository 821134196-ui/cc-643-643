import { createContext, useContext, useMemo, useState, useCallback } from 'react'
import { api } from './api/client.js'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    const raw = localStorage.getItem('gate_user')
    return raw ? JSON.parse(raw) : null
  })

  const login = useCallback(async (email, password) => {
    const data = await api.post('/api/auth/login', { email, password })
    localStorage.setItem('gate_token', data.token)
    localStorage.setItem('gate_user', JSON.stringify(data.user))
    setUser(data.user)
  }, [])

  const logout = useCallback(() => {
    localStorage.removeItem('gate_token')
    localStorage.removeItem('gate_user')
    setUser(null)
  }, [])

  const value = useMemo(() => ({
    user,
    isSupervisor: !!user?.is_supervisor,
    login,
    logout,
  }), [user, login, logout])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  return useContext(AuthContext)
}
