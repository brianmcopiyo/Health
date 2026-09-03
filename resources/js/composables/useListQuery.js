import { queryParam } from '@/utils/helpers'

const packedFrom = query => {
  const next = {}
  Object.keys(query || {}).forEach(key => {
    const value = queryParam(query, key)
    if (value !== null && value !== undefined && value !== '')
      next[key] = String(value)
  })
  return next
}

const sameQuery = (left, right) => JSON.stringify(left) === JSON.stringify(right)

export const formatResultCount = meta => {
  const total = Number(meta?.total)
  if (!Number.isFinite(total))
    return null

  return `${total} result${total === 1 ? '' : 's'}`
}

export const useListQuery = (keys = [], options = {}) => {
  const searchKey = options.searchKey || 'q'
  const route = useRoute()
  const router = useRouter()
  const page = ref(Number(queryParam(route.query, 'page')) || 1)
  const q = ref(queryParam(route.query, searchKey) || '')
  const values = reactive(Object.fromEntries(keys.map(key => [key, queryParam(route.query, key)])))

  const filterValues = computed({
    get: () => ({ ...values }),
    set: next => {
      keys.forEach(key => {
        values[key] = next?.[key] ?? null
      })
    },
  })

  const packed = () => {
    const query = {}
    if (page.value > 1)
      query.page = String(page.value)
    if (String(q.value || '').trim())
      query[searchKey] = String(q.value).trim()
    keys.forEach(key => {
      const value = values[key]
      if (value === null || value === undefined || value === '')
        return
      query[key] = String(value)
    })
    return query
  }

  const writeUrl = () => {
    const next = packed()
    if (sameQuery(next, packedFrom(route.query)))
      return
    router.replace({ query: next })
  }

  const applyRoute = () => {
    page.value = Number(queryParam(route.query, 'page')) || 1
    q.value = queryParam(route.query, searchKey) || ''
    keys.forEach(key => {
      values[key] = queryParam(route.query, key)
    })
  }

  const apiQuery = extra => {
    const query = { page: page.value, ...(extra || {}) }
    if (String(q.value || '').trim())
      query[searchKey] = String(q.value).trim()
    keys.forEach(key => {
      if (values[key] !== null && values[key] !== undefined && values[key] !== '')
        query[key] = values[key]
    })
    return query
  }

  const onSearch = load => {
    page.value = 1
    writeUrl()
    return load()
  }

  const onChange = load => {
    page.value = 1
    writeUrl()
    return load()
  }

  const onPage = (value, load) => {
    page.value = value
    writeUrl()
    return load()
  }

  const sync = loader => {
    watch(() => route.query, () => {
      if (sameQuery(packedFrom(route.query), packed()))
        return
      applyRoute()
      loader()
    })
  }

  return {
    page,
    q,
    values,
    filterValues,
    apiQuery,
    writeUrl,
    onSearch,
    onChange,
    onPage,
    sync,
    resultCount: formatResultCount,
  }
}
