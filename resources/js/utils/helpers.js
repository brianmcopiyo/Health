export const getByPath = (item, path) => {
  if (!path)
    return ''
  return path.split('.').reduce((value, key) => value?.[key], item)
}

export const joinContext = (...parts) => parts
  .flat()
  .map(part => (part === null || part === undefined ? '' : String(part).trim()))
  .filter(part => part && part !== '—')
  .join(' · ')

export const routeTo = to => {
  if (!to)
    return undefined
  if (typeof to === 'string') {
    if (to.startsWith('/') || to.startsWith('http'))
      return to
    return { name: to }
  }

  return to
}


export const facilityRecordTo = item => {
  const slug = item?.type?.slug || item?.type

  if (slug === 'ward')
    return { name: 'wards-id', params: { id: item.id } }

  if (slug === 'bed')
    return { name: 'beds-id', params: { id: item.id } }

  return { name: 'facilities-id', params: { id: item.id } }
}

export const formatWhen = value => {
  if (!value)
    return '—'

  return new Date(value).toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export const formatDate = value => {
  if (!value)
    return '—'

  const date = /^\d{4}-\d{2}-\d{2}$/.test(String(value))
    ? new Date(`${value}T00:00:00`)
    : new Date(value)

  if (Number.isNaN(date.getTime()))
    return '—'

  return date.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

export const formatQty = value => Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 3 })

export const queryParam = (query, key) => {
  const value = query?.[key]
  if (Array.isArray(value))
    return value[0] ? String(value[0]) : null
  if (value === undefined || value === null || value === '')
    return null

  return String(value)
}
