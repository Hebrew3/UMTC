
const API_ROOT = (
  import.meta.env.VITE_API_URL || '/api'
).replace(/\/+$/, '')

let csrfToken = null

async function parseResponse(response) {
  const contentType = response.headers.get('content-type') || ''

  if (contentType.includes('application/json')) {
    return await response.json()
  }

  return null
}

async function initializeCsrf() {
  if (csrfToken) return csrfToken

  const response = await fetch(`${API_ROOT}/csrf-token`, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
    },
    credentials: 'include',
  })

  const payload = await parseResponse(response)

  if (!response.ok || !payload?.token) {
    throw new Error(
      payload?.message || 'Unable to initialize a secure session.'
    )
  }

  csrfToken = payload.token

  return csrfToken
}

async function request(path, options = {}) {
  const method = (options.method || 'GET').toUpperCase()

  const headers = new Headers(options.headers || {})

  headers.set('Accept', 'application/json')

  let body = options.body

  const bodyIsForm = body instanceof FormData

  if (
    body &&
    !bodyIsForm &&
    typeof body !== 'string'
  ) {
    headers.set('Content-Type', 'application/json')
    body = JSON.stringify(body)
  }

  // Initialize CSRF protection for write requests
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    const token = await initializeCsrf()
    headers.set('X-CSRF-TOKEN', token)
  }

  const normalizedPath = path.startsWith('/')
    ? path
    : `/${path}`

  const response = await fetch(
    `${API_ROOT}${normalizedPath}`,
    {
      ...options,
      method,
      headers,
      body,
      credentials: 'include',
    }
  )

  const payload = await parseResponse(response)

  if (!response.ok) {
    const firstValidationError =
      payload?.errors &&
      Object.values(payload.errors)[0]?.[0]

    const error = new Error(
      firstValidationError ||
      payload?.message ||
      `Request failed (${response.status}).`
    )

    error.status = response.status
    error.data = payload

    throw error
  }

  return payload
}

export const api = {
  get: (path, options = {}) =>
    request(path, options),

  post: (path, body, options = {}) =>
    request(path, {
      ...options,
      method: 'POST',
      body,
    }),

  put: (path, body, options = {}) =>
    request(path, {
      method: 'PUT',
      body,
    }),

  delete: (path) =>
    request(path, {
      method: 'DELETE',
    }),

  clearSession: () => {
    csrfToken = null
  },
}