import { sameValue } from '@/utils/formOptions'

let activeClose = null

export const usePopover = () => {
  const open = ref(false)
  const triggerRef = ref(null)
  const panelRef = ref(null)
  const coords = ref({
    top: 0,
    left: 0,
    width: 240,
    maxHeight: 280,
  })

  const place = () => {
    const el = triggerRef.value
    if (!el)
      return

    const rect = el.getBoundingClientRect()
    const spaceBelow = window.innerHeight - rect.bottom - 12
    const spaceAbove = rect.top - 12
    const flip = spaceBelow < 200 && spaceAbove > spaceBelow
    const maxHeight = Math.max(148, Math.min(320, flip ? spaceAbove : spaceBelow))

    coords.value = {
      top: flip ? Math.max(8, rect.top - maxHeight - 6) : rect.bottom + 6,
      left: Math.min(rect.left, window.innerWidth - Math.min(rect.width, window.innerWidth - 16)),
      width: Math.max(rect.width, 198),
      maxHeight,
    }
  }

  const close = () => {
    open.value = false
    if (activeClose === close)
      activeClose = null
  }

  const setOpen = value => {
    if (value) {
      if (activeClose && activeClose !== close)
        activeClose()
      activeClose = close
      open.value = true
      nextTick(place)
      return
    }
    close()
  }

  const toggle = () => setOpen(!open.value)

  const onDoc = event => {
    if (!open.value)
      return
    const path = event.target
    if (triggerRef.value?.contains(path) || panelRef.value?.contains(path))
      return
    close()
  }

  const onKey = event => {
    if (!open.value || event.key !== 'Escape')
      return
    event.stopImmediatePropagation()
    close()
  }

  watch(open, value => {
    if (value) {
      window.addEventListener('resize', place)
      window.addEventListener('scroll', place, true)
      return
    }
    window.removeEventListener('resize', place)
    window.removeEventListener('scroll', place, true)
  })

  onMounted(() => {
    document.addEventListener('mousedown', onDoc)
    window.addEventListener('keydown', onKey, true)
  })

  onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDoc)
    window.removeEventListener('keydown', onKey, true)
    window.removeEventListener('resize', place)
    window.removeEventListener('scroll', place, true)
    if (activeClose === close)
      activeClose = null
  })

  return { open, triggerRef, panelRef, coords, place, setOpen, toggle, close }
}

export const useListHighlight = (filtered, isOpen) => {
  const highlighted = ref(-1)

  const sync = () => {
    highlighted.value = filtered.value.length ? 0 : -1
  }

  watch(filtered, () => {
    if (isOpen.value)
      sync()
  })

  watch(isOpen, value => {
    if (value)
      sync()
  })

  const move = delta => {
    const total = filtered.value.length
    if (!total)
      return
    const next = highlighted.value + delta
    highlighted.value = (next + total) % total
  }

  const current = () => filtered.value[highlighted.value] || null

  const isActive = option => highlighted.value >= 0 && sameValue(filtered.value[highlighted.value]?.value, option.value)

  return { highlighted, move, current, isActive }
}
