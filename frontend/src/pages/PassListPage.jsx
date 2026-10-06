import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client.js'

const STATUS = {
  pending: { text: '待处理（检查/复洗中）', cls: 'st-pending' },
  passed: { text: '最后一轮合格 · 待放行', cls: 'st-passed' },
  released: { text: '已放行', cls: 'st-released' },
}

function NewPassForm({ onCreated }) {
  const [open, setOpen] = useState(false)
  const [file, setFile] = useState(null)
  const [simulateFail, setSimulateFail] = useState(false)
  const [manualPlate, setManualPlate] = useState('')
  const [driver, setDriver] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const fileRef = useRef(null)

  async function submit(e) {
    e.preventDefault()
    setError(''); setBusy(true)
    try {
      const fd = new FormData()
      if (file) fd.append('lpr_image', file)
      fd.append('simulate_failure', simulateFail ? '1' : '0')
      if (simulateFail && manualPlate.trim()) fd.append('manual_plate', manualPlate.trim())
      if (driver.trim()) fd.append('driver_name', driver.trim())
      const res = await api.post('/api/gate-passes', fd)
      setOpen(false); setFile(null); setSimulateFail(false); setManualPlate(''); setDriver('')
      if (fileRef.current) fileRef.current.value = ''
      onCreated(res.data.id)
    } catch (err) {
      setError(err.message)
    } finally {
      setBusy(false)
    }
  }

  if (!open) return <button className="btn btn-primary" onClick={() => setOpen(true)}>+ 登记车辆出场</button>

  return (
    <form className="card newpass" onSubmit={submit}>
      <h3>登记新的出场单</h3>
      <label>车牌识别相机抓拍图（本地模拟识别；留空则模拟空拍）
        <input ref={fileRef} type="file" accept="image/*"
          onChange={e => setFile(e.target.files?.[0] || null)} />
      </label>
      <label className="check">
        <input type="checkbox" checked={simulateFail} onChange={e => setSimulateFail(e.target.checked)} />
        模拟本次车牌识别失败（可人工登记，原识别结果会保留）
      </label>
      {simulateFail && (
        <label>人工登记车牌（识别失败时填写，可稍后补录）
          <input value={manualPlate} onChange={e => setManualPlate(e.target.value)}
            placeholder="如：京A12345" maxLength={20} />
        </label>
      )}
      <label>司机/备注（可选）
        <input value={driver} onChange={e => setDriver(e.target.value)} maxLength={50} />
      </label>
      {error && <div className="alert alert-error">{error}</div>}
      <div className="row">
        <button className="btn btn-primary" disabled={busy}>{busy ? '识别中…' : '创建出场单'}</button>
        <button type="button" className="btn btn-ghost" onClick={() => setOpen(false)}>取消</button>
      </div>
    </form>
  )
}

export default function PassListPage() {
  const [items, setItems] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [filter, setFilter] = useState('')
  const [tick, setTick] = useState(0)

  useEffect(() => {
    setLoading(true)
    api.get(`/api/gate-passes${filter ? `?status=${filter}` : ''}`)
      .then(d => setItems(d.data || []))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false))
  }, [filter, tick])

  return (
    <div className="page-grid">
      <div className="left-col">
        <NewPassForm onCreated={(id) => { setTick(t => t + 1); location.hash = ''; window.location.assign(`/passes/${id}`) }} />
      </div>
      <div className="right-col">
        <div className="list-head">
          <h2>出场单</h2>
          <select value={filter} onChange={e => setFilter(e.target.value)}>
            <option value="">全部状态</option>
            <option value="pending">检查/复洗中</option>
            <option value="passed">待放行</option>
            <option value="released">已放行</option>
          </select>
        </div>
        {loading && <div className="muted">加载中…</div>}
        {error && <div className="alert alert-error">{error}</div>}
        {!loading && items.length === 0 && <div className="muted">暂无出场单</div>}
        <div className="pass-list">
          {items.map(p => {
            const st = STATUS[p.status] || { text: p.status, cls: '' }
            const latest = p.latest_round
            return (
              <Link key={p.id} to={`/passes/${p.id}`} className="card pass-card">
                <div className="pass-top">
                  <span className="pass-id">#{String(p.id).padStart(4, '0')}</span>
                  <span className={`status-tag ${st.cls}`}>{st.text}</span>
                </div>
                <div className="pass-plate">
                  {p.effective_plate
                    ? <span className="plate">{p.effective_plate}</span>
                    : <span className="plate plate-unknown">车牌待登记</span>}
                  {p.recognition.status === 'failed' && (
                    <span className="lpr-failed" title="车牌识别失败，原始识别结果已保留">识别失败{p.manual_plate_number ? '·已人工登记' : ''}</span>
                  )}
                </div>
                <div className="pass-meta muted small">
                  检查 {p.inspections_count ?? 0} 轮
                  {latest ? (
                    <span> · 末次第 {latest.round} 轮：
                      <b className={latest.result === 'qualified' ? 'ok-text' : 'bad-text'}>
                        {latest.result === 'qualified' ? '合格' : '不合格'}
                      </b>
                    </span>
                  ) : null}
                </div>
              </Link>
            )
          })}
        </div>
      </div>
    </div>
  )
}
