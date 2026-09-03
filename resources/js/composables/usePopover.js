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

    const next = {
      top,
      left,
      width: width || undefined,
      maxHeight,
    }
    const prev = coords.value
    if (
      prev.top === next.top
      && prev.left === next.left
      && prev.width === next.width
      && prev.maxHeight === next.maxHeight
    )
      return

    coords.value = next
  }

  const bindPanel = el => {
    if (el === panelRef.value)
      return

    panelRef.value = el
    if (el && open.value)
      nextTick(() => {
        if (panelRef.value === el)
          place()
      })
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
  }

  const unlisten = () => {
    window.removeEventListener('resize', place)
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

export const useListHighlight = (filtered, isOpen, selected = null) => {
  const highlighted = ref(-1)

  const selectedValue = () => (typeof selected === 'function' ? selected() : unref(selected))

  const selectedIndex = () => {
    const items = filtered.value || []
    if (!items.length)
      return -1

    const value = selectedValue()
    if (value == null || value === '')
      return -1

    const values = Array.isArray(value) ? value : [value]
    if (!values.length)
      return -1

    return items.findIndex(item => values.some(itemValue => sameValue(item.value, itemValue)))
  }

  const sync = () => {
    const index = selectedIndex()
    highlighted.value = index >= 0 ? index : -1
  }

  watch(filtered, () => {
    if (!isOpen.value)
      return

    const index = selectedIndex()
    if (index >= 0) {
      highlighted.value = index
      return
    }

    if (highlighted.value >= filtered.value.length)
      highlighted.value = filtered.value.length ? 0 : -1
  })

  watch(isOpen, value => {
    if (value)
      sync()
    else
      highlighted.value = -1
  })

  const move = delta => {
    const total = filtered.value.length
    if (!total)
      return
    if (highlighted.value < 0)
      highlighted.value = delta > 0 ? 0 : total - 1
    else
      highlighted.value = (highlighted.value + delta + total) % total
  }

  const activate = option => {
    const index = filtered.value.findIndex(item => sameValue(item.value, option?.value))
    highlighted.value = index
  }

  const current = () => filtered.value[highlighted.value] || null

  const isActive = option => highlighted.value >= 0 && sameValue(filtered.value[highlighted.value]?.value, option.value)

  return { highlighted, move, current, isActive, activate }
}
