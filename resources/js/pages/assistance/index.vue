<script setup>
import { assistanceTypes, labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'AssistanceRequest',
  },
})

const ability = useAbility()
const userData = useCookie('userData')
const items = ref([])
const hospitals = ref([])
const patients = ref([])
const encounters = ref([])
const types = ref([])
const direction = ref('all')
const isCreateVisible = ref(false)
const selected = ref(null)
const saving = ref(false)
const formError = ref('')
const responseNotes = ref('')
const form = ref({
  to_hospital_id: null,
  type: 'staff',
  title: '',
  description: '',
  patient_id: null,
  encounter_id: null,
  facility_type_id: null,
  quantity: 1,
})

const encounterOptions = computed(() => encounters.value.map(item => ({
  title: `${labelize(item.type)} · ${item.chief_complaint || labelize(item.status)}`,
  value: item.id,
})))

const headers = [
  { title: 'Request', key: 'title' },
  { title: 'Patient', key: 'patient.full_name' },
  { title: 'Type', key: 'type' },
  { title: 'From', key: 'from_hospital.name' },
  { title: 'To', key: 'to_hospital.name' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions' },
]

const load = async () => {
  const query = direction.value === 'all' ? {} : { direction: direction.value }
  items.value = asList(await $api('/assistance-requests', { query }))
}

const openCreate = async () => {
  formError.value = ''
  hospitals.value = asList(await $api('/network/hospitals'))
  patients.value = asList(await $api('/patients', { query: compactListQuery() }).catch(() => []))
  types.value = asList(await $api('/facility-types').catch(() => []))
  encounters.value = []
  form.value = {
    to_hospital_id: hospitals.value[0]?.id ?? null,
    type: 'staff',
    title: '',
    description: '',
    patient_id: null,
    encounter_id: null,
    facility_type_id: null,
    quantity: 1,
  }
  isCreateVisible.value = true
}

const onAssistancePatient = async id => {
  form.value.encounter_id = null
  if (!id) {
    encounters.value = []
    return
  }
  encounters.value = asList(await $api('/encounters', { query: { patient_id: id } }).catch(() => []))
}

const create = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/assistance-requests', { method: 'POST', body: form.value })
    isCreateVisible.value = false
    await load()
  })
}

const updateStatus = async status => {
  await wrapSave(saving, formError, async () => {
    await $api(`/assistance-requests/${selected.value.id}/status`, {
      method: 'PATCH',
      body: {
        status,
        response_notes: responseNotes.value,
      },
    })
    selected.value = null
    await load()
  })
}

const setDirection = value => {
  direction.value = value
  load()
}

await withPageLoad(load)
</script>

<template>
  <div>
    <HPage
      title="Inter-hospital assistance"
      subtitle="Staff, beds, equipment, and supply requests"
    >
      <HButton
        v-if="ability.can('create', 'AssistanceRequest')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Request support
      </HButton>
    </HPage>

    <HCard flush>
      <HToolbar>
        <HSegmented
          :model-value="direction"
          :options="[
            { value: 'all', title: 'All' },
            { value: 'incoming', title: 'Incoming' },
            { value: 'outgoing', title: 'Outgoing' },
          ]"
          @update:model-value="setDirection"
        />
      </HToolbar>
      <HTable
        :headers="headers"
        :items="items"
        empty="No assistance requests yet"
      >
        <template #cell-patient.full_name="{ item }">
          <RouterLink
            v-if="item.patient?.id"
            class="h-inline-link"
            :to="{ name: 'patients-id', params: { id: item.patient.id } }"
          >
            {{ item.patient.full_name }}
          </RouterLink>
          <span v-else>—</span>
        </template>
        <template #cell-type="{ item }">
          {{ labelize(item.type) }}
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <div class="h-actions">
            <HButton
              variant="ghost"
              size="icon"
              :to="{ name: 'assistance-id', params: { id: item.id } }"
            >
              <HIcon name="eye" />
            </HButton>
            <HButton
              variant="ghost"
              size="sm"
              @click="formError = ''; selected = item"
            >
              Manage
            </HButton>
          </div>
        </template>
      </HTable>
    </HCard>

    <HModal
      v-model="isCreateVisible"
      title="Request assistance"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
        class="h-stack"
        :disabled="saving"
      >
        <HSelect
          v-model="form.to_hospital_id"
          :items="hospitals"
          item-title="name"
          item-value="id"
          label="Destination hospital"
          required
        />
        <HSelect
          v-model="form.type"
          :items="assistanceTypes"
          label="Type"
          required
        />
        <HSelect
          v-model="form.facility_type_id"
          :items="types"
          item-title="name"
          item-value="id"
          label="Requested resource"
        />
        <HNumber
          v-model="form.quantity"
          label="Quantity"
          :min="1"
        />
        <HSelect
          v-model="form.patient_id"
          :items="patients"
          item-title="full_name"
          item-value="id"
          label="Patient"
          optional
          @update:model-value="onAssistancePatient"
        />
        <HSelect
          v-if="encounterOptions.length"
          v-model="form.encounter_id"
          :items="encounterOptions"
          label="Encounter"
        />
        <HInput
          v-model="form.title"
          label="Title"
          required
        />
        <HTextarea
          v-model="form.description"
          label="Details"
        />
      </fieldset>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="isCreateVisible = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving"
          @click="create"
        >
          Submit
        </HButton>
      </template>
    </HModal>

    <HModal
      :model-value="Boolean(selected)"
      :title="selected?.title || 'Assistance'"
      :error="formError"
      :persistent="saving"
      @update:model-value="val => { if (!val) selected = null }"
    >
      <div v-if="selected" class="h-stack">
        <p class="h-muted">
          {{ selected.from_hospital?.name }} → {{ selected.to_hospital?.name }}
        </p>
        <p>{{ selected.description }}</p>
        <p v-if="selected.patient">
          Patient: {{ selected.patient.full_name }}
        </p>
        <HTextarea
          v-model="responseNotes"
          label="Response notes"
        />
      </div>
      <template #actions>
        <HButton
          v-if="selected && userData?.hospitalId === selected.from_hospital_id && selected.status === 'pending'"
          variant="ghost"
          :disabled="saving"
          @click="updateStatus('cancelled')"
        >
          Cancel
        </HButton>
        <HButton
          v-if="selected && userData?.hospitalId === selected.to_hospital_id && selected.status === 'pending' && ability.can('respond', 'AssistanceRequest')"
          variant="danger"
          :disabled="saving"
          @click="updateStatus('declined')"
        >
          Decline
        </HButton>
        <HButton
          v-if="selected && userData?.hospitalId === selected.to_hospital_id && selected.status === 'pending' && ability.can('respond', 'AssistanceRequest')"
          variant="ok"
          :disabled="saving"
          @click="updateStatus('accepted')"
        >
          Accept
        </HButton>
        <HButton
          v-if="selected && userData?.hospitalId === selected.to_hospital_id && selected.status === 'accepted'"
          :disabled="saving"
          @click="updateStatus('fulfilled')"
        >
          Mark fulfilled
        </HButton>
      </template>
    </HModal>
  </div>
</template>
