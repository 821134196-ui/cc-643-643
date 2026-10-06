import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../auth.jsx'

const DEMO = [
  { email: 'clerk@site.local', label: '普通检查人员', desc: '李录入 · 只能记录检查，不能人工放行' },
  { email: 'supervisor@site.local', label: '值班员', desc: '王值班 · 可确认人工放行（须填原因）' },
]

export default function LoginPage() {
  const { login, user } = useAuth()
  const nav = useNavigate()
  const [email, setEmail] = useState('clerk@site.local')
  const [password, setPassword] = useState('password')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  if (user) { nav('/', { replace: true }) }

  async function submit(e) {
    e.preventDefault()
    setError(''); setLoading(true)
    try {
      await login(email.trim(), password)
      nav('/', { replace: true })
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="login-wrap">
      <form className="card login-card" onSubmit={submit}>
        <h1>门岗终端登录</h1>
        <p className="muted">出场车辆洗轮复查系统（本地演示）</p>
        <label>账号
          <input type="email" value={email} onChange={e => setEmail(e.target.value)} required />
        </label>
        <label>密码
          <input type="password" value={password} onChange={e => setPassword(e.target.value)} required />
        </label>
        {error && <div className="alert alert-error">{error}</div>}
        <button className="btn btn-primary btn-block" disabled={loading}>
          {loading ? '登录中…' : '登 录'}
        </button>
        <div className="demo-accounts">
          <div className="muted small">演示账号（密码均为 password，点击填充）：</div>
          {DEMO.map(a => (
            <button type="button" key={a.email} className="demo-btn"
              onClick={() => { setEmail(a.email); setPassword('password') }}>
              <b>{a.label}</b><span className="muted">{a.desc}</span>
            </button>
          ))}
        </div>
      </form>
    </div>
  )
}
