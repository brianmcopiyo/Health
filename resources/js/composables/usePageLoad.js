import { ref } from 'vue'

export const pageLoadError = ref(null)

export const asList = value => (Array.isArray(value) ? value : [])

export const withPageLoad = async loader => {
  pageLoadError.value = null

  try {
    await loader()
  }
  catch (error) {
    pageLoadError.value = error?.data?.message || error?.message || 'Unable to load this page'
    console.error(error)
  }
}
