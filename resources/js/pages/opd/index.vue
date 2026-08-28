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
  timer = setInterval(() => { withPageLoad(load) }, 15000)
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

    <VCard class="mt-6">
      <VCardItem>
        <VCardTitle>Consultation queue</VCardTitle>
      </VCardItem>
      <VDataTable
        :headers="[
          { title: 'Patient', key: 'patient.first_name' },
          { title: 'Complaint', key: 'chief_complaint' },
          { title: 'Clinician', key: 'clinician.name' },
          { title: 'Status', key: 'status' },
          { title: 'Actions', key: 'actions', sortable: false },
        ]"
        :items="encounters"
      >
        <template #item.patient.first_name="{ item }">
          {{ item.patient?.first_name }} {{ item.patient?.last_name }}
        </template>
        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="statusColor(item.status)"
            class="text-capitalize"
          >
            {{ labelize(item.status) }}
          </VChip>
        </template>
        <template #item.actions="{ item }">
          <VBtn
            v-if="ability.can('update', 'Opd') && item.status === 'waiting'"
            size="small"
            class="me-2"
            @click="updateVisit(item, 'in_progress')"
          >
            Start consult
          </VBtn>
          <VBtn
            v-if="ability.can('update', 'Opd') && item.status !== 'completed'"
            size="small"
            variant="tonal"
            @click="updateVisit(item, 'completed')"
          >
            Complete
          </VBtn>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
