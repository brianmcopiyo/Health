<script setup>
definePage({
  meta: {
    action: 'read',
    subject: 'User',
  },
})

const ability = useAbility()
const userData = useCookie('userData')
const stats = ref(null)
const workspace = ref(null)

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

const utilization = computed(() => {
  const capacity = stats.value?.facilities?.capacity
  if (!capacity)
    return '0%'

  return `${Math.round((stats.value.facilities.utilization / capacity) * 100)}%`
})

await withPageLoad(async () => {
  workspace.value = await $api('/workspace')
  if (ability.can('read', 'Report'))
    stats.value = await $api('/reports')
})
</script>

<template>
  <div>
    <HPage
      title="Administration"
      :subtitle="userData?.hospitalName || 'Network'"
    />

    <div
      v-if="stats"
      class="h-grid cols-4"
      style="margin-bottom:18px"
    >
      <HStat
        title="Remaining capacity"
        :value="Math.max(0, (stats.facilities?.capacity || 0) - (stats.facilities?.utilization || 0))"
      />
      <HStat
        title="Utilization"
        :value="utilization"
      />
      <HStat
        title="Open encounters"
        :value="(stats.encounters?.waiting || 0) + (stats.encounters?.in_progress || 0)"
      />
      <HStat
        title="Pending referrals"
        :value="stats.referrals?.incoming || 0"
      />
    </div>

    <HCard
      v-if="workspace"
      title="Operational activity"
      style="margin-bottom:18px"
    >
      <div class="h-grid cols-3">
        <div class="h-metric">
          <span>My open encounters</span>
          <strong>{{ workspace.my_encounters?.length || 0 }}</strong>
        </div>
        <div class="h-metric">
          <span>Lab queue</span>
          <strong>{{ workspace.lab_orders?.length || 0 }}</strong>
        </div>
        <div class="h-metric">
          <span>Pharmacy queue</span>
          <strong>{{ workspace.prescriptions?.length || 0 }}</strong>
        </div>
      </div>
    </HCard>

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
