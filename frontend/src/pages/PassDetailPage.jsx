import { useCallback, useEffect, useRef, useState } from 'react'
import { useParams } from 'react-router-dom'
import { api } from '../api/client.js'
import { photoUrl } from '../api/client.js'
import { useAuth } from '../auth.jsx'

const RESULT_TEXT = { qualified: '合格', unqualified: '不合格' }

function RecognitionPanel({ pass, onChanged }) {
  const [plate, setPlate] = useState('')
  const [saving, setSaving] = useState(false)
  const [err, setErr] = useState('')
  const [ok, setOk] = useState('')
  const [hasManual, setHasManual] = useState(!!pass.manual_plate_number)
  const locked = pass.status === 'released'

  async function save(e) {
    e.preventDefault()
    setErr(''); setOk(''); setSaving(true)
    try {
      await api.post(`/api/gate-passes/${pass.id}/manual-plate`, { manual_plate: plate.trim() })
      setHasManual(true); setOk('人工车牌已登记，原识别失败结果仍保留在记录中')
      onChanged()
    } catch (e2) { setErr(e2.message) } finally { setSaving(false) }
  }

  return (
    <section className="card">
      <h3>车牌识别</h3>
      <div className="kv">
        <span className="muted">识别结果：</span>
        {pass.recognition.status === 'success'
          ? <b className="ok-text">成功 · {pass.plate_number}</b>
          : <b className="bad-text">识别失败</b>}
        {pass.recognition.confidence != null && <span className="muted small">（置信度 {pass.recognition.confidence}%）</span>}
      </div>
      {pass.recognition.status === 'failed' && (
        <>
          <details className="raw-box">
            <summary>查看识别服务原始返回（失败原因与请求号，不可修改）</summary>
            <pre>{JSON.stringify(pass.recognition.raw, null, 2)}</pre>
          </details>
          {hasManual
            ? <div className="kv"><span className="muted">人工登记车牌：</span><b>{pass.manual_plate_number}</b>
                <span className="muted small">（登记人：{pass.manual_operator?.name}）</span></div>
            : locked
              ? <div className="alert alert-warn">该单已放行且未人工登记车牌</div>
              : <form onSubmit={save} className="manual-plate">
                  <label>识别失败，请人工登记车牌（原识别结果同时保留）
                    <input value={plate} onChange={e => setPlate(e.target.value)} maxLength={20} placeholder="如：京A12345" required />
                  </label>
                  {err && <div className="alert alert-error">{err}</div>}
                  {ok && <div className="alert alert-ok">{ok}</div>}
                  <button className="btn btn-primary" disabled={saving || !plate.trim()}>{saving ? '保存中…' : '保存人工登记'}</button>
                </form>}
        </>
      )}
      <div className="muted small">出场单创建人：{pass.creator?.name} · {pass.driver_name ? `司机：${pass.driver_name}` : ''}</div>
    </section>
  )
}

function InspectionRound({ round, isLatest }) {
  const qualified = round.result === 'qualified'
  return (
    <section className={`card round-card ${qualified ? 'round-ok' : 'round-bad'}`}>
      <div className="round-head">
        <h3>第 {round.round} 轮检查{round.round === 1 ? '（初次检查）' : '（复洗后复查）'}
          {isLatest && <span className="latest-tag">本次最后一轮</span>}
        </h3>
        <span className={`result-tag ${qualified ? 'res-ok' : 'res-bad'}`}>
          {qualified ? '✓ 合格' : '✗ 不合格 · 退回洗轮区'}
        </span>
      </div>
      <div className="muted small">
        检查人：{round.inspector?.name} · {new Date(round.inspected_at).toLocaleString('zh-CN')}
        {round.finding ? <> · 检查说明：{round.finding}</> : null}
      </div>
      <div className="photo-grid">
        {round.photos.map(p => (
          <figure key={p.id} className="photo-fig">
            <a href={photoUrl(p.url)} target="_blank" rel="noreferrer">
              <img src={photoUrl(p.url)} alt={p.view_type === 'wheel' ? '车轮照片' : '车身照片'} />
            </a>
            <figcaption>
              {p.view_type === 'wheel' ? '🛞 车轮照片' : '🚛 车身照片'}
              <span className="muted small hash" title={p.file_hash}>{p.file_hash.slice(0, 12)}…</span>
            </figcaption>
          </figure>
        ))}
      </div>
    </section>
  )
}

