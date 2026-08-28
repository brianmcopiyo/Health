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
    <HPage
      title="Reception"
      subtitle="Register patients and open OPD or emergency visits"
    />

    <div class="h-grid cols-2">
      <HCard title="Register patient">
        <div class="h-stack">
          <HInput
            v-model="patientForm.first_name"
            label="First name"
          />
          <HInput
            v-model="patientForm.last_name"
            label="Last name"
          />
          <HSelect
            v-model="patientForm.sex"
            :items="['male', 'female', 'other']"
            label="Sex"
          />
          <HInput
            v-model="patientForm.phone"
            label="Phone"
          />
          <HButton
            class="is-block"
            :disabled="!patientForm.first_name || !patientForm.last_name"
            @click="registerPatient"
          >
            Save patient
          </HButton>
        </div>
      </HCard>

      <HCard title="Open visit">
        <div class="h-stack">
          <HSelect
            v-model="visitForm.patient_id"
            :items="patients"
            item-title="full_name"
            item-value="id"
            label="Patient"
          />
          <HSelect
            v-model="visitForm.type"
            :items="[
              { title: 'OPD', value: 'opd' },
              { title: 'Emergency', value: 'emergency' },
            ]"
            item-title="title"
            item-value="value"
            label="Visit type"
          />
          <HInput
            v-model="visitForm.chief_complaint"
            label="Chief complaint"
          />
          <HSelect
            v-model="visitForm.clinician_id"
            :items="staff"
            item-title="name"
            item-value="id"
            label="Clinician"
          />
          <HButton
            class="is-block"
            :disabled="!visitForm.patient_id"
            @click="createVisit"
          >
            Open visit
          </HButton>
        </div>
      </HCard>
    </div>

    <HCard
      title="Today's visits"
      style="margin-top:18px"
    >
      <HTable
        :headers="[
          { title: 'Patient', key: 'patient.first_name' },
          { title: 'Type', key: 'type' },
          { title: 'Complaint', key: 'chief_complaint' },
          { title: 'Status', key: 'status' },
        ]"
        :items="encounters"
        empty="No visits opened yet"
      >
        <template #cell-patient.first_name="{ item }">
          {{ item.patient?.first_name }} {{ item.patient?.last_name }}
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
      </HTable>
    </HCard>
  </div>
</template>
