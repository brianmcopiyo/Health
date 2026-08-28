import { ref } from 'vue'

export const pageLoadError = ref(null)
export const pageLoading = ref(false)

export const asList = value => (Array.isArray(value) ? value : [])

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
