import { useCallback, useEffect, useState } from 'react'
import { api } from './api'
import NewOrder from './NewOrder'

const STATUS_LABEL = {
  open: ['待放行', 'badge open'],
  released: ['已放行', 'badge pass'],
  released_manual: ['人工放行', 'badge manual'],
}

export default function OrderList({ onOpen }) {
  const [orders, setOrders] = useState([])
  const [creating, setCreating] = useState(false)
  const [error, setError] = useState('')

  const load = useCallback(() => {
    api('/orders')
      .then((page) => setOrders(page.data))
      .catch((e) => setError(e.message))
  }, [])

  useEffect(load, [load])

  return (
    <div>
      <div className="row spread">
        <h2>出场车辆</h2>
        <button className="primary" onClick={() => setCreating(true)}>
          + 新建出场单
        </button>
      </div>

      {creating && (
        <NewOrder
          onCreated={(order) => {
            setCreating(false)
            onOpen(order.id)
          }}
          onCancel={() => setCreating(false)}
        />
      )}

      {error && <p className="error">{error}</p>}

      <table>
        <thead>
          <tr>
            <th>单号</th>
            <th>车牌</th>
            <th>登记方式</th>
            <th>检查轮次</th>
            <th>最新结论</th>
            <th>状态</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {orders.map((o) => {
            const [label, cls] = STATUS_LABEL[o.status] ?? [o.status, 'badge']
            return (
              <tr key={o.id}>
                <td>#{o.id}</td>
                <td className="plate">{o.plate_number}</td>
                <td>{o.plate_source === 'manual' ? '人工登记' : '识别'}</td>
                <td>{o.rounds_count}</td>
                <td>
                  {o.latest_round ? (
                    <span className={`badge ${o.latest_round.result}`}>
                      {o.latest_round.result === 'pass' ? '合格' : '不合格'}
                    </span>
                  ) : (
                    <span className="muted">未检查</span>
                  )}
                </td>
                <td>
                  <span className={cls}>{label}</span>
                </td>
                <td>
                  <button className="ghost" onClick={() => onOpen(o.id)}>
                    详情
                  </button>
                </td>
              </tr>
            )
          })}
          {orders.length === 0 && (
            <tr>
              <td colSpan={7} className="muted">
                暂无出场单
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  )
}
