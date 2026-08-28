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
const status = ref(null)
const direction = ref('all')
const selected = ref(null)
const responseNotes = ref('')
const destinationFacilityId = ref(null)

const headers = [
  { title: 'Patient', key: 'patient_name' },
  { title: 'From', key: 'from_hospital.name' },
  { title: 'To', key: 'to_hospital.name' },
  { title: 'Need', key: 'required_facility_type.name' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions' },
]

const load = async () => {
  const query = {}
  if (status.value)
    query.status = status.value
  if (direction.value !== 'all')
    query.direction = direction.value

  referrals.value = asList(await $api('/referrals', { query }))
}

const isDestination = item => userData.value?.hospitalId === item.to_hospital_id
const isOrigin = item => userData.value?.hospitalId === item.from_hospital_id

const updateStatus = async nextStatus => {
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
      title="Patient referrals"
      subtitle="Incoming and outgoing hospital transfers"
    >
      <HButton
        v-if="ability.can('create', 'Referral')"
        :to="{ name: 'referrals-create' }"
      >
        <HIcon name="plus" />
        New referral
      </HButton>
    </HPage>

    <HCard>
      <div
        class="h-grid cols-2"
        style="margin-bottom:16px"
      >
        <HSegmented
          :model-value="direction"
          :options="[
            { value: 'all', title: 'All' },
            { value: 'incoming', title: 'Incoming' },
            { value: 'outgoing', title: 'Outgoing' },
          ]"
          @update:model-value="setDirection"
        />
        <HSelect
          v-model="status"
          :items="referralStatuses"
          label="Status"
          placeholder="All statuses"
          @update:model-value="load"
        />
      </div>
      <HTable
        :headers="headers"
        :items="referrals"
        empty="No referrals in this view"
      >
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <HButton
            variant="ghost"
            size="sm"
            @click="selected = item"
          >
            Manage
          </HButton>
        </template>
      </HTable>
    </HCard>

    <HDialog
      :model-value="Boolean(selected)"
      :title="selected?.patient_name || 'Referral'"
      @update:model-value="val => { if (!val) selected = null }"
    >
      <div v-if="selected">
        <p style="color:var(--muted);margin-top:0">
          {{ selected.from_hospital?.name }} → {{ selected.to_hospital?.name }}
        </p>
        <p>{{ selected.reason }}</p>
        <p>Need: {{ selected.required_facility_type?.name }} · {{ selected.required_capacity }}</p>
        <HTextarea
          v-model="responseNotes"
          label="Notes"
        />
      </div>
      <template #actions>
        <HButton
          v-if="selected && isOrigin(selected) && selected.status === 'pending'"
          variant="ghost"
          @click="updateStatus('cancelled')"
        >
          Cancel
        </HButton>
        <HButton
          v-if="selected && isDestination(selected) && selected.status === 'pending' && ability.can('respond', 'Referral')"
          variant="danger"
          @click="updateStatus('declined')"
        >
          Decline
        </HButton>
        <HButton
          v-if="selected && isDestination(selected) && selected.status === 'pending' && ability.can('respond', 'Referral')"
          variant="ok"
          @click="updateStatus('accepted')"
        >
          Accept
        </HButton>
        <HButton
          v-if="selected && selected.status === 'accepted'"
          @click="updateStatus('in_transit')"
        >
          Mark in transit
        </HButton>
        <HButton
          v-if="selected && ['accepted', 'in_transit'].includes(selected.status)"
          variant="ok"
          @click="updateStatus('completed')"
        >
          Complete
        </HButton>
      </template>
    </HDialog>
  </div>
</template>
