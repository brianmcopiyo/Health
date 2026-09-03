<script setup>
import EncounterChart from '@/components/hms/EncounterChart.vue'
import FacilityBoard from '@/components/hms/FacilityBoard.vue'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Opd',
  },
})

const ability = useAbility()
const encounters = ref([])
const mine = ref([])
const encounterId = ref(null)
const chartOpen = ref(false)

const load = async () => {
  encounters.value = asList(await $api('/encounters', { query: { type: 'opd', open: true } }))
  const workspace = await $api('/workspace')
  mine.value = asList(workspace?.my_encounters)
}

const openChart = id => {
  encounterId.value = id
  chartOpen.value = true
}

const { pending, run } = usePageQuery(load)
const mineHeaders = [
  { title: 'Patient', key: 'patient.first_name', fill: true },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions' },
]
const queueHeaders = [
  { title: 'Patient', key: 'patient.first_name', fill: true },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions' },
]

let timer
onMounted(() => {
  timer = setInterval(() => { run({ silent: true }) }, 15000)
})
onBeforeUnmount(() => {
  if (timer)
    clearInterval(timer)
})
</script>

<template>
  <div>
    <FacilityBoard
      module-key="opd"
      title="OPD / Consultation"
      subject="Opd"
    />

    <HCard
      title="My open encounters"
      flush
    >
      <HTable
        :loading="pending"
        :headers="mineHeaders"
        :items="mine"
        empty="No patients currently assigned to you"
      >
        <template #cell-patient.first_name="{ item }">
          <HCell
            :to="item.patient?.id ? { name: 'patients-id', params: { id: item.patient.id } } : null"
            :secondary="joinContext(labelize(item.type), item.chief_complaint)"
          >
            {{ item.patient?.full_name || `${item.patient?.first_name || ''} ${item.patient?.last_name || ''}`.trim() || '—' }}
          </HCell>
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <HActionMenu
            :actions="[
              { label: 'Open chart', icon: 'stethoscope', onSelect: () => openChart(item.id) },
            ]"
          />
        </template>
      </HTable>
    </HCard>

    <HCard
      title="Consultation queue"
      flush
    >
      <HTable
        :loading="pending"
        :headers="queueHeaders"
        :items="encounters"
        empty="No patients waiting in OPD"
      >
        <template #cell-patient.first_name="{ item }">
          <HCell
            :to="item.patient?.id ? { name: 'patients-id', params: { id: item.patient.id } } : null"
            :secondary="joinContext(item.clinician?.name, item.chief_complaint)"
          >
            {{ item.patient?.full_name || `${item.patient?.first_name || ''} ${item.patient?.last_name || ''}`.trim() || '—' }}
          </HCell>
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <HActionMenu
            :actions="[
              { label: 'Open chart', icon: 'stethoscope', if: ability.can('update', 'Opd'), onSelect: () => openChart(item.id) },
            ]"
          />
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
