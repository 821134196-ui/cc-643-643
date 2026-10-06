import { useState } from 'react'
import { api } from './api'

// 新建出场单:车牌识别 → 失败时人工登记(保留原识别结果)
export default function NewOrder({ onCreated, onCancel }) {
  const [recog, setRecog] = useState(null) // {status, plate}
  const [manualPlate, setManualPlate] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  async function recognize(forceFail = false) {
    setBusy(true)
    setError('')
    try {
      const result = await api(`/plate/recognize${forceFail ? '?fail=1' : ''}`, { method: 'POST' })
      setRecog(result)
      if (result.status === 'ok') setManualPlate('')
    } catch (e) {
      setError(e.message)
    } finally {
      setBusy(false)
    }
  }

  async function createOrder() {
    setBusy(true)
    setError('')
    try {
      const body =
        recog?.status === 'ok'
          ? { plate_number: recog.plate, plate_source: 'recognized' }
          : {
              plate_number: manualPlate.trim(),
              plate_source: 'manual',
              // 识别失败时,设备原始识别结果随单保存
              plate_recognized: recog?.plate ?? null,
            }
      const order = await api('/orders', { method: 'POST', body })
      onCreated(order)
    } catch (e) {
      setError(e.message)
    } finally {
      setBusy(false)
    }
  }

  const failed = recog?.status === 'failed'
  const canSubmit = !busy && (recog?.status === 'ok' || (failed && manualPlate.trim().length >= 5))

  return (
    <div className="card">
      <h3>新建出场单</h3>
      <div className="row">
        <button disabled={busy} onClick={() => recognize(false)}>
          识别车牌
        </button>
        <button disabled={busy} className="ghost" onClick={() => recognize(true)}>
          模拟识别失败
        </button>
      </div>

      {recog?.status === 'ok' && (
        <p>
          识别结果:<strong className="plate">{recog.plate}</strong>
        </p>
      )}

      {failed && (
        <div className="warn-box">
          <p>
            车牌识别失败,请人工登记车牌。
            <span className="muted">
              (原识别结果:{recog.plate ?? '无'} —— 将随单保存)
            </span>
          </p>
          <input
            value={manualPlate}
            onChange={(e) => setManualPlate(e.target.value)}
            placeholder="人工输入车牌,如 沪A12345"
          />
        </div>
      )}

      {error && <p className="error">{error}</p>}

      <div className="row">
        <button className="primary" disabled={!canSubmit} onClick={createOrder}>
          生成出场单
        </button>
        <button className="ghost" onClick={onCancel}>
          取消
        </button>
      </div>
    </div>
  )
}
