import { computed, ref, watch } from 'vue'
import { useCookie } from '@/composables/useCookie'

const MODES = ['light', 'dark', 'system']

const systemDark = ref(
  typeof window !== 'undefined'
    ? window.matchMedia('(prefers-color-scheme: dark)').matches
    : false,
)

let bound = false
let watching = false

const normalize = value => (MODES.includes(value) ? value : 'system')

const resolveDark = (mode, prefersDark) => {
  if (mode === 'dark')
    return true
  if (mode === 'light')
    return false
  return prefersDark
}

const applyTheme = dark => {
  if (typeof document === 'undefined')
    return
  document.documentElement.dataset.theme = dark ? 'dark' : 'light'
  document.documentElement.style.colorScheme = dark ? 'dark' : 'light'
}

const bindSystem = () => {
  if (bound || typeof window === 'undefined')
    return
  bound = true
  const media = window.matchMedia('(prefers-color-scheme: dark)')
  const onChange = event => { systemDark.value = event.matches }
  if (media.addEventListener)
    media.addEventListener('change', onChange)
  else
    media.addListener(onChange)
}

export const useTheme = () => {
  const mode = useCookie('theme', { default: () => 'system' })
  if (!MODES.includes(mode.value))
    mode.value = 'system'

  bindSystem()

  const isDark = computed(() => resolveDark(normalize(mode.value), systemDark.value))

  if (!watching) {
    watching = true
    watch(isDark, applyTheme, { immediate: true })
  }

  const setTheme = value => {
    mode.value = normalize(value)
  }

  const toggleTheme = () => {
    mode.value = isDark.value ? 'light' : 'dark'
  }

  return { mode, isDark, setTheme, toggleTheme }
}
