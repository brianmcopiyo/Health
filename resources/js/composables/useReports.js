import { formatWhen } from '@/utils/helpers'
import { labelize } from '@/utils/status'

const pad = value => String(value).padStart(2, '0')

export const reportDate = (date = new Date()) => {
  const value = date instanceof Date ? date : new Date(date)
  return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}`
}

export const defaultReportFilters = () => {
  const to = new Date()
  const from = new Date()
  from.setDate(from.getDate() - 29)

  return {
    from: reportDate(from),
    to: reportDate(to),
    department_id: null,
    facility_id: null,
    clinician_id: null,
    status: null,
    patient_type: null,
    kind: null,
  }
}

export const reportQuery = (section, filters = {}, extra = {}) => {
  const query = { section, ...extra }
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '')
      query[key] = value
  })
  return query
}

export const reportCell = (item, key) => {
  const value = item?.[key]
  if (value === null || value === undefined || value === '')
    return '—'
  if (key === 'when' || key === 'opened' || key === 'registered')
    return formatWhen(value)
  if (['status', 'type', 'sex'].includes(key))
    return labelize(value)
  return value
}

export const downloadReport = async (section, filters, format) => {
  const blob = await $api('/reports/export', {
    query: reportQuery(section, filters, { format, scope: 'complete' }),
    responseType: 'blob',
    timeout: 120000,
  })
  const href = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = href
  link.download = `hospital-report-${filters.from || 'range'}-${filters.to || 'now'}.${format}`
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(href)
}