function AddInspectionForm({ passId, onDone }) {
  const [result, setResult] = useState('unqualified')
  const [wheel, setWheel] = useState(null)
  const [body, setBody] = useState(null)
  const [finding, setFinding] = useState('')
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState('')

  const submit = async (e) => {
    e.preventDefault()
    setErr('')
    if (!wheel || !body) { setErr('每一轮都必须上传当次拍摄的车轮照片和车身照片'); return }
    if (wheel === body || (wheel.size === body.size && wheel.name === body.name)) {
      setErr('车轮照片与车身照片不能是同一个文件')
      return
    }
    setBusy(true)
    try {
      const fd = new FormData()
      fd.append('result', result)
      fd.append('wheel_photo', wheel)
      fd.append('body_photo', body)
      if (finding.trim()) fd.append('finding', finding.trim())
      await api.post(`/api/gate-passes/${passId}/inspections`, fd)
      onDone()
    } catch (e2) {
      setErr(e2.code === 'PHOTO_ALREADY_USED'
        ? '系统拒绝：上传的照片与历史出场检查照片相同。必须使用本次当场拍摄的新照片，不能引用以前出场的照片。'
        : e2.message)
    } finally { setBusy(false) }
  }

  return (
    <form className="card add-round" onSubmit={submit}>
      <h3>增加一轮检查（复洗后在原单上复查）</h3>
      <p className="muted small">每一轮都要重新上传本轮当场拍摄的照片；系统会比对全部历史照片，发现复用旧照片将拒绝提交。</p>
      <div className="result-radio">
        <label className={result === 'unqualified' ? 'rr-active bad' : ''}>
          <input type="radio" name="result" checked={result === 'unqualified'} onChange={() => setResult('unqualified')} />
          不合格（退回洗轮区）
        </label>
        <label className={result === 'qualified' ? 'rr-active ok' : ''}>
          <input type="radio" name="result" checked={result === 'qualified'} onChange={() => setResult('qualified')} />
          合格
        </label>
      </div>
      <div className="photo-inputs">
        <label>🛞 本轮车轮照片（必传）
          <input type="file" accept="image/*" required onChange={e => setWheel(e.target.files?.[0])} />
        </label>
        <label>🚛 本轮车身照片（必传）
          <input type="file" accept="image/*" required onChange={e => setBody(e.target.files?.[0])} />
        </label>
      </div>
      <label>泥污/检查说明（可选）
        <input value={finding} onChange={e => setFinding(e.target.value)} maxLength={200}
          placeholder="如：后轮挡泥板残留泥点" />
      </label>
      {err && <div className="alert alert-error">{err}</div>}
      <button className="btn btn-primary" disabled={busy}>{busy ? '提交中…' : '提交本轮检查结果'}</button>
    </form>
  )
}

function ManualReleaseDialog({ onClose, onConfirm, busy, err }) {
  const [reason, setReason] = useState('')
  return (
    <div className="modal-mask" onClick={onClose}>
      <div className="modal card" onClick={e => e.stopPropagation()}>
        <h3>值班员人工放行</h3>
        <p className="alert-warn-inline">当前最后一轮检查为<b>不合格</b>。人工放行将绕过洗轮检查结论，请确认现场情况并填写原因。</p>
        <label>放行原因（必填，将永久留档）
          <textarea value={reason} onChange={e => setReason(e.target.value)} rows={4} maxLength={200}
            placeholder="如：应急抢险车辆，经现场负责人XXX口头确认" />
        </label>
        {err && <div className="alert alert-error">{err}</div>}
        <div className="row">
          <button className="btn btn-danger" disabled={busy || !reason.trim()}
            onClick={() => onConfirm(reason.trim())}>{busy ? '下发中…' : '确认人工放行'}</button>
          <button className="btn btn-ghost" onClick={onClose}>取消</button>
        </div>
      </div>
    </div>
  )
}

