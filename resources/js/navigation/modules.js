export const catalog = [
  { key: 'reception', title: 'Reception', icon: 'desk', to: 'reception', subject: 'Reception', heading: 'Front desk' },
  { key: 'patients', title: 'Patients', icon: 'users', to: 'patients', subject: 'Patient', heading: 'Front desk' },
  { key: 'opd', title: 'OPD', icon: 'stethoscope', to: 'opd', subject: 'Opd', heading: 'Clinical' },
  { key: 'emergency', title: 'Emergency', icon: 'emergency', to: 'emergency', subject: 'Emergency', heading: 'Clinical' },
  { key: 'wards', title: 'Wards', icon: 'hospital', to: 'wards', subject: 'Ward', heading: 'Inpatient' },
  { key: 'beds', title: 'Beds', icon: 'bed', to: 'beds', subject: 'Bed', heading: 'Inpatient' },
  { key: 'theatre', title: 'Theatre', icon: 'cut', to: 'theatre', subject: 'Theatre', heading: 'Inpatient' },
  { key: 'laboratory', title: 'Laboratory', icon: 'flask', to: 'laboratory', subject: 'Laboratory', heading: 'Diagnostics' },
  { key: 'imaging', title: 'Imaging', icon: 'scan', to: 'imaging', subject: 'Imaging', heading: 'Diagnostics' },
  { key: 'pharmacy', title: 'Pharmacy', icon: 'pill', to: 'pharmacy', subject: 'Pharmacy', heading: 'Pharmacy' },
  { key: 'referrals', title: 'Referrals', icon: 'transfer', to: 'referrals', subject: 'Referral', heading: 'Network' },
  { key: 'assistance', title: 'Assistance', icon: 'heartbeat', to: 'assistance', subject: 'AssistanceRequest', heading: 'Network' },
  { key: 'ambulances', title: 'Ambulances', icon: 'ambulance', to: 'ambulances', subject: 'Ambulance', heading: 'Network' },
  { key: 'billing', title: 'Billing', icon: 'receipt', to: 'billing', subject: 'Invoice', heading: 'Finance' },
  { key: 'reports', title: 'Reports', icon: 'chart', to: 'reports', subject: 'Report', heading: 'Finance' },
  { key: 'admin', title: 'Overview', icon: 'dashboard', to: 'admin', subject: 'User', heading: 'Administration' },
  { key: 'departments', title: 'Departments', icon: 'building', to: 'admin-departments', subject: 'Department', heading: 'Administration' },
  { key: 'facilities', title: 'Facilities', icon: 'community', to: 'facilities', subject: 'Facility', heading: 'Administration' },
  { key: 'users', title: 'Users', icon: 'users', to: 'admin-users', subject: 'User', heading: 'Administration' },
  { key: 'roles', title: 'Roles', icon: 'shield', to: 'admin-roles', subject: 'Role', heading: 'Administration' },
  { key: 'hospitals', title: 'Hospitals', icon: 'hospital', to: 'admin-hospitals', subject: 'Hospital', heading: 'Administration' },
]

export const buildNavigation = (ability, userData) => {
  const visible = userData?.role === 'platform-admin'
    ? catalog.filter(module => ['hospitals', 'users', 'roles', 'reports'].includes(module.key))
    : catalog.filter(module => ability.can('read', module.subject))

  const items = []
  let heading = null

  visible.forEach(module => {
    if (heading !== module.heading) {
      heading = module.heading
      items.push({ heading })
    }

    items.push({
      title: module.title,
      icon: module.icon,
      to: module.to,
      action: 'read',
      subject: module.subject,
    })
  })

  return items
}

export const pageMeta = name => {
  const exact = catalog.find(module => module.to === name)
  if (exact)
    return exact

  const prefixes = [
    ['facilities', 'Facilities'],
    ['ambulances', 'Ambulances'],
    ['referrals', 'Referrals'],
    ['admin', 'Administration'],
  ]
  const match = prefixes.find(([key]) => String(name || '').startsWith(key))

  return match ? { title: match[1], heading: 'Workspace' } : { title: 'Caregrid', heading: 'Workspace' }
}
