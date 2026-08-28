<script setup>
import FacilityBoard from '@/components/hms/FacilityBoard.vue'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Emergency',
  },
})

const ability = useAbility()
const board = ref(null)
const patients = ref([])
const staff = ref([])
const form = ref({
  patient_id: null,
  chief_complaint: '',
  clinician_id: null,
})

const encounters = computed(() => asList(board.value?.encounters))

const load = async () => {
  board.value = await $api('/modules/emergency')
  if (ability.can('read', 'Patient'))
    patients.value = asList(await $api('/patients'))
  staff.value = asList(await $api('/users/directory'))
}

const startVisit = async () => {
  await $api('/encounters', {
    method: 'POST',
    body: {
      type: 'emergency',
      ...form.value,
    },
  })
  form.value = { patient_id: null, chief_complaint: '', clinician_id: null }
  await load()
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
      module-key="emergency"
      title="Emergency"
      subject="Emergency"
    />

    <VCard class="mt-6">
      <VCardItem>
        <VCardTitle>Emergency queue</VCardTitle>
      </VCardItem>
      <VCardText v-if="ability.can('create', 'Emergency')">
        <VRow>
          <VCol md="4">
            <AppSelect
              v-model="form.patient_id"
              :items="patients"
              item-title="full_name"
              item-value="id"
              label="Patient"
            />
          </VCol>
          <VCol md="4">
            <AppTextField
              v-model="form.chief_complaint"
              label="Chief complaint"
            />
          </VCol>
          <VCol md="4">
            <VBtn
              :disabled="!form.patient_id"
              @click="startVisit"
            >
              Register visit
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
      <VDataTable
        :headers="[
          { title: 'Patient', key: 'patient.first_name' },
          { title: 'Complaint', key: 'chief_complaint' },
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
            v-if="ability.can('update', 'Emergency') && item.status === 'waiting'"
            size="small"
            class="me-2"
            @click="updateVisit(item, 'in_progress')"
          >
            Start
          </VBtn>
          <VBtn
            v-if="ability.can('update', 'Emergency') && item.status !== 'completed'"
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
