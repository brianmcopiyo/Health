import { ref } from 'vue'

export const pageLoading = ref(true)

let generation = 0

export const currentPageNav = () => generation

export const isNewPage = (to, from) => {
  if (!from?.matched?.length)
    return true
  if (to.name !== from.name)
    return true

  const keys = new Set([
    ...Object.keys(to.params || {}),
    ...Object.keys(from.params || {}),
  ])

  for (const key of keys) {
    if (String(to.params?.[key] ?? '') !== String(from.params?.[key] ?? ''))
      return true
  }

  return false
}

export const startPageNav = () => {
  generation += 1
  pageLoading.value = true
  return generation
}

export const finishPageNav = id => {
  if (id !== generation)
    return
  pageLoading.value = false
}

export const forceFinishPageNav = () => {
  generation += 1
  pageLoading.value = false
}
