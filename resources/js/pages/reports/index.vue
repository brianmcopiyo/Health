<script setup>
import CardStatisticsHorizontal from '@core/components/cards/CardStatisticsHorizontal.vue'
import { statusColor } from '@/utils/status'

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

await withPageLoad(load)

const utilization = computed(() => {
  if (!stats.value.facilities.capacity)
    return '0%'

  return `${Math.round((stats.value.facilities.utilization / stats.value.facilities.capacity) * 100)}%`
})
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h4 class="text-h4">
          Reports
        </h4>
        <div class="text-body-1">
          {{ stats.hospital?.name }}
        </div>
      </div>
      <VBtn
        variant="tonal"
        prepend-icon="tabler-refresh"
        @click="load"
      >
        Refresh
      </VBtn>
    </div>

    <VRow>
      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <CardStatisticsHorizontal
          title="Available facilities"
          :stats="String(stats.facilities.available)"
          color="success"
          icon="tabler-building-hospital"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <CardStatisticsHorizontal
          title="Active patients"
          :stats="String(stats.patients.active)"
          color="primary"
          icon="tabler-users"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <CardStatisticsHorizontal
          title="Capacity used"
          :stats="utilization"
          color="warning"
          icon="tabler-chart-donut"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <CardStatisticsHorizontal
          title="Ambulances ready"
          :stats="String(stats.ambulances.available)"
          color="info"
          icon="tabler-ambulance"
        />
      </VCol>
    </VRow>

    <VRow>
      <VCol
        cols="12"
        md="4"
      >
        <VCard>
          <VCardItem>
            <VCardTitle>Referrals</VCardTitle>
          </VCardItem>
          <VCardText>
            <div class="d-flex justify-space-between mb-2">
              <span>Pending incoming</span>
              <VChip
                size="small"
                :color="statusColor('pending')"
              >
                {{ stats.referrals.incoming }}
              </VChip>
            </div>
            <div class="d-flex justify-space-between mb-2">
              <span>Accepted</span>
              <strong>{{ stats.referrals.accepted }}</strong>
            </div>
            <div class="d-flex justify-space-between">
              <span>In transit</span>
              <strong>{{ stats.referrals.in_transit }}</strong>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="4"
      >
        <VCard>
          <VCardItem>
            <VCardTitle>Assistance</VCardTitle>
          </VCardItem>
          <VCardText>
            <div class="d-flex justify-space-between mb-2">
              <span>Open requests</span>
              <VChip
                size="small"
                color="warning"
              >
                {{ stats.assistance.pending }}
              </VChip>
            </div>
            <div class="d-flex justify-space-between">
              <span>Accepted</span>
              <strong>{{ stats.assistance.accepted }}</strong>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="4"
      >
        <VCard>
          <VCardItem>
            <VCardTitle>Clinical activity</VCardTitle>
          </VCardItem>
          <VCardText>
            <div class="d-flex justify-space-between mb-2">
              <span>OPD waiting</span>
              <strong>{{ stats.encounters.opd }}</strong>
            </div>
            <div class="d-flex justify-space-between">
              <span>Emergency active</span>
              <strong>{{ stats.encounters.emergency }}</strong>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="mt-6">
      <VCardItem>
        <VCardTitle>Facility utilization by type</VCardTitle>
      </VCardItem>
      <VTable>
        <thead>
          <tr>
            <th>Type</th>
            <th>Units</th>
            <th>Capacity</th>
            <th>In use</th>
            <th>Remaining</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in stats.facilitiesByType"
            :key="row.facility_type_id"
          >
            <td>{{ row.type?.name }}</td>
            <td>{{ row.total }}</td>
            <td>{{ row.capacity }}</td>
            <td>{{ row.utilization }}</td>
            <td>{{ Math.max(0, row.capacity - row.utilization) }}</td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </div>
</template>
