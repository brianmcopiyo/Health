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

    <HGrid
      v-if="stats"
      cols="4"
      kind="stats"
    >
      <HStat
        icon="hospital"
        title="Remaining capacity"
        :value="Math.max(0, (stats.facilities?.capacity || 0) - (stats.facilities?.utilization || 0))"
        hint="Beds and units still open"
      />
      <HStat
        icon="chart"
        title="Utilization"
        :value="utilization"
        hint="Of rated facility capacity"
        :tone="Number.parseInt(utilization, 10) >= 80 ? 'warn' : ''"
      />
      <HStat
        icon="stethoscope"
        title="Open encounters"
        :value="(stats.encounters?.waiting || 0) + (stats.encounters?.in_progress || 0)"
        hint="Waiting and in progress"
      />
      <HStat
        icon="transfer"
        title="Pending referrals"
        :value="stats.referrals?.incoming || 0"
        hint="Incoming transfers"
        :tone="stats.referrals?.incoming ? 'warn' : ''"
      />
    </HGrid>

    <HGrid
      v-if="workspace"
      cols="4"
      kind="stats"
    >
      <HStat
        icon="stethoscope"
        title="My open encounters"
        :value="workspace.my_encounters?.length || 0"
        hint="Assigned to you"
      />
      <HStat
        icon="flask"
        title="Lab queue"
        :value="workspace.lab_orders?.length || 0"
        hint="Orders awaiting results"
      />
      <HStat
        icon="pill"
        title="Pharmacy queue"
        :value="workspace.prescriptions?.length || 0"
        hint="Prescriptions to dispense"
      />
    </HGrid>

    <HGrid
      cols="3"
      kind="links"
    >
      <HLinkCard
        v-for="card in cards"
        :key="card.to"
        :title="card.title"
        :text="card.text"
        :icon="card.icon"
        :to="card.to"
      />
    </HGrid>
  </div>
</template>
