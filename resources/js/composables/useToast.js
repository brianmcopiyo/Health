import { ref } from 'vue'

const toasts = ref([])
let nextId = 0

const dismiss = id => {
  toasts.value = toasts.value.filter(item => item.id !== id)
}

const push = (message, tone = 'info', timeout = 3200) => {
  const id = ++nextId
  toasts.value = [...toasts.value.slice(-4), { id, message, tone }]
  if (timeout > 0)
    window.setTimeout(() => dismiss(id), timeout)

  return id
}

export const useToast = () => ({
  toasts,
  push,
  dismiss,
  success: message => push(message, 'success'),
  error: message => push(message, 'error'),
  warning: message => push(message, 'warning'),
  info: message => push(message, 'info'),
})
