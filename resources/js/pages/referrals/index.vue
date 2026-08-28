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
  { title: 'Actions', key: 'actions', sortable: false },
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

await withPageLoad(load)
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Patient referrals</VCardTitle>
      <template #append>
        <VBtn
          v-if="ability.can('create', 'Referral')"
          prepend-icon="tabler-plus"
          :to="{ name: 'referrals-create' }"
        >
          New referral
        </VBtn>
      </template>
    </VCardItem>
    <VCardText>
      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <VBtnToggle
            v-model="direction"
            mandatory
            divided
            @update:model-value="load"
          >
            <VBtn value="all">
              All
            </VBtn>
            <VBtn value="incoming">
              Incoming
            </VBtn>
            <VBtn value="outgoing">
              Outgoing
            </VBtn>
          </VBtnToggle>
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="status"
            :items="[{ value: null, title: 'All statuses' }, ...referralStatuses.map(item => ({ value: item, title: labelize(item) }))]"
            item-title="title"
            item-value="value"
            label="Status"
            @update:model-value="load"
          />
        </VCol>
      </VRow>
    </VCardText>
    <VDataTable
      :headers="headers"
      :items="referrals"
    >
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
          size="small"
          variant="tonal"
          @click="selected = item"
        >
          Manage
        </VBtn>
      </template>
    </VDataTable>
  </VCard>

  <VDialog
    :model-value="Boolean(selected)"
    max-width="640"
    @update:model-value="val => { if (!val) selected = null }"
  >
    <VCard v-if="selected">
      <VCardItem>
        <VCardTitle>{{ selected.patient_name }}</VCardTitle>
        <VCardSubtitle>{{ selected.from_hospital?.name }} → {{ selected.to_hospital?.name }}</VCardSubtitle>
      </VCardItem>
      <VCardText>
        <p>{{ selected.reason }}</p>
        <p class="mb-0">
          Need: {{ selected.required_facility_type?.name }} · {{ selected.required_capacity }}
        </p>
        <AppTextarea
          v-model="responseNotes"
          class="mt-4"
          label="Notes"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          v-if="isOrigin(selected) && selected.status === 'pending'"
          color="secondary"
          @click="updateStatus('cancelled')"
        >
          Cancel
        </VBtn>
        <VBtn
          v-if="isDestination(selected) && selected.status === 'pending' && ability.can('respond', 'Referral')"
          color="error"
          @click="updateStatus('declined')"
        >
          Decline
        </VBtn>
        <VBtn
          v-if="isDestination(selected) && selected.status === 'pending' && ability.can('respond', 'Referral')"
          color="success"
          @click="updateStatus('accepted')"
        >
          Accept
        </VBtn>
        <VBtn
          v-if="selected.status === 'accepted'"
          @click="updateStatus('in_transit')"
        >
          Mark in transit
        </VBtn>
        <VBtn
          v-if="['accepted', 'in_transit'].includes(selected.status)"
          color="success"
          @click="updateStatus('completed')"
        >
          Complete
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
