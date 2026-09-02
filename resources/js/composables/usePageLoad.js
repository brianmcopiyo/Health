import { ref, unref, watch, onBeforeUnmount } from 'vue'
import { httpStatus, pageErrorCode } from '@/utils/errors'
import { forceFinishPageNav } from '@/composables/useRouteLoad'

export const pageLoadError = ref(null)
export const pageError = ref((() => {
  const code = typeof window !== 'undefined' ? window.__PAGE_ERROR__ : null
  if (typeof window !== 'undefined')
    window.__PAGE_ERROR__ = null

  return code ? { code: Number(code) } : null
})())
export const LOAD_HINT_DELAY = 180

export const setPageError = code => {
  pageError.value = code ? { code: Number(code) } : null
  if (code)
    forceFinishPageNav()
}

export const clearPageError = () => {
  pageError.value = null
  pageLoadError.value = null
}

export const asList = value => {
  if (Array.isArray(value))
    return value
  if (Array.isArray(value?.data))
    return value.data

  return []
}

export const asPageMeta = value => {
  if (!value || Array.isArray(value)) {
    return {
      current_page: 1,
      last_page: 1,
      per_page: Array.isArray(value) ? value.length : 25,
      total: Array.isArray(value) ? value.length : 0,
    }
  }

  return {
    current_page: value.current_page ?? 1,
    last_page: value.last_page ?? 1,
    per_page: value.per_page ?? 25,
    total: value.total ?? 0,
  }
}

export const compactListQuery = (extra = {}) => ({ compact: 1, per_page: 50, ...extra })

export const saveError = error => {
  const data = error?.data
  const first = data?.errors ? Object.values(data.errors).flat().find(Boolean) : null

  return first || data?.message || error?.message || 'Unable to save'
}

export const wrapSave = async (saving, formError, action) => {
  if (unref(saving))
    return false

  saving.value = true
  formError.value = ''
  try {
    await action()
    return true
  }
  catch (error) {
    if (httpStatus(error) === 401)
      return false

    formError.value = saveError(error)
    return false
  }
  finally {
    saving.value = false
  }
}

export const useDelayedVisible = (source, delay = LOAD_HINT_DELAY) => {
  const visible = ref(false)
  let timer

  const sync = value => {
    clearTimeout(timer)
    if (value) {
      timer = window.setTimeout(() => {
        visible.value = true
      }, delay)
      return
    }
    visible.value = false
  }

  watch(() => (typeof source === 'function' ? source() : unref(source)), sync, { immediate: true })
  onBeforeUnmount(() => clearTimeout(timer))

  return visible
}

export const withPageLoad = async (loader, options = {}) => {
  if (!options.silent)
    pageLoadError.value = null

  try {
    await loader()
  }
  catch (error) {
    const status = httpStatus(error)
    if (status === 401)
      return

    if (!options.silent) {
      pageLoadError.value = null
      setPageError(pageErrorCode(status) || 500)
      return
    }

    console.error(error)
  }
}

export const usePageQuery = (loader, options = {}) => {
  const pending = ref(options.immediate !== false)

  const run = async (opts = {}) => {
    const silent = opts.silent ?? options.silent ?? false
    if (!silent)
      pending.value = true

    try {
      await withPageLoad(loader, { silent })
    }
    finally {
      if (!silent)
        pending.value = false
    }
  }

  if (options.immediate !== false)
    run()

  return { pending, run }
}
