import { useState } from 'react'
import { getUser, clearSession } from './api'
import Login from './Login'
import OrderList from './OrderList'
import OrderDetail from './OrderDetail'

export default function App() {
  const [user, setUser] = useState(getUser())
  const [view, setView] = useState({ name: 'list' })

  if (!user) {
    return (
      <main>
        <h1>工地出场车辆洗轮与泥污复查系统</h1>
        <Login onLogin={setUser} />
      </main>
    )
  }

  return (
    <main>
      <header className="row spread">
        <h1>工地出场车辆洗轮与泥污复查系统</h1>
        <div>
          <span className="muted">{user.name} </span>
          <button
            className="ghost"
            onClick={() => {
              clearSession()
              setUser(null)
            }}
          >
            退出
          </button>
        </div>
      </header>

      {view.name === 'list' ? (
        <OrderList onOpen={(id) => setView({ name: 'detail', id })} />
      ) : (
        <OrderDetail orderId={view.id} onBack={() => setView({ name: 'list' })} />
      )}
    </main>
  )
}
