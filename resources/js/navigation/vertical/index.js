const catalog = [
  { key: 'reception', title: 'Reception', icon: 'tabler-desk', to: 'reception', subject: 'Reception', heading: 'Front desk' },
  { key: 'patients', title: 'Patients', icon: 'tabler-users', to: 'patients', subject: 'Patient', heading: 'Front desk' },
  { key: 'opd', title: 'OPD', icon: 'tabler-stethoscope', to: 'opd', subject: 'Opd', heading: 'Clinical' },
  { key: 'emergency', title: 'Emergency', icon: 'tabler-emergency-bed', to: 'emergency', subject: 'Emergency', heading: 'Clinical' },
  { key: 'wards', title: 'Wards', icon: 'tabler-building-hospital', to: 'wards', subject: 'Ward', heading: 'Inpatient' },
  { key: 'beds', title: 'Beds', icon: 'tabler-bed', to: 'beds', subject: 'Bed', heading: 'Inpatient' },
  { key: 'theatre', title: 'Theatre', icon: 'tabler-cut', to: 'theatre', subject: 'Theatre', heading: 'Inpatient' },
  { key: 'laboratory', title: 'Laboratory', icon: 'tabler-test-pipe', to: 'laboratory', subject: 'Laboratory', heading: 'Diagnostics' },
  { key: 'imaging', title: 'Imaging', icon: 'tabler-scan', to: 'imaging', subject: 'Imaging', heading: 'Diagnostics' },
  { key: 'pharmacy', title: 'Pharmacy', icon: 'tabler-pill', to: 'pharmacy', subject: 'Pharmacy', heading: 'Pharmacy' },
  { key: 'referrals', title: 'Referrals', icon: 'tabler-transfer', to: 'referrals', subject: 'Referral', heading: 'Network' },
  { key: 'assistance', title: 'Assistance', icon: 'tabler-heartbeat', to: 'assistance', subject: 'AssistanceRequest', heading: 'Network' },
  { key: 'ambulances', title: 'Ambulances', icon: 'tabler-ambulance', to: 'ambulances', subject: 'Ambulance', heading: 'Network' },
  { key: 'billing', title: 'Billing', icon: 'tabler-receipt', to: 'billing', subject: 'Invoice', heading: 'Finance' },
  { key: 'reports', title: 'Reports', icon: 'tabler-chart-bar', to: 'reports', subject: 'Report', heading: 'Finance' },
  { key: 'admin', title: 'Overview', icon: 'tabler-layout-dashboard', to: 'admin', subject: 'User', heading: 'Administration' },
  { key: 'departments', title: 'Departments', icon: 'tabler-building', to: 'admin-departments', subject: 'Department', heading: 'Administration' },
  { key: 'facilities', title: 'Facilities', icon: 'tabler-building-community', to: 'facilities', subject: 'Facility', heading: 'Administration' },
  { key: 'users', title: 'Users', icon: 'tabler-user-cog', to: 'admin-users', subject: 'User', heading: 'Administration' },
  { key: 'roles', title: 'Roles', icon: 'tabler-shield', to: 'admin-roles', subject: 'Role', heading: 'Administration' },
  { key: 'hospitals', title: 'Hospitals', icon: 'tabler-building-skyscraper', to: 'admin-hospitals', subject: 'Hospital', heading: 'Administration' },
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
      icon: { icon: module.icon },
      to: module.to,
      action: 'read',
      subject: module.subject,
    })
  })

  return items
}
