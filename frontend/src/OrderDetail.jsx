import { useCallback, useEffect, useState } from 'react'
import { api, getUser } from './api'

const TYPE_LABEL = { wheel: '车轮', body: '车身' }

export default function OrderDetail({ orderId, onBack }) {
  const [order, setOrder] = useState(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  // 新增检查轮次表单
  const [wheelFile, setWheelFile] = useState(null)
  const [bodyFile, setBodyFile] = useState(null)
  const [result, setResult] = useState('pass')
  const [note, setNote] = useState('')
  const [busy, setBusy] = useState(false)

  // 人工放行
  const [reason, setReason] = useState('')
  const [showManual, setShowManual] = useState(false)

  const user = getUser()
  const isSupervisor = user?.role === 'supervisor'

  const load = useCallback(() => {
    api(`/orders/${orderId}`)
      .then(setOrder)
      .catch((e) => setError(e.message))
  }, [orderId])

  useEffect(load, [load])

  async function uploadPhoto(type, file) {
    const fd = new FormData()
    fd.append('type', type)
    fd.append('photo', file)
    return api(`/orders/${orderId}/photos`, { method: 'POST', formData: fd })
  }

  async function submitRound(e) {
    e.preventDefault()
    setError('')
    setNotice('')
    if (!wheelFile || !bodyFile) {
      setError('请同时上传车轮照片和车身照片')
      return
    }
    setBusy(true)
    try {
      // 每轮检查都现场重新上传照片,后端会拒绝引用其他单或已用过的照片
      const wheel = await uploadPhoto('wheel', wheelFile)
      const body = await uploadPhoto('body', bodyFile)
      await api(`/orders/${orderId}/rounds`, {
        method: 'POST',
        body: { result, note: note || null, photo_ids: [wheel.id, body.id] },
      })
      setWheelFile(null)
      setBodyFile(null)
      setNote('')
      setNotice(`第 ${order.rounds.length + 1} 轮检查已记录`)
      load()
    } catch (err) {
      setError(err.message)
    } finally {
      setBusy(false)
    }
  }

  async function release() {
    setError('')
    setNotice('')
    try {
      const data = await api(`/orders/${orderId}/release`, { method: 'POST' })
      setNotice(`闸机指令已发送:${data.command.gate_response}`)
      load()
    } catch (err) {
      setError(err.message)
    }
  }

  async function manualRelease() {
    setError('')
    setNotice('')
    try {
      const data = await api(`/orders/${orderId}/manual-release`, {
        method: 'POST',
        body: { reason },
      })
      setNotice(`人工放行完成:${data.command.gate_response}`)
      setShowManual(false)
      load()
    } catch (err) {
      setError(err.message)
    }
  }

  if (!order) return <p className="muted">加载中… {error}</p>

  const open = order.status === 'open'
  const latest = order.rounds[order.rounds.length - 1]

  return (
    <div>
      <button className="ghost" onClick={onBack}>
        ← 返回列表
      </button>

      <div className="card">
        <h2>
          出场单 #{order.id} <span className="plate">{order.plate_number}</span>
        </h2>
        <p className="muted">
          登记方式:{order.plate_source === 'manual' ? '人工登记' : '车牌识别'}
          {order.plate_source === 'manual' && ` (原识别结果:${order.plate_recognized ?? '无'})`}
          {' '}· 开单人:{order.creator?.name} · {order.created_at}
        </p>
        <p>
          状态:
          {order.status === 'open' && <span className="badge open">待放行</span>}
          {order.status === 'released' && <span className="badge pass">已放行</span>}
          {order.status === 'released_manual' && <span className="badge manual">人工放行</span>}
        </p>
      </div>

      <h3>检查记录(按检查顺序)</h3>
      {order.rounds.length === 0 && <p className="muted">尚无检查记录</p>}
      {order.rounds.map((r) => (
        <div className="card round" key={r.id}>
          <div className="row spread">
            <strong>第 {r.round_no} 轮检查</strong>
            <span className={`badge ${r.result}`}>{r.result === 'pass' ? '合格' : '不合格'}</span>
          </div>
          <div className="photos">
            {r.photos.map((p) => (
              <figure key={p.id}>
                <img src={p.url} alt={TYPE_LABEL[p.type]} />
                <figcaption>{TYPE_LABEL[p.type]}</figcaption>
              </figure>
            ))}
          </div>
          <p className="muted">
            检查人:{r.inspector?.name} · {r.created_at}
            {r.note ? ` · 备注:${r.note}` : ''}
          </p>
        </div>
      ))}

      {order.gate_commands.length > 0 && (
        <div className="card">
          <h3>闸机指令记录</h3>
          {order.gate_commands.map((c) => (
            <p key={c.id}>
              <span className={`badge ${c.type === 'manual' ? 'manual' : 'pass'}`}>
                {c.type === 'manual' ? '人工放行' : '自动放行'}
              </span>{' '}
              {c.gate_response} · 操作人:{c.issuer?.name}
              {c.reason ? ` · 原因:${c.reason}` : ''}
            </p>
          ))}
        </div>
      )}

      {open && (
        <div className="card">
          <h3>新增一轮检查{order.rounds.length > 0 ? '(复洗后复检)' : '(初检)'}</h3>
          <form onSubmit={submitRound}>
            <div className="row">
              <label>
                车轮照片
                <input type="file" accept="image/*" onChange={(e) => setWheelFile(e.target.files[0])} />
              </label>
              <label>
                车身照片
                <input type="file" accept="image/*" onChange={(e) => setBodyFile(e.target.files[0])} />
              </label>
            </div>
            <div className="row">
              <label>
                检查结论
                <select value={result} onChange={(e) => setResult(e.target.value)}>
                  <option value="pass">合格</option>
                  <option value="fail">不合格(返回洗轮区)</option>
                </select>
              </label>
              <label className="grow">
                备注
                <input value={note} onChange={(e) => setNote(e.target.value)} placeholder="选填" />
              </label>
            </div>
            <button className="primary" disabled={busy}>
              {busy ? '提交中…' : '提交本轮检查'}
            </button>
          </form>
        </div>
      )}

      {open && (
        <div className="card">
          <h3>放行控制</h3>
          <p className="muted">
            {order.can_auto_release
              ? `第 ${latest.round_no} 轮检查合格,可发送闸机放行指令。`
              : '只有本次出场最后一轮检查合格,才能发送闸机放行指令。'}
          </p>
          <div className="row">
            <button className="primary" disabled={!order.can_auto_release} onClick={release}>
              发送闸机放行指令
            </button>
            {isSupervisor && (
              <button className="warn" onClick={() => setShowManual((v) => !v)}>
                人工放行(值班员)
              </button>
            )}
          </div>
          {showManual && isSupervisor && (
            <div className="warn-box">
              <p>人工放行将绕过检查结果,必须填写原因并留痕。</p>
              <input
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="放行原因(必填)"
              />
              <button className="warn" disabled={reason.trim().length < 2} onClick={manualRelease}>
                确认人工放行
              </button>
            </div>
          )}
        </div>
      )}

      {error && <p className="error">{error}</p>}
      {notice && <p className="ok">{notice}</p>}
    </div>
  )
}
