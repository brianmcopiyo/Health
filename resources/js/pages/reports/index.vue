<script setup>
definePage({
  meta: {
    action: 'read',
    subject: 'Report',
  },
})

const emptyStats = () => ({
  hospital: { name: '' },
  facilities: { available: 0, capacity: 0, utilization: 0 },
  patients: { active: 0 },
  ambulances: { available: 0 },
  referrals: { incoming: 0, accepted: 0, in_transit: 0 },
  assistance: { pending: 0, accepted: 0 },
  encounters: { opd: 0, emergency: 0 },
  facilitiesByType: [],
})

const stats = ref(emptyStats())

const load = async () => {
  const payload = await $api('/reports')
  stats.value = {
    ...emptyStats(),
    ...payload,
    facilities: { ...emptyStats().facilities, ...(payload?.facilities || {}) },
    patients: { ...emptyStats().patients, ...(payload?.patients || {}) },
    ambulances: { ...emptyStats().ambulances, ...(payload?.ambulances || {}) },
    referrals: { ...emptyStats().referrals, ...(payload?.referrals || {}) },
    assistance: { ...emptyStats().assistance, ...(payload?.assistance || {}) },
    encounters: { ...emptyStats().encounters, ...(payload?.encounters || {}) },
    facilitiesByType: asList(payload?.facilitiesByType),
  }
}

const { pending } = usePageQuery(load)
const showSkel = useDelayedVisible(pending)
const typeHeaders = [
  { title: 'Type', key: 'type.name' },
  { title: 'Units', key: 'total' },
  { title: 'Capacity', key: 'capacity' },
  { title: 'In use', key: 'utilization' },
  { title: 'Remaining', key: 'remaining' },
]

const utilization = computed(() => {
  if (!stats.value.facilities.capacity)
    return '0%'

  return `${Math.round((stats.value.facilities.utilization / stats.value.facilities.capacity) * 100)}%`
})
</script>

<template>
  <div>
    <HPage
      title="Reports"
      :subtitle="stats.hospital?.name || 'Operational utilization'"
    >
      <HButton
        variant="ghost"
        @click="load"
      >
        <HIcon name="refresh" />
        Refresh
      </HButton>
    </HPage>

    <HGrid
      cols="4"
      kind="stats"
    >
      <HStat
        icon="community"
        title="Available facilities"
        :value="stats.facilities.available"
        hint="Units ready to receive patients"
        :loading="pending"
      />
      <HStat
        icon="users"
        title="Active patients"
        :value="stats.patients.active"
        hint="Currently under care"
        :loading="pending"
      />
      <HStat
        icon="chart"
        title="Capacity used"
        :value="utilization"
        hint="Of rated facility capacity"
        :tone="Number.parseInt(utilization, 10) >= 80 ? 'warn' : ''"
        :loading="pending"
      />
      <HStat
        icon="ambulance"
        title="Ambulances ready"
        :value="stats.ambulances.available"
        hint="Vehicles available to dispatch"
        :loading="pending"
      />
    </HGrid>

    <HGrid cols="3">
      <HCard title="Referrals">
        <template v-if="pending">
          <div
            v-for="n in 3"
            :key="n"
            class="h-metric"
            :class="{ 'is-hold': !showSkel }"
          >
            <span class="h-skeleton is-label" />
            <strong class="h-skeleton is-value" />
          </div>
        </template>
        <template v-else>
          <div class="h-metric">
            <span>Pending incoming</span>
            <HBadge tone="warning">
              {{ stats.referrals.incoming }}
            </HBadge>
          </div>
          <div class="h-metric">
            <span>Accepted</span>
            <strong>{{ stats.referrals.accepted }}</strong>
          </div>
          <div class="h-metric">
            <span>In transit</span>
            <strong>{{ stats.referrals.in_transit }}</strong>
          </div>
        </template>
      </HCard>
      <HCard title="Assistance">
        <template v-if="pending">
          <div
            v-for="n in 2"
            :key="n"
            class="h-metric"
            :class="{ 'is-hold': !showSkel }"
          >
            <span class="h-skeleton is-label" />
            <strong class="h-skeleton is-value" />
          </div>
        </template>
        <template v-else>
          <div class="h-metric">
            <span>Open requests</span>
            <HBadge tone="warning">
              {{ stats.assistance.pending }}
            </HBadge>
          </div>
          <div class="h-metric">
            <span>Accepted</span>
            <strong>{{ stats.assistance.accepted }}</strong>
          </div>
        </template>
      </HCard>
      <HCard title="Clinical activity">
        <template v-if="pending">
          <div
            v-for="n in 2"
            :key="n"
            class="h-metric"
            :class="{ 'is-hold': !showSkel }"
          >
            <span class="h-skeleton is-label" />
            <strong class="h-skeleton is-value" />
          </div>
        </template>
        <template v-else>
          <div class="h-metric">
            <span>OPD waiting</span>
            <strong>{{ stats.encounters.opd }}</strong>
          </div>
          <div class="h-metric">
            <span>Emergency active</span>
            <strong>{{ stats.encounters.emergency }}</strong>
          </div>
        </template>
      </HCard>
    </HGrid>

    <HCard
      title="Facility utilization by type"
      flush
    >
      <HTable
        :loading="pending"
        :headers="typeHeaders"
        :items="stats.facilitiesByType.map(row => ({ ...row, remaining: Math.max(0, row.capacity - row.utilization) }))"
        empty="No facility utilization yet"
      />
    </HCard>
  </div>
</template>
