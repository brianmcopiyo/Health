export const accountStatuses = ['active', 'inactive', 'suspended']

export const accountStatusItems = [
  { title: 'Active', value: 'active' },
  { title: 'Inactive', value: 'inactive' },
  { title: 'Suspended', value: 'suspended' },
]

export const userSortItems = [
  { title: 'Name', value: 'name' },
  { title: 'Email', value: 'email' },
  { title: 'Status', value: 'status' },
  { title: 'Created', value: 'created_at' },
  { title: 'Last login', value: 'last_login_at' },
]

export const sortDirItems = [
  { title: 'A to Z', value: 'asc' },
  { title: 'Z to A', value: 'desc' },
]

export const bulkStatusItems = [
  { title: 'Set active', value: 'active' },
  { title: 'Set inactive', value: 'inactive' },
  { title: 'Set suspended', value: 'suspended' },
]

export const emptyUserForm = (defaults = {}) => ({
  name: '',
  email: '',
  password: '',
  role_id: null,
  phone: '',
  job_title: '',
  status: 'active',
  hospital_id: null,
  branch_id: null,
  ...defaults,
})

export const userFormFrom = (item, extra = {}) => ({
  name: item?.name || '',
  email: item?.email || '',
  password: '',
  role_id: item?.role_id ?? null,
  phone: item?.phone || '',
  job_title: item?.job_title || '',
  status: item?.status || 'active',
  hospital_id: item?.hospital_id ?? null,
  branch_id: item?.branch_id ?? null,
  ...extra,
})

export const userListQuery = (page, search, values) => {
  const query = { page }
  if (search)
    query.q = search
  if (values.status)
    query.status = values.status
  if (values.role_id)
    query.role_id = values.role_id
  if (values.sort)
    query.sort = values.sort
  if (values.sort_dir)
    query.sort_dir = values.sort_dir

  return query
}

export const userPayload = (form, editing, canAssignHospital) => {
  const payload = { ...form }
  if (editing) {
    if (!payload.password)
      delete payload.password
    delete payload.email
  }
  if (!canAssignHospital)
    delete payload.hospital_id
  if (!payload.branch_id)
    payload.branch_id = null

  return payload
}

export const groupPermissions = permissions => {
  const groups = {}
  permissions.forEach(permission => {
    const group = permission.group || 'General'
    groups[group] = groups[group] || []
    groups[group].push(permission)
  })

  return groups
}

export const canWriteUser = ability => ability.can('create', 'User') || ability.can('update', 'User')

export const canWriteRole = ability => ability.can('create', 'Role') || ability.can('update', 'Role')
