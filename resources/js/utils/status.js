export const facilityStatuses = ['available', 'occupied', 'unavailable', 'maintenance', 'reserved', 'cleaning']
export const referralStatuses = ['pending', 'more_info', 'accepted', 'declined', 'in_transit', 'completed', 'cancelled']
export const assistanceStatuses = ['pending', 'accepted', 'declined', 'fulfilled', 'cancelled']
export const ambulanceStatuses = ['available', 'on_trip', 'maintenance', 'unavailable']
export const tripStatuses = ['dispatched', 'en_route', 'arrived', 'completed', 'cancelled']
export const assistanceTypes = ['staff', 'equipment', 'supplies', 'beds', 'pharmacy', 'other']

export const statusColor = status => {
  const map = {
    available: 'success',
    occupied: 'warning',
    unavailable: 'secondary',
    maintenance: 'info',
    reserved: 'primary',
    cleaning: 'info',
    pending: 'warning',
    accepted: 'success',
    declined: 'error',
    in_transit: 'info',
    completed: 'success',
    cancelled: 'secondary',
    fulfilled: 'success',
    on_trip: 'info',
    dispatched: 'primary',
    en_route: 'info',
    arrived: 'info',
    issued: 'info',
    paid: 'success',
    draft: 'secondary',
    waiting: 'warning',
    in_progress: 'info',
    active: 'success',
    discharged: 'secondary',
    requested: 'warning',
    collected: 'info',
    scheduled: 'info',
    processing: 'primary',
    verified: 'info',
    dispensed: 'success',
    transferred: 'primary',
    admitted: 'warning',
    more_info: 'info',
    busy: 'warning',
    away: 'secondary',
    archived: 'secondary',
    inactive: 'secondary',
    suspended: 'error',
    system: 'info',
  }

  return map[status] || 'secondary'
}

export const labelize = value => {
  if (!value)
    return ''

  return String(value).replaceAll('_', ' ').replaceAll('-', ' ')
}
