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

const spokenLabel = label => {
  return String(label || '').replace(/[:*]+$/g, '').trim().split(/\s+/).filter(Boolean).map(word => {
    if (/^[A-Z0-9]{2,}(?:[+/][A-Z0-9]+)*$/.test(word) || /[A-Za-z][0-9]|[0-9][A-Za-z]/.test(word))
      return word

    return word.toLowerCase()
  }).join(' ')
}

export const fieldPlaceholder = (placeholder, label, kind = 'text') => {
  if (placeholder)
    return placeholder

  const name = spokenLabel(label)
  const fallback = {
    text: '',
    textarea: '',
    number: '',
    select: 'Select an option',
    multi: 'Select options',
    combo: 'Type or select',
    date: 'Select date',
    time: 'Select time',
    file: 'Drop files or browse',
    search: 'Search',
  }[kind] ?? ''

  if (!name)
    return fallback

  if (kind === 'select' || kind === 'multi')
    return `Select ${name}`
  if (kind === 'combo')
    return `Type or select ${name}`
  if (kind === 'date' || kind === 'time')
    return `Select ${name}`
  if (kind === 'file')
    return 'Drop files or browse'
  if (kind === 'search' || name === 'search' || name.startsWith('search '))
    return name === 'search' ? 'Search…' : (name.startsWith('search ') ? `Search ${name.slice(7)}` : `Search ${name}`)

  return `Enter ${name}`
}

export const sameValue = (left, right) => {
  if (left === right)
    return true

  if (left == null || right == null)
    return left == null && right == null

  return String(left) === String(right)
}
