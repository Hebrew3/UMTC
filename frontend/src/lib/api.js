const API_ROOT = '/api'

let csrfToken

async function request(path, options = {}) {
  const method = options.method || 'GET'
  const headers = new Headers(options.headers || {})
  const bodyIsForm = options.body instanceof FormData
  headers.set('Accept', 'application/json')

  if (options.body && !bodyIsForm && typeof options.body !== 'string') {
    headers.set('Content-Type', 'application/json')
    options.body = JSON.stringify(options.body)
  }

  if (!['GET', 'HEAD', 'OPTIONS'].includes(method.toUpperCase())) {
    if (!csrfToken) {
      const tokenResponse = await fetch(`${API_ROOT}/csrf-token`, { credentials: 'include' })
      if (!tokenResponse.ok) throw new Error('Unable to initialize a secure session.')
      csrfToken = (await tokenResponse.json()).token
    }
    headers.set('X-CSRF-TOKEN', csrfToken)
  }

  const response = await fetch(`${API_ROOT}${path}`, {
    ...options,
    method,
    headers,
    credentials: 'include',
  })

  const contentType = response.headers.get('content-type') || ''
  const payload = contentType.includes('application/json') ? await response.json() : null
  if (!response.ok) {
    const firstValidationError = payload?.errors && Object.values(payload.errors)[0]?.[0]
    const error = new Error(firstValidationError || payload?.message || `Request failed (${response.status}).`)
    error.status = response.status
    throw error
  }
  return payload
}

export const api = {
  get: (path, options = {}) => request(path, options),
  post: (path, body, options = {}) => request(path, { ...options, method: 'POST', body }),
  put: (path, body) => request(path, { method: 'PUT', body }),
  delete: (path) => request(path, { method: 'DELETE' }),
  clearSession: () => { csrfToken = undefined },
}
