const TOKEN_KEY = 'wash_gate_token'
const USER_KEY = 'wash_gate_user'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}
export function getUser() {
  const raw = localStorage.getItem(USER_KEY)
  return raw ? JSON.parse(raw) : null
}
export function saveSession(token, user) {
  localStorage.setItem(TOKEN_KEY, token)
  localStorage.setItem(USER_KEY, JSON.stringify(user))
}
export function clearSession() {
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(USER_KEY)
}

export async function api(path, { method = 'GET', body, formData } = {}) {
  const headers = { Authorization: `Bearer ${getToken()}` }
  if (body !== undefined) headers['Content-Type'] = 'application/json'

  const res = await fetch(`/api${path}`, {
    method,
    headers,
    body: formData ?? (body !== undefined ? JSON.stringify(body) : undefined),
  })

  const data = await res.json().catch(() => ({}))
  if (!res.ok) {
    const err = new Error(data.message || `请求失败(${res.status})`)
    err.status = res.status
    err.data = data
    throw err
  }
  return data
}
