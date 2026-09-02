export const exportQuery = (filters = {}, extra = {}) => {
  const query = { ...extra }
  Object.entries(filters || {}).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '')
      query[key] = value
  })
  return query
}

export const downloadExport = async (dataset, filters = {}, format = 'xlsx') => {
  const query = exportQuery(filters, { format })
  const blob = await $api(`/exports/${dataset}`, {
    query,
    responseType: 'blob',
    timeout: 120000,
  })
  const stamp = new Date().toISOString().slice(0, 10)
  const href = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = href
  link.download = `${dataset}-${stamp}.${format}`
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(href)
}

export const useExport = () => {
  const toast = useToast()
  const exporting = ref('')

  const exportDataset = async (dataset, filters = {}, format = 'xlsx') => {
    if (exporting.value)
      return

    exporting.value = format
    try {
      await downloadExport(dataset, filters, format)
      toast.success('Exported the complete report')
    }
    catch (error) {
      toast.error(error?.data?.message || error?.message || 'Unable to export this report')
    }
    finally {
      exporting.value = ''
    }
  }

  return { exporting, exportDataset }
}
