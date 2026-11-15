import axios from 'axios'

/**
 * One axios instance for the whole app.
 * withCredentials + withXSRFToken: the browser sends the Sanctum session cookie
 * and axios copies the XSRF-TOKEN cookie into the X-XSRF-TOKEN header.
 */
export const http = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
})

export function fetchCsrfCookie() {
  return axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}

/*
 * Requests from the SPA's own origin are "stateful" to Sanctum, so every write needs a CSRF
 * token, including the public request form. Fetch the cookie once, before the first write.
 */
const hasXsrfCookie = () => document.cookie.split('; ').some((c) => c.startsWith('XSRF-TOKEN='))

http.interceptors.request.use(async (config) => {
  const method = (config.method || 'get').toLowerCase()
  if (!['get', 'head', 'options'].includes(method) && !hasXsrfCookie()) {
    await fetchCsrfCookie()
  }
  return config
})

/**
 * Turns any failure into the API's own envelope so components handle one shape.
 * @returns {{ status: number, message: string, errors: Record<string, string[]> }}
 */
export function apiError(error) {
  const body = error?.response?.data
  if (body && body.success === false) {
    return { status: error.response.status, message: body.message, errors: body.errors || {} }
  }
  if (!error?.response) {
    return { status: 0, message: 'تعذر الاتصال بالخادم. تأكد من اتصالك بالإنترنت وحاول مرة أخرى.', errors: {} }
  }
  return { status: error.response.status, message: 'حدث خطأ غير متوقع. حاول مرة أخرى.', errors: {} }
}
