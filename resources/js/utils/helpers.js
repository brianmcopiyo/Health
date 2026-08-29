export const getByPath = (item, path) => {
  if (!path)
    return ''
  return path.split('.').reduce((value, key) => value?.[key], item)
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
