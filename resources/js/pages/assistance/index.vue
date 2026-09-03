<script setup>
import { assistanceStatuses, assistanceTypes, labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'AssistanceRequest',
  },
})

const ability = useAbility()
const userData = useCookie('userData')
const items = ref([])
const meta = ref(asPageMeta())
const hospitals = ref([])
const patients = ref([])
const encounters = ref([])
const types = ref([])
const list = useListQuery(['direction', 'status', 'type'])
const { page, q, filterValues } = list
if (!list.values.direction)
  list.values.direction = 'all'
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
  { title: 'Request', key: 'title', fill: true },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions' },
]

const assistanceQuery = extra => {
  const query = list.apiQuery(extra)
  if (query.direction === 'all')
    delete query.direction
  return query
}

const load = async () => {
  const payload = await $api('/assistance-requests', { query: assistanceQuery() })
  items.value = asList(payload)
  meta.value = asPageMeta(payload)
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

list.sync(load)
const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Inter-hospital assistance"
      subtitle="Staff, beds, equipment, and supply requests"
    >
      <HExportActions
        dataset="assistance"
        :query="assistanceQuery()"
      />
      <HButton
        v-if="ability.can('create', 'AssistanceRequest')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Request support
      </HButton>
    </HPage>

    <HCard flush>
      <HListToolbar
        v-model:search="q"
        v-model:values="filterValues"
        search-placeholder="Search requests"
        search-button
        :result-count="list.resultCount(meta)"
        :filters="[
          { key: 'direction', type: 'segmented', empty: 'all', options: [
            { value: 'all', title: 'All' },
            { value: 'incoming', title: 'Incoming' },
            { value: 'outgoing', title: 'Outgoing' },
          ] },
          { key: 'status', type: 'select', label: 'Status', placeholder: 'All statuses', optional: true, empty: null, items: assistanceStatuses.map(value => ({ title: labelize(value), value })) },
          { key: 'type', type: 'select', label: 'Type', placeholder: 'All types', optional: true, empty: null, more: true, items: assistanceTypes.map(value => ({ title: labelize(value), value })) },
        ]"
        @search="list.onSearch(load)"
        @change="list.onChange(load)"
      />
      <HTable
        :loading="pending"
        :headers="headers"
        :items="items"
        empty="No assistance requests yet"
      >
        <template #cell-title="{ item }">
          <HCell
            :to="{ name: 'assistance-id', params: { id: item.id } }"
            :secondary="joinContext(labelize(item.type), item.patient?.full_name, item.from_hospital?.name, item.to_hospital?.name)"
          >
            {{ item.title }}
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
              { label: 'View', icon: 'eye', to: { name: 'assistance-id', params: { id: item.id } } },
              { label: 'Manage', icon: 'edit', onSelect: () => { formError = ''; selected = item } },
            ]"
          />
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => list.onPage(value, load)"
      />
    </HCard>

    <HModal
      v-model="isCreateVisible"
      title="Request assistance"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
        class="h-form-grid"
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
          placeholder="e.g. 1"
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
          span
          v-model="form.title"
          label="Title"
          placeholder="e.g. ICU bed needed"
          required
        />
        <HTextarea
          span
          v-model="form.description"
          label="Details"
          placeholder="What support is needed"
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
          :loading="saving"
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
          placeholder="How you are responding"
        />
      </div>
      <template #actions>
        <HButton
          v-if="selected && userData?.hospitalId === selected.from_hospital_id && selected.status === 'pending'"
          variant="ghost"
          :loading="saving"
          :disabled="saving"
          @click="updateStatus('cancelled')"
        >
          Cancel
        </HButton>
        <HButton
          v-if="selected && userData?.hospitalId === selected.to_hospital_id && selected.status === 'pending' && ability.can('respond', 'AssistanceRequest')"
          variant="danger"
          :loading="saving"
          :disabled="saving"
          @click="updateStatus('declined')"
        >
          Decline
        </HButton>
        <HButton
          v-if="selected && userData?.hospitalId === selected.to_hospital_id && selected.status === 'pending' && ability.can('respond', 'AssistanceRequest')"
          variant="ok"
          :loading="saving"
          :disabled="saving"
          @click="updateStatus('accepted')"
        >
          Accept
        </HButton>
        <HButton
          v-if="selected && userData?.hospitalId === selected.to_hospital_id && selected.status === 'accepted'"
          :loading="saving"
          :disabled="saving"
          @click="updateStatus('fulfilled')"
        >
          Mark fulfilled
        </HButton>
      </template>
    </HModal>
  </div>
</template>
