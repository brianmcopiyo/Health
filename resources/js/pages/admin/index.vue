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
    { title: 'Users', to: 'admin-users', icon: 'tabler-users', subject: 'User', text: 'Assign hospital access and roles' },
    { title: 'Roles', to: 'admin-roles', icon: 'tabler-shield', subject: 'Role', text: 'Configure permissions and workspaces' },
    { title: 'Departments', to: 'admin-departments', icon: 'tabler-building', subject: 'Department', text: 'Map departments to modules' },
    { title: 'Facilities', to: 'facilities', icon: 'tabler-building-hospital', subject: 'Facility', text: 'Capacity and unit configuration' },
    { title: 'Reports', to: 'reports', icon: 'tabler-chart-bar', subject: 'Report', text: 'Operational utilization and activity' },
    { title: 'Hospitals', to: 'admin-hospitals', icon: 'tabler-building-skyscraper', subject: 'Hospital', text: 'Network hospital registry' },
  ]

  return items.filter(card => ability.can('read', card.subject) || ability.can('manage', card.subject))
})
</script>

<template>
  <div>
    <div class="mb-6">
      <h4 class="text-h4">
        Administration
      </h4>
      <div class="text-body-1">
        {{ userData?.hospitalName || 'Network' }}
      </div>
    </div>

    <VRow>
      <VCol
        v-for="card in cards"
        :key="card.to"
        cols="12"
        md="4"
      >
        <VCard :to="{ name: card.to }">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar
              color="primary"
              variant="tonal"
            >
              <VIcon :icon="card.icon" />
            </VAvatar>
            <div>
              <div class="text-h6">
                {{ card.title }}
              </div>
              <div class="text-body-2">
                {{ card.text }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
