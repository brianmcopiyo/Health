import { ref, watch } from 'vue'

const store = new Map()

const storageKey = name => `hms:${name}`

const readStored = name => {
  try {
    const raw = localStorage.getItem(storageKey(name))
    if (raw === null || raw === '' || raw === 'null' || raw === 'undefined')
      return null

    return JSON.parse(raw)
  }
  catch {
    return null
  }
}

export const writeStored = (name, value) => {
  if (value === null || value === undefined)
    localStorage.removeItem(storageKey(name))
  else
    localStorage.setItem(storageKey(name), JSON.stringify(value))
}

export const useCookie = (name, options = {}) => {
  if (store.has(name))
    return store.get(name)

  const state = ref(readStored(name) ?? options.default?.() ?? null)

  watch(state, value => {
    writeStored(name, value)
  }, { deep: true, flush: 'sync' })

  store.set(name, state)

  return state
}
