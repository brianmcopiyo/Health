let overlayCount = 0

export const useOverlay = (isOpen, close) => {
  const onKeydown = event => {
    if (event.key === 'Escape' && isOpen.value)
      close()
  }

  watch(isOpen, (value, previous) => {
    if (value === previous)
      return

    if (value) {
      overlayCount += 1
      document.body.classList.add('h-overlay-open')
      return
    }

    if (overlayCount > 0)
      overlayCount -= 1

    if (overlayCount === 0)
      document.body.classList.remove('h-overlay-open')
  }, { immediate: true })

  onMounted(() => window.addEventListener('keydown', onKeydown))
  onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown)
    if (!isOpen.value)
      return

    if (overlayCount > 0)
      overlayCount -= 1

    if (overlayCount === 0)
      document.body.classList.remove('h-overlay-open')
  })
}
