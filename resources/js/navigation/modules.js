export const navConfig = {
  workspaceSectionRank: 15,
  alwaysVisible: ['admin'],
  platformKeys: ['admin', 'reports', 'users', 'roles', 'hospitals'],
  sections: {
    home: { title: null, rank: 0 },
    care: { title: 'Care', rank: 10 },
    clinical: { title: 'Clinical', rank: 20 },
    inpatient: { title: 'Inpatient', rank: 30 },
    diagnostics: { title: 'Diagnostics', rank: 40 },
    pharmacy: { title: 'Pharmacy', rank: 50 },
    theatre: { title: 'Theatre', rank: 60 },
    network: { title: 'Network', rank: 70 },
    finance: { title: 'Finance', rank: 80 },
    admin: { title: 'Administration', rank: 90 },
  },
}

export const catalog = [
  { key: 'admin', title: 'Dashboard', icon: 'dashboard', to: 'admin', subject: null, section: 'home', rank: 1, heading: 'Workspace' },
  { key: 'patients', title: 'Patients', icon: 'users', to: 'patients', subject: 'Patient', section: 'care', rank: 1, heading: 'Care' },
  { key: 'reception', title: 'Reception', icon: 'desk', to: 'reception', subject: 'Reception', section: 'care', rank: 2, heading: 'Care' },
  { key: 'opd', title: 'OPD', icon: 'stethoscope', to: 'opd', subject: 'Opd', section: 'clinical', rank: 1, heading: 'Clinical' },
  { key: 'emergency', title: 'Emergency', icon: 'emergency', to: 'emergency', subject: 'Emergency', section: 'clinical', rank: 2, heading: 'Clinical' },
  { key: 'wards', title: 'Wards', icon: 'hospital', to: 'wards', subject: 'Ward', section: 'inpatient', rank: 1, heading: 'Inpatient' },
  { key: 'beds', title: 'Beds', icon: 'bed', to: 'beds', subject: 'Bed', section: 'inpatient', rank: 2, heading: 'Inpatient' },
  { key: 'laboratory', title: 'Laboratory', icon: 'flask', to: 'laboratory', subject: 'Laboratory', section: 'diagnostics', rank: 1, heading: 'Diagnostics' },
  { key: 'imaging', title: 'Imaging', icon: 'scan', to: 'imaging', subject: 'Imaging', section: 'diagnostics', rank: 2, heading: 'Diagnostics' },
  { key: 'pharmacy', title: 'Pharmacy', icon: 'pill', to: 'pharmacy', subject: 'Pharmacy', section: 'pharmacy', rank: 1, heading: 'Pharmacy' },
  { key: 'inventory', title: 'Inventory', icon: 'flask', to: 'inventory', subject: 'Inventory', section: 'pharmacy', rank: 2, heading: 'Pharmacy' },
  { key: 'theatre', title: 'Theatre', icon: 'cut', to: 'theatre', subject: 'Theatre', section: 'theatre', rank: 1, heading: 'Theatre' },
  { key: 'referrals', title: 'Referrals', icon: 'transfer', to: 'referrals', subject: 'Referral', section: 'network', rank: 1, heading: 'Network' },
  { key: 'assistance', title: 'Assistance', icon: 'heartbeat', to: 'assistance', subject: 'AssistanceRequest', section: 'network', rank: 2, heading: 'Network' },
  { key: 'ambulances', title: 'Ambulances', icon: 'ambulance', to: 'ambulances', subject: 'Ambulance', section: 'network', rank: 3, heading: 'Network' },
  { key: 'billing', title: 'Billing', icon: 'receipt', to: 'billing', subject: 'Invoice', section: 'finance', rank: 1, heading: 'Finance' },
  { key: 'pricing', title: 'Pricing', icon: 'tag', to: 'pricing', subject: 'PriceList', section: 'finance', rank: 2, heading: 'Finance' },
  { key: 'billing-reports', title: 'Sales reports', icon: 'chart', to: 'billing-reports', subject: 'Invoice', section: 'finance', rank: 3, heading: 'Finance' },
  { key: 'reports', title: 'Reports', icon: 'chart', to: 'reports', subject: 'Report', section: 'finance', rank: 4, heading: 'Finance' },
  { key: 'departments', title: 'Departments', icon: 'building', to: 'admin-departments', subject: 'Department', section: 'admin', rank: 1, heading: 'Administration' },
  { key: 'facilities', title: 'Facilities', icon: 'community', to: 'facilities', subject: 'Facility', section: 'admin', rank: 2, heading: 'Administration' },
  { key: 'users', title: 'Users', icon: 'users', to: 'admin-users', subject: 'User', section: 'admin', rank: 3, heading: 'Administration' },
  { key: 'roles', title: 'Roles', icon: 'shield', to: 'admin-roles', subject: 'Role', section: 'admin', rank: 4, heading: 'Administration' },
  { key: 'hospitals', title: 'Hospitals', icon: 'hospital', to: 'admin-hospitals', subject: 'Hospital', section: 'admin', rank: 5, heading: 'Administration' },
]

const canSee = (module, ability, userData) => {
  if (userData?.role === 'platform-admin')
    return navConfig.platformKeys.includes(module.key)

  if (navConfig.alwaysVisible.includes(module.key) || !module.subject)
    return true

  return ability.can('read', module.subject)
}

const sectionRank = (section, workspace) => {
  const meta = navConfig.sections[section] || { rank: 99 }
  const home = catalog.find(module => module.to === workspace || module.key === workspace)
  if (home?.section === section && section !== 'home' && section !== 'admin')
    return navConfig.workspaceSectionRank

  return meta.rank
}

export const buildNavigation = (ability, userData) => {
  const workspace = userData?.workspace
  const groups = new Map()

  catalog.filter(module => canSee(module, ability, userData)).forEach(module => {
    if (!groups.has(module.section)) {
      const meta = navConfig.sections[module.section] || { title: module.heading, rank: 99 }
      groups.set(module.section, {
        heading: meta.title,
        section: module.section,
        items: [],
      })
    }

    groups.get(module.section).items.push({
      title: module.title,
      icon: module.icon,
      to: module.to,
      action: 'read',
      subject: module.subject,
      rank: module.rank,
    })
  })

  return [...groups.values()]
    .filter(group => group.items.length)
    .sort((a, b) => sectionRank(a.section, workspace) - sectionRank(b.section, workspace) || a.section.localeCompare(b.section))
    .map(group => ({
      heading: group.heading,
      section: group.section,
      items: group.items.sort((a, b) => a.rank - b.rank),
    }))
}

export const pageMeta = name => {
  const key = String(name || '')
  const exact = catalog.find(module => module.to === key)
  if (exact)
    return exact

  const nested = catalog
    .filter(module => module.to !== 'admin' && key.startsWith(`${module.to}-`))
    .sort((a, b) => b.to.length - a.to.length)[0]

  if (nested)
    return nested

  if (key === 'account-profile')
    return { title: 'Profile', heading: 'Account' }

  if (key === 'account-security')
    return { title: 'Security', heading: 'Account' }

  if (key === 'not-authorized')
    return { title: 'Caregrid', heading: 'Help' }

  return { title: 'Caregrid', heading: 'Workspace' }
}
