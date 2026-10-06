// 后端返回的照片 URL 是 http://localhost/storage/...，统一转成同源相对路径，
// 经 Vite 代理访问 Laravel，避免跨域和端口写死。
export function photoUrl(url) {
  if (!url) return url
  return url.replace(/^https?:\/\/[^/]+/i, '')
}

async function request(path, { method = 'GET', body, headers } = {}) {
  const token = localStorage.getItem('gate_token')
  const res = await fetch(path, {
    method,
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...headers,
    },
    body,
  })

  const isJson = (res.headers.get('content-type') || '').includes('application/json')
  const data = isJson ? await res.json() : await res.text()

  if (!res.ok) {
    if (res.status === 401) {
      localStorage.removeItem('gate_token')
      localStorage.removeItem('gate_user')
      if (!location.pathname.startsWith('/login')) location.assign('/login')
    }
    const err = new Error(data?.message || `请求失败（${res.status}）`)
    err.status = res.status
    err.code = data?.error_code || null
    err.data = data?.errors || null
    throw err
  }
  return data
}

export const api = {
  get: (p) => request(p),
  post: (p, body) =>
    request(p, {
      method: 'POST',
      body: body instanceof FormData ? body : JSON.stringify(body),
      headers: body instanceof FormData ? {} : { 'Content-Type': 'application/json' },
    }),
}
