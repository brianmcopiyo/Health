<script setup>
definePage({
  meta: {
    action: 'read',
    subject: 'User',
  },
})

const ability = useAbility()
const userData = useCookie('userData')

const cards = computed(() => {
  const items = [
    { title: 'Users', to: 'admin-users', icon: 'users', subject: 'User', text: 'Assign hospital access and roles' },
    { title: 'Roles', to: 'admin-roles', icon: 'shield', subject: 'Role', text: 'Configure permissions and workspaces' },
    { title: 'Departments', to: 'admin-departments', icon: 'building', subject: 'Department', text: 'Map departments to modules' },
    { title: 'Facilities', to: 'facilities', icon: 'hospital', subject: 'Facility', text: 'Capacity and unit configuration' },
    { title: 'Reports', to: 'reports', icon: 'chart', subject: 'Report', text: 'Operational utilization and activity' },
    { title: 'Hospitals', to: 'admin-hospitals', icon: 'community', subject: 'Hospital', text: 'Network hospital registry' },
  ]

  return items.filter(card => ability.can('read', card.subject) || ability.can('manage', card.subject))
})
</script>

<template>
  <div>
    <HPage
      title="Administration"
      :subtitle="userData?.hospitalName || 'Network'"
    />

    <div class="h-grid cols-3">
      <RouterLink
        v-for="card in cards"
        :key="card.to"
        :to="{ name: card.to }"
        class="h-card h-link-card"
      >
        <div class="h-icon-bubble">
          <HIcon :name="card.icon" />
        </div>
        <div>
          <strong>{{ card.title }}</strong>
          <div style="color:var(--muted)">
            {{ card.text }}
          </div>
        </div>
      </RouterLink>
    </div>
  </div>
</template>
