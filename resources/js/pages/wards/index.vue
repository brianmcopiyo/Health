<script setup>
import EncounterChart from '@/components/hms/EncounterChart.vue'
import FacilityBoard from '@/components/hms/FacilityBoard.vue'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Ward',
  },
})

const assigned = ref([])
const encounterId = ref(null)
const chartOpen = ref(false)

const load = async () => {
  const workspace = await $api('/workspace')
  assigned.value = asList(workspace?.ward_patients)
}

const openChart = item => {
  encounterId.value = item.encounter_id || item.encounter?.id
  if (encounterId.value)
    chartOpen.value = true
}

await withPageLoad(load)
</script>

<template>
  <div>
    <FacilityBoard
      module-key="wards"
      title="Wards"
      subject="Ward"
    />

    <HCard
      title="Patients under my care"
      flush
    >
      <HTable
        :headers="[
          { title: 'Patient', key: 'patient.first_name' },
          { title: 'Bed', key: 'facility.name' },
          { title: 'Status', key: 'status' },
          { title: 'Actions', key: 'actions' },
        ]"
        :items="assigned"
        empty="No inpatients assigned to your ward or shift"
      >
        <template #cell-patient.first_name="{ item }">
          <RouterLink
            v-if="item.patient?.id"
            class="h-inline-link"
            :to="{ name: 'patients-id', params: { id: item.patient.id } }"
          >
            {{ item.patient.first_name }} {{ item.patient.last_name }}
          </RouterLink>
          <span v-else>—</span>
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <HButton
            size="sm"
            :disabled="!item.encounter_id && !item.encounter"
            @click="openChart(item)"
          >
            Open chart
          </HButton>
        </template>
      </HTable>
    </HCard>

    <EncounterChart
      v-model="chartOpen"
      :encounter-id="encounterId"
      @saved="load"
    />
  </div>
</template>
