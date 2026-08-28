<script setup>
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Reception',
  },
})

const ability = useAbility()
const patients = ref([])
const encounters = ref([])
const staff = ref([])
const patientForm = ref({
  first_name: '',
  last_name: '',
  sex: null,
  phone: '',
})
const visitForm = ref({
  patient_id: null,
  type: 'opd',
  chief_complaint: '',
  clinician_id: null,
})

const load = async () => {
  patients.value = asList(await $api('/patients'))
  staff.value = asList(await $api('/users/directory'))
  const [opd, emergency] = await Promise.all([
    ability.can('create', 'Opd') || ability.can('read', 'Opd')
      ? $api('/encounters', { query: { type: 'opd' } }).catch(() => [])
      : Promise.resolve([]),
    ability.can('create', 'Emergency') || ability.can('read', 'Emergency')
      ? $api('/encounters', { query: { type: 'emergency' } }).catch(() => [])
      : Promise.resolve([]),
  ])
  encounters.value = [...asList(opd), ...asList(emergency)].sort((a, b) => b.id - a.id)
}

const registerPatient = async () => {
  const patient = await $api('/patients', { method: 'POST', body: patientForm.value })
  patientForm.value = { first_name: '', last_name: '', sex: null, phone: '' }
  visitForm.value.patient_id = patient.id
  await load()
}

const createVisit = async () => {
  await $api('/encounters', { method: 'POST', body: visitForm.value })
  visitForm.value = { patient_id: null, type: 'opd', chief_complaint: '', clinician_id: null }
  await load()
}

await withPageLoad(load)
</script>

<template>
  <div>
    <div class="mb-6">
      <h4 class="text-h4">
        Reception
      </h4>
      <div class="text-body-1">
        Register patients and open OPD or emergency visits
      </div>
    </div>

    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <VCard>
          <VCardItem>
            <VCardTitle>Register patient</VCardTitle>
          </VCardItem>
          <VCardText>
            <AppTextField
              v-model="patientForm.first_name"
              label="First name"
              class="mb-4"
            />
            <AppTextField
              v-model="patientForm.last_name"
              label="Last name"
              class="mb-4"
            />
            <AppSelect
              v-model="patientForm.sex"
              :items="['male', 'female', 'other']"
              label="Sex"
              class="mb-4"
            />
            <AppTextField
              v-model="patientForm.phone"
              label="Phone"
              class="mb-4"
            />
            <VBtn
              block
              :disabled="!patientForm.first_name || !patientForm.last_name"
              @click="registerPatient"
            >
              Save patient
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VCard>
          <VCardItem>
            <VCardTitle>Open visit</VCardTitle>
          </VCardItem>
          <VCardText>
            <AppSelect
              v-model="visitForm.patient_id"
              :items="patients"
              item-title="full_name"
              item-value="id"
              label="Patient"
              class="mb-4"
            />
            <AppSelect
              v-model="visitForm.type"
              :items="[
                { title: 'OPD', value: 'opd' },
                { title: 'Emergency', value: 'emergency' },
              ]"
              item-title="title"
              item-value="value"
              label="Visit type"
              class="mb-4"
            />
            <AppTextField
              v-model="visitForm.chief_complaint"
              label="Chief complaint"
              class="mb-4"
            />
            <AppSelect
              v-model="visitForm.clinician_id"
              :items="staff"
              item-title="name"
              item-value="id"
              label="Clinician"
              class="mb-4"
            />
            <VBtn
              block
              :disabled="!visitForm.patient_id"
              @click="createVisit"
            >
              Open visit
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="mt-6">
      <VCardItem>
        <VCardTitle>Today's visits</VCardTitle>
      </VCardItem>
      <VDataTable
        :headers="[
          { title: 'Patient', key: 'patient.first_name' },
          { title: 'Type', key: 'type' },
          { title: 'Complaint', key: 'chief_complaint' },
          { title: 'Status', key: 'status' },
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
      </VDataTable>
    </VCard>
  </div>
</template>
