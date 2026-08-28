let seq = 0

export const useFieldId = (prefix = 'hf') => {
  seq += 1

  return `${prefix}-${seq}`
}

export const errorText = error => {
  if (Array.isArray(error))
    return error[0] || ''

  return error || ''
}

export const normalizeOptions = (items, itemTitle = 'title', itemValue = 'value') => {
  return (items || []).map(item => {
    if (item === null || item === undefined)
      return { title: '', value: item, raw: item }

    if (['string', 'number', 'boolean'].includes(typeof item))
      return { title: String(item).replaceAll('_', ' '), value: item, raw: item }

    return {
      title: String(item[itemTitle] ?? item.name ?? item.title ?? item.label ?? ''),
      value: item[itemValue] ?? item.id ?? item.value,
      raw: item,
    }
  })
}

export const sameValue = (left, right) => {
  if (left === right)
    return true

  if (left == null || right == null)
    return left == null && right == null

  return String(left) === String(right)
}
