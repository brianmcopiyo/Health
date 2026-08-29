import { ofetch } from 'ofetch'
import { router } from '@/plugins/router'
import { clearSession } from '@/utils/session'
import { pageLoadError } from '@/composables/usePageLoad'

let endingSession = false

const isAuthCall = request => {
  const url = typeof request === 'string' ? request : String(request?.url || request || '')
  return /\/auth\/(login|logout)\b/.test(url)
}

const endSession = () => {
  if (endingSession)
    return

  endingSession = true
  pageLoadError.value = null
  clearSession()

  const current = router.currentRoute.value
  const next = current.meta?.public || current.name === 'login' || current.name === 'errors-code'
    ? undefined
    : current.fullPath

  router.replace({
    name: 'login',
    query: {
      reason: 'expired',
      ...(next ? { to: next } : {}),
    },
  }).finally(() => {
    endingSession = false
  })
}

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  timeout: 20000,
  async onRequest({ options }) {
    const accessToken = useCookie('accessToken').value
    const headers = new Headers(options.headers)
    const isForm = typeof FormData !== 'undefined' && options.body instanceof FormData

    if (!isForm)
      headers.set('Accept', 'application/json')
    if (accessToken)
      headers.set('Authorization', `Bearer ${accessToken}`)

    options.headers = headers
  },
  async onResponseError({ request, response }) {
    if (response.status === 401 && !isAuthCall(request))
      endSession()
  },
})

export const downloadAuthorized = async (path, filename) => {
  const blob = await $api(path, { responseType: 'blob' })
  const href = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = href
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(href)
}
