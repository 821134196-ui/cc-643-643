import { Routes, Route, Navigate, Link, useLocation } from 'react-router-dom'
import { useAuth } from './auth.jsx'
import LoginPage from './pages/LoginPage.jsx'
import PassListPage from './pages/PassListPage.jsx'
import PassDetailPage from './pages/PassDetailPage.jsx'

function Header() {
  const { user, isSupervisor, logout } = useAuth()
  const location = useLocation()
  if (!user) return null
  return (
    <header className="topbar">
      <div className="topbar-inner">
        <Link to="/" className="brand">🚧 出场车辆洗轮与泥污复查 · 门岗终端</Link>
        <div className="topbar-user">
          <span className="user-name">{user.name}</span>
          <span className={`role-badge ${isSupervisor ? 'role-super' : 'role-clerk'}`}>
            {isSupervisor ? '值班员（可人工放行）' : '普通检查人员'}
          </span>
          <button className="btn btn-ghost" onClick={logout}>退出</button>
        </div>
      </div>
      {location.pathname !== '/' && (
        <div className="topbar-inner sub">
          <Link to="/" className="back-link">← 返回出场单列表</Link>
        </div>
      )}
    </header>
  )
}

function RequireAuth({ children }) {
  const { user } = useAuth()
  if (!user) return <Navigate to="/login" replace />
  return children
}

export default function App() {
  return (
    <>
      <Header />
      <main className="container">
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/" element={<RequireAuth><PassListPage /></RequireAuth>} />
          <Route path="/passes/:id" element={<RequireAuth><PassDetailPage /></RequireAuth>} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </main>
    </>
  )
}
