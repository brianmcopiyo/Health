<script setup>
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
const staff = ref([])

const load = async () => {
  encounters.value = asList(await $api('/encounters', { query: { type: 'opd' } }))
  staff.value = asList(await $api('/users/directory'))
}

const updateVisit = async (encounter, status) => {
  await $api(`/encounters/${encounter.id}`, {
    method: 'PATCH',
    body: { status },
  })
  await load()
}

await withPageLoad(load)

let timer
onMounted(() => {
  timer = setInterval(() => { withPageLoad(load, { silent: true }) }, 15000)
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
      title="Consultation queue"
      style="margin-top:18px"
    >
      <HTable
        :headers="[
          { title: 'Patient', key: 'patient.first_name' },
          { title: 'Complaint', key: 'chief_complaint' },
          { title: 'Clinician', key: 'clinician.name' },
          { title: 'Status', key: 'status' },
          { title: 'Actions', key: 'actions' },
        ]"
        :items="encounters"
        empty="No patients waiting in OPD"
      >
        <template #cell-patient.first_name="{ item }">
          {{ item.patient?.first_name }} {{ item.patient?.last_name }}
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <div class="h-actions">
            <HButton
              v-if="ability.can('update', 'Opd') && item.status === 'waiting'"
              size="sm"
              @click="updateVisit(item, 'in_progress')"
            >
              Start consult
            </HButton>
            <HButton
              v-if="ability.can('update', 'Opd') && item.status !== 'completed'"
              variant="ghost"
              size="sm"
              @click="updateVisit(item, 'completed')"
            >
              Complete
            </HButton>
          </div>
        </template>
      </HTable>
    </HCard>
  </div>
</template>