function ReleasePanel({ pass, latest, onChanged }) {
  const { isSupervisor, user } = useAuth()
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState('')
  const [showManual, setShowManual] = useState(false)

  const doRelease = async (type, reason) => {
    setErr(''); setBusy(true)
    try {
      await api.post(`/api/gate-passes/${pass.id}/release`, { type, reason })
      setShowManual(false)
      onChanged()
    } catch (e) {
      if (e.code === 'FORBIDDEN') setErr('权限不足：仅值班员可执行人工放行（后端已拒绝该操作）')
      else if (e.code === 'LATEST_ROUND_NOT_QUALIFIED') setErr(e.message)
      else setErr(e.message)
    } finally { setBusy(false) }
  }

  if (pass.status === 'released' && pass.release) {
    return (
      <section className="card release-panel released">
        <h3>🚦 闸机放行记录</h3>
        <div className="kv"><span className="muted">放行方式：</span>
          <b>{pass.release.release_type === 'normal' ? '正常放行（检查合格）' : '人工放行（值班员确认）'}</b>
        </div>
        <div className="kv muted small">
          放行时间：{new Date(pass.release.released_at).toLocaleString('zh-CN')} · 操作人：{pass.release.operator?.name}
        </div>
        {pass.release.reason && <div className="manual-reason">人工放行原因：{pass.release.reason}</div>}
        <details className="raw-box">
          <summary>闸机接口应答</summary>
          <pre>{JSON.stringify(pass.release.gate_response_raw, null, 2)}</pre>
        </details>
      </section>
    )
  }

  const latestQualified = latest?.result === 'qualified'

  return (
    <section className="card release-panel">
      <h3>🚦 闸机放行</h3>
      {!latest && <div className="alert alert-warn">出场单尚无检查记录，请先完成第 1 轮检查。</div>}
      {latest && !latestQualified && (
        <div className="alert alert-error">
          最后一轮（第 {latest.round} 轮）检查结论为<b>不合格</b>，闸机正常放行已被锁定。
          请退回洗轮区复洗，并在本单上新增一轮复查。
        </div>
      )}
      {latest && latestQualified && (
        <div className="alert alert-ok">最后一轮（第 {latest.round} 轮）检查合格，可以向闸机发送放行指令。</div>
      )}
      {err && <div className="alert alert-error">{err}</div>}
      <div className="row">
        <button className="btn btn-primary" disabled={busy || !latestQualified}
          title={latestQualified ? '' : '最后一轮检查不合格，禁止放行'}
          onClick={() => doRelease('normal')}>
          {busy ? '下发中…' : '发送闸机放行指令'}
        </button>
        {isSupervisor
          ? <button className="btn btn-danger" disabled={busy || !latest || latestQualified}
              onClick={() => setShowManual(true)}>
              值班员人工放行…
            </button>
          : <span className="muted small lock-note">🔒 人工放行需值班员账号确认并填写原因（当前账号无权限）</span>}
      </div>
      {showManual && (
        <ManualReleaseDialog busy={busy} err={err}
          onClose={() => setShowManual(false)}
          onConfirm={(reason) => doRelease('manual', reason)} />
      )}
    </section>
  )
}

export default function PassDetailPage() {
  const { id } = useParams()
  const [pass, setPass] = useState(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    setError('')
    try {
      const d = await api.get(`/api/gate-passes/${id}`)
      setPass(d.data)
    } catch (e) { setError(e.message) } finally { setLoading(false) }
  }, [id])

  useEffect(() => { load() }, [load])

  if (loading) return <div className="muted">加载中…</div>
  if (error) return <div className="alert alert-error">{error}</div>
  if (!pass) return null

  const rounds = pass.inspections || []
  const latest = rounds.length ? rounds[rounds.length - 1] : null
  const released = pass.status === 'released'

  return (
    <div className="detail-grid">
      <div className="detail-main">
        <div className="detail-title">
          <h2>出场单 #{String(pass.id).padStart(4, '0')}</h2>
          <span className={`status-tag st-${pass.status}`}>
            {pass.status === 'pending' ? '检查/复洗中' : pass.status === 'passed' ? '待放行' : '已放行'}
          </span>
        </div>
        <RecognitionPanel pass={pass} onChanged={load} />
        {rounds.map(r => (
          <InspectionRound key={r.id} round={r} isLatest={!released && r.id === latest?.id} />
        ))}
        {!released && <AddInspectionForm passId={pass.id} onDone={load} />}
      </div>
      <div className="detail-side">
        <ReleasePanel pass={pass} latest={latest} onChanged={load} />
        <section className="card tips">
          <h3>规则提示</h3>
          <ul>
            <li>同一辆车每次出场都是新出场单；本单内复洗通过“增加一轮检查”留痕。</li>
            <li>只有<b>本次最后一轮</b>检查合格才允许放行，历史合格结论无效。</li>
            <li>每轮照片必须当次上传，重复使用历史照片会被系统按文件指纹拒绝。</li>
            <li>人工放行仅限值班员，且必须填写原因。</li>
          </ul>
        </section>
      </div>
    </div>
  )
}
