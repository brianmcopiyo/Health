<script setup>
import { labelize, referralStatuses, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Referral',
  },
})

const ability = useAbility()
const userData = useCookie('userData')
const referrals = ref([])
const meta = ref(asPageMeta())
const page = ref(1)
const status = ref(null)
const direction = ref('all')
const selected = ref(null)
const saving = ref(false)
const formError = ref('')
const responseNotes = ref('')
const destinationFacilityId = ref(null)
const facilities = ref([])

const headers = [
  { title: 'Patient', key: 'patient_name', fill: true },
  { title: 'Need', key: 'required_facility_type.name' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions' },
]

const load = async () => {
  const query = { page: page.value }
  if (status.value)
    query.status = status.value
  if (direction.value !== 'all')
    query.direction = direction.value

  const payload = await $api('/referrals', { query })
  referrals.value = asList(payload)
  meta.value = asPageMeta(payload)
}

const isDestination = item => userData.value?.hospitalId === item.to_hospital_id
const isOrigin = item => userData.value?.hospitalId === item.from_hospital_id

const openManage = async item => {
  formError.value = ''
  selected.value = item
  responseNotes.value = item.response_notes || ''
  destinationFacilityId.value = item.destination_facility_id || null
  facilities.value = []
  if (isDestination(item) && ['pending', 'more_info'].includes(item.status)) {
    const typeId = item.required_facility_type_id || item.required_facility_type?.id
    const payload = await $api('/facilities', { query: { facility_type_id: typeId, per_page: 50 } }).catch(() => [])
    facilities.value = asList(payload)
  }
}

const updateStatus = async nextStatus => {
  await wrapSave(saving, formError, async () => {
    await $api(`/referrals/${selected.value.id}/status`, {
      method: 'PATCH',
      body: {
        status: nextStatus,
        response_notes: responseNotes.value,
        destination_facility_id: destinationFacilityId.value,
      },
    })
    selected.value = null
    await load()
  })
}

const filterValues = computed({
  get: () => ({ direction: direction.value, status: status.value }),
  set: next => {
    direction.value = next.direction
    status.value = next.status
  },
})

const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Patient referrals"
      subtitle="Incoming and outgoing hospital transfers"
    >
      <HExportActions
        dataset="referrals"
        :query="{ status: status || undefined, direction: direction !== 'all' ? direction : undefined }"
        :disabled="pending"
      />
      <HButton
        v-if="ability.can('create', 'Referral')"
        :to="{ name: 'referrals-create' }"
      >
        <HIcon name="plus" />
        New referral
      </HButton>
    </HPage>

    <HCard flush>
      <HListToolbar
        v-model:values="filterValues"
        :filters="[
          { key: 'direction', type: 'segmented', empty: 'all', options: [
            { value: 'all', title: 'All' },
            { value: 'incoming', title: 'Incoming' },
            { value: 'outgoing', title: 'Outgoing' },
          ] },
          { key: 'status', type: 'select', label: 'Status', placeholder: 'All statuses', items: referralStatuses },
        ]"
        @change="load"
      />
      <HTable
        :loading="pending"
        :headers="headers"
        :items="referrals"
        empty="No referrals in this view"
      >
        <template #cell-patient_name="{ item }">
          <HCell
            :to="item.patient?.id ? { name: 'patients-id', params: { id: item.patient.id } } : null"
            :secondary="joinContext(item.from_hospital?.name, item.to_hospital?.name)"
          >
            {{ item.patient?.full_name || item.patient_name || '—' }}
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
              { label: 'View', icon: 'eye', to: { name: 'referrals-id', params: { id: item.id } } },
              { label: 'Manage', icon: 'edit', onSelect: () => openManage(item) },
            ]"
          />
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => { page = value; load() }"
      />
    </HCard>

    <HModal
      :model-value="Boolean(selected)"
      :title="selected?.patient_name || 'Referral'"
      :error="formError"
      :persistent="saving"
      @update:model-value="val => { if (!val) selected = null }"
    >
      <div v-if="selected" class="h-stack">
        <p class="h-muted">
          {{ selected.from_hospital?.name }} → {{ selected.to_hospital?.name }}
        </p>
        <p>{{ selected.patient?.full_name || selected.patient_name }} · {{ selected.patient_reference }}</p>
        <p v-if="selected.encounter">
          Encounter: {{ labelize(selected.encounter.type) }} · {{ selected.encounter.chief_complaint }}
        </p>
        <p>{{ selected.reason }}</p>
        <p>Need: {{ selected.required_facility_type?.name }} · {{ selected.required_capacity }}</p>
        <p v-if="selected.response_notes">
          Response: {{ selected.response_notes }}
        </p>
        <HSelect
          v-if="isDestination(selected) && ['pending', 'more_info'].includes(selected.status) && facilities.length"
          v-model="destinationFacilityId"
          :items="facilities"
          item-title="name"
          item-value="id"
          label="Receiving unit"
        />
        <HTextarea
          v-model="responseNotes"
          label="Notes"
          placeholder="Response or extra information"
        />
      </div>
      <template #actions>
        <HButton
          v-if="selected && isOrigin(selected) && selected.status === 'pending'"
          variant="ghost"
          :loading="saving"
          :disabled="saving"
          @click="updateStatus('cancelled')"
        >
          Cancel
        </HButton>
        <HButton
          v-if="selected && isDestination(selected) && selected.status === 'pending' && ability.can('respond', 'Referral')"
          variant="ghost"
          :loading="saving"
          :disabled="saving"
          @click="updateStatus('more_info')"
        >
          Request more information
        </HButton>
        <HButton
          v-if="selected && isDestination(selected) && ['pending', 'more_info'].includes(selected.status) && ability.can('respond', 'Referral')"
          variant="danger"
          :loading="saving"
          :disabled="saving"
          @click="updateStatus('declined')"
        >
          Decline
        </HButton>
        <HButton
          v-if="selected && isDestination(selected) && ['pending', 'more_info'].includes(selected.status) && ability.can('respond', 'Referral')"
          variant="ok"
          :loading="saving"
          :disabled="saving"
          @click="updateStatus('accepted')"
        >
          Accept
        </HButton>
        <HButton
          v-if="selected && selected.status === 'accepted'"
          :loading="saving"
          :disabled="saving"
          @click="updateStatus('in_transit')"
        >
          Mark in transit
        </HButton>
        <HButton
          v-if="selected && ['accepted', 'in_transit'].includes(selected.status)"
          variant="ok"
          :loading="saving"
          :disabled="saving"
          @click="updateStatus('completed')"
        >
          Complete
        </HButton>
      </template>
    </HModal>
  </div>
</template>
