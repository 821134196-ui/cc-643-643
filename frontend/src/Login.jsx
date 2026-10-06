import { useEffect, useState } from 'react'
import { api, saveSession } from './api'

export default function Login({ onLogin }) {
  const [users, setUsers] = useState([])
  const [error, setError] = useState('')

  useEffect(() => {
    fetch('/api/users')
      .then((r) => r.json())
      .then(setUsers)
      .catch(() => setError('无法连接后端,请确认 php artisan serve 已启动'))
  }, [])

  async function login(email) {
    setError('')
    try {
      const data = await api('/login', { method: 'POST', body: { email } })
      saveSession(data.token, data.user)
      onLogin(data.user)
    } catch (e) {
      setError(e.message)
    }
  }

  return (
    <div className="card narrow">
      <h2>门岗登录</h2>
      <p className="muted">本地演示账号(点击即可登录):</p>
      {users.map((u) => (
        <button key={u.id} className="user-btn" onClick={() => login(u.email)}>
          <strong>{u.name}</strong>
          <span className={`badge ${u.role}`}>
            {u.role === 'supervisor' ? '值班员(可人工放行)' : '普通录入'}
          </span>
        </button>
      ))}
      {error && <p className="error">{error}</p>}
    </div>
  )
}
