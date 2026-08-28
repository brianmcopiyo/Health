import { ref } from 'vue'

export const pageLoadError = ref(null)
export const pageLoading = ref(false)

export const asList = value => (Array.isArray(value) ? value : [])

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
    if (!options.silent)
      pageLoadError.value = error?.data?.message || error?.message || 'Unable to load this page'
    console.error(error)
  }
  finally {
    if (!options.silent)
      pageLoading.value = false
  }
}
