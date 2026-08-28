export const getByPath = (item, path) => {
  if (!path)
    return ''
  return path.split('.').reduce((value, key) => value?.[key], item)
}
