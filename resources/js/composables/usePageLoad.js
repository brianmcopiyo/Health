import { ref } from 'vue'
import { router } from '@/plugins/router'
import { httpStatus, pageErrorRoute } from '@/utils/errors'

export const pageLoadError = ref(null)
export const pageLoading = ref(false)

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

export const withPageLoad = async (loader, options = {}) => {
  if (!options.silent) {
    pageLoadError.value = null
    pageLoading.value = true
  }

  try {
    await loader()
  }
  catch (error) {
    const status = httpStatus(error)
    if (status === 401)
      return

    const dest = pageErrorRoute(status)
    if (dest && !options.silent) {
      pageLoadError.value = null
      await router.replace(dest)
      return
    }

    if (!options.silent)
      pageLoadError.value = error?.data?.message || error?.message || 'Unable to load this page'
    console.error(error)
  }
  finally {
    if (!options.silent)
      pageLoading.value = false
  }
}
