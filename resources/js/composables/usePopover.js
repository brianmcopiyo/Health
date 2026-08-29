import { sameValue } from '@/utils/formOptions'

let activeClose = null

export const popoverStyle = coords => {
  const style = {
    top: `${coords.top}px`,
    left: `${coords.left}px`,
  }
  if (coords.width)
    style.width = `${coords.width}px`
  if (coords.maxWidth)
    style.maxWidth = `${coords.maxWidth}px`
  if (coords.maxHeight)
    style.maxHeight = `${coords.maxHeight}px`
  return style
}

export const usePopover = (options = {}) => {
  const open = ref(false)
  const triggerRef = ref(null)
  const panelRef = ref(null)
  const coords = ref({
    top: 0,
    left: 0,
    width: 0,
    maxHeight: 280,
  })
  let observer

  const viewport = () => {
    const view = window.visualViewport
    return {
      left: view?.offsetLeft ?? 0,
      top: view?.offsetTop ?? 0,
      width: view?.width ?? window.innerWidth,
      height: view?.height ?? window.innerHeight,
    }
  }

  const place = () => {
    const trigger = triggerRef.value
    if (!trigger)
      return

    const align = unref(options.align) || 'start'
    const matchWidth = Boolean(unref(options.matchWidth))
    const minWidth = Number(unref(options.minWidth) || 0)
    const gap = Number(unref(options.gap) ?? 6)
    const pad = 8
    const view = viewport()
    const box = trigger.getBoundingClientRect()
    const panel = panelRef.value
    const limit = view.width - pad * 2
    let width = matchWidth ? Math.max(box.width, minWidth) : 0
    if (width)
      width = Math.min(width, limit)

    const measuredW = panel?.offsetWidth || width || Math.max(box.width, minWidth || 196)
    const measuredH = panel?.offsetHeight || 200
    const spaceBelow = view.top + view.height - box.bottom - pad
    const spaceAbove = box.top - view.top - pad
    const need = Math.min(measuredH, 168)
    const below = unref(options.placement) === 'top'
      ? spaceAbove < need && spaceBelow > spaceAbove
      : spaceBelow >= need || spaceBelow >= spaceAbove

    const boxW = width || measuredW
    let left = box.left
    if (align === 'end')
      left = box.right - boxW
    else if (align === 'center')
      left = box.left + (box.width - boxW) / 2

    left = Math.min(Math.max(left, view.left + pad), view.left + view.width - pad - boxW)

    let top
    let maxHeight
    if (below) {
      top = box.bottom + gap
      maxHeight = Math.max(120, view.top + view.height - pad - top)
    }
    else {
      maxHeight = Math.max(120, box.top - gap - view.top - pad)
      const height = Math.min(panel ? panel.offsetHeight : measuredH, maxHeight)
      top = Math.max(view.top + pad, box.top - gap - height)
    }

    coords.value = {
      top,
      left,
      width: width || undefined,
      maxHeight,
    }
  }

  const bindPanel = el => {
    observer?.disconnect()
    observer = null
    panelRef.value = el
    if (el && open.value) {
      nextTick(place)
      if (typeof ResizeObserver !== 'undefined') {
        observer = new ResizeObserver(place)
        observer.observe(el)
      }
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
      nextTick(() => {
        place()
        requestAnimationFrame(() => {
          place()
          requestAnimationFrame(place)
        })
      })
      return
    }
    close()
  }

  const toggle = () => setOpen(!open.value)

  const onDoc = event => {
    if (!open.value)
      return
    const node = event.target
    if (triggerRef.value?.contains(node) || panelRef.value?.contains(node))
      return
    close()
  }

  const onKey = event => {
    if (!open.value || event.key !== 'Escape')
      return
    event.stopImmediatePropagation()
    close()
    const trigger = triggerRef.value
    if (trigger?.focus)
      trigger.focus()
    else
      trigger?.querySelector?.('button, [tabindex], input, select, textarea')?.focus()
  }

  const listen = () => {
    window.addEventListener('resize', place)
    window.addEventListener('scroll', place, true)
    window.visualViewport?.addEventListener('resize', place)
    window.visualViewport?.addEventListener('scroll', place)
    if (panelRef.value && typeof ResizeObserver !== 'undefined') {
      observer?.disconnect()
      observer = new ResizeObserver(place)
      observer.observe(panelRef.value)
    }
  }

  const unlisten = () => {
    window.removeEventListener('resize', place)
    window.removeEventListener('scroll', place, true)
    window.visualViewport?.removeEventListener('resize', place)
    window.visualViewport?.removeEventListener('scroll', place)
    observer?.disconnect()
    observer = null
  }

  watch(open, value => {
    unlisten()
    if (value)
      listen()
  })

  const route = typeof useRoute === 'function' ? useRoute() : null
  if (route)
    watch(() => route.fullPath, close)

  onMounted(() => {
    document.addEventListener('mousedown', onDoc, true)
    window.addEventListener('keydown', onKey, true)
  })

  onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDoc, true)
    window.removeEventListener('keydown', onKey, true)
    unlisten()
    if (activeClose === close)
      activeClose = null
  })

  return { open, triggerRef, panelRef, coords, place, bindPanel, setOpen, toggle, close }
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
