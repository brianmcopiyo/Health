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
const hospitals = ref([])
const direction = ref('all')
const isCreateVisible = ref(false)
const selected = ref(null)
const responseNotes = ref('')
const form = ref({
  to_hospital_id: null,
  type: 'staff',
  title: '',
  description: '',
})

const headers = [
  { title: 'Request', key: 'title' },
  { title: 'Type', key: 'type' },
  { title: 'From', key: 'from_hospital.name' },
  { title: 'To', key: 'to_hospital.name' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions', sortable: false },
]

const load = async () => {
  const query = direction.value === 'all' ? {} : { direction: direction.value }
  items.value = asList(await $api('/assistance-requests', { query }))
}

const openCreate = async () => {
  hospitals.value = asList(await $api('/network/hospitals'))
  form.value = {
    to_hospital_id: hospitals.value[0]?.id ?? null,
    type: 'staff',
    title: '',
    description: '',
  }
  isCreateVisible.value = true
}

const create = async () => {
  await $api('/assistance-requests', { method: 'POST', body: form.value })
  isCreateVisible.value = false
  await load()
}

const updateStatus = async status => {
  await $api(`/assistance-requests/${selected.value.id}/status`, {
    method: 'PATCH',
    body: {
      status,
      response_notes: responseNotes.value,
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
      <VCardTitle>Inter-hospital assistance</VCardTitle>
      <template #append>
        <VBtn
          v-if="ability.can('create', 'AssistanceRequest')"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          Request support
        </VBtn>
      </template>
    </VCardItem>
    <VCardText>
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
    </VCardText>
    <VDataTable
      :headers="headers"
      :items="items"
    >
      <template #item.type="{ item }">
        <span class="text-capitalize">{{ labelize(item.type) }}</span>
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
    v-model="isCreateVisible"
    max-width="640"
  >
    <VCard title="Request assistance">
      <VCardText>
        <AppSelect
          v-model="form.to_hospital_id"
          :items="hospitals"
          item-title="name"
          item-value="id"
          label="Destination hospital"
          class="mb-4"
        />
        <AppSelect
          v-model="form.type"
          :items="assistanceTypes"
          label="Type"
          class="mb-4"
        />
        <AppTextField
          v-model="form.title"
          label="Title"
          class="mb-4"
        />
        <AppTextarea
          v-model="form.description"
          label="Details"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          @click="isCreateVisible = false"
        >
          Cancel
        </VBtn>
        <VBtn @click="create">
          Submit
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <VDialog
    :model-value="Boolean(selected)"
    max-width="640"
    @update:model-value="val => { if (!val) selected = null }"
  >
    <VCard v-if="selected">
      <VCardItem>
        <VCardTitle>{{ selected.title }}</VCardTitle>
        <VCardSubtitle>{{ selected.from_hospital?.name }} → {{ selected.to_hospital?.name }}</VCardSubtitle>
      </VCardItem>
      <VCardText>
        <p>{{ selected.description }}</p>
        <AppTextarea
          v-model="responseNotes"
          label="Response notes"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          v-if="userData?.hospitalId === selected.from_hospital_id && selected.status === 'pending'"
          @click="updateStatus('cancelled')"
        >
          Cancel
        </VBtn>
        <VBtn
          v-if="userData?.hospitalId === selected.to_hospital_id && selected.status === 'pending' && ability.can('respond', 'AssistanceRequest')"
          color="error"
          @click="updateStatus('declined')"
        >
          Decline
        </VBtn>
        <VBtn
          v-if="userData?.hospitalId === selected.to_hospital_id && selected.status === 'pending' && ability.can('respond', 'AssistanceRequest')"
          color="success"
          @click="updateStatus('accepted')"
        >
          Accept
        </VBtn>
        <VBtn
          v-if="userData?.hospitalId === selected.to_hospital_id && selected.status === 'accepted'"
          @click="updateStatus('fulfilled')"
        >
          Mark fulfilled
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
