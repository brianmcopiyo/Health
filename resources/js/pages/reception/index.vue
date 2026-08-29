<script setup>
import EncounterChart from '@/components/hms/EncounterChart.vue'
import { bloodGroups, sexOptions, visitTypeOptions } from '@/utils/clinicalOptions'
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
const patientOpen = ref(false)
const visitOpen = ref(false)
const chartOpen = ref(false)
const encounterId = ref(null)
const saving = ref(false)
const formError = ref('')
const patientForm = ref({
  first_name: '',
  last_name: '',
  sex: null,
  phone: '',
  next_of_kin_name: '',
  next_of_kin_phone: '',
  blood_group: '',
})
const visitForm = ref({
  patient_id: null,
  type: 'opd',
  chief_complaint: '',
  clinician_id: null,
})

const load = async () => {
  patients.value = asList(await $api('/patients', { query: compactListQuery() }))
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

const openPatient = () => {
  formError.value = ''
  patientForm.value = { first_name: '', last_name: '', sex: null, phone: '', next_of_kin_name: '', next_of_kin_phone: '', blood_group: '' }
  patientOpen.value = true
}

const openVisit = () => {
  formError.value = ''
  visitForm.value = { patient_id: null, type: 'opd', chief_complaint: '', clinician_id: null }
  visitOpen.value = true
}

const registerPatient = async () => {
  await wrapSave(saving, formError, async () => {
    const patient = await $api('/patients', { method: 'POST', body: patientForm.value })
    patientOpen.value = false
    visitForm.value = { patient_id: patient.id, type: 'opd', chief_complaint: '', clinician_id: null }
    await load()
    visitOpen.value = true
  })
}

const createVisit = async () => {
  await wrapSave(saving, formError, async () => {
    const visit = await $api('/encounters', { method: 'POST', body: visitForm.value })
    visitOpen.value = false
    await load()
    encounterId.value = visit.id
    chartOpen.value = true
  })
}

const openChart = id => {
  encounterId.value = id
  chartOpen.value = true
}

await withPageLoad(load)
</script>

<template>
  <div>
    <HPage
      title="Reception"
      subtitle="Register patients and open OPD or emergency visits"
    >
      <HButton
        v-if="ability.can('create', 'Patient')"
        variant="ghost"
        @click="openPatient"
      >
        <HIcon name="plus" />
        Register patient
      </HButton>
      <HButton
        v-if="ability.can('create', 'Opd') || ability.can('create', 'Emergency')"
        @click="openVisit"
      >
        Open visit
      </HButton>
    </HPage>

    <HCard
      title="Today's visits"
      flush
    >
      <HTable
        :headers="[
          { title: 'Patient', key: 'patient.first_name' },
          { title: 'Type', key: 'type' },
          { title: 'Complaint', key: 'chief_complaint' },
          { title: 'Status', key: 'status' },
          { title: 'Actions', key: 'actions' },
        ]"
        :items="encounters"
        empty="No visits opened yet"
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
            variant="ghost"
            @click="openChart(item.id)"
          >
            Open chart
          </HButton>
        </template>
      </HTable>
    </HCard>

    <HModal
      v-model="patientOpen"
      title="Register patient"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
        class="h-stack"
        :disabled="saving"
      >
        <HInput
          v-model="patientForm.first_name"
          label="First name"
          required
        />
        <HInput
          v-model="patientForm.last_name"
          label="Last name"
          required
        />
        <HRadioGroup
          v-model="patientForm.sex"
          :items="sexOptions"
          label="Sex"
        />
        <HInput
          v-model="patientForm.phone"
          label="Phone"
          type="tel"
          icon="phone"
        />
        <HCombobox
          v-model="patientForm.blood_group"
          :items="bloodGroups"
          label="Blood group"
        />
        <HInput
          v-model="patientForm.next_of_kin_name"
          label="Next of kin"
        />
        <HInput
          v-model="patientForm.next_of_kin_phone"
          label="Next of kin phone"
          type="tel"
        />
      </fieldset>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="patientOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving || !patientForm.first_name || !patientForm.last_name"
          @click="registerPatient"
        >
          Save patient
        </HButton>
      </template>
    </HModal>

    <HModal
      v-model="visitOpen"
      title="Open visit"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
        class="h-stack"
        :disabled="saving"
      >
        <HSelect
          v-model="visitForm.patient_id"
          :items="patients"
          item-title="full_name"
          item-value="id"
          label="Patient"
          required
        />
        <HRadioGroup
          v-model="visitForm.type"
          :items="visitTypeOptions"
          label="Visit type"
        />
        <HInput
          v-model="visitForm.chief_complaint"
          label="Chief complaint"
          placeholder="Why is the patient here?"
        />
        <HSelect
          v-model="visitForm.clinician_id"
          :items="staff"
          item-title="name"
          item-value="id"
          label="Clinician"
        />
      </fieldset>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="visitOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving || !visitForm.patient_id"
          @click="createVisit"
        >
          Open visit
        </HButton>
      </template>
    </HModal>

    <EncounterChart
      v-model="chartOpen"
      :encounter-id="encounterId"
      @saved="load"
    />
  </div>
</template>
