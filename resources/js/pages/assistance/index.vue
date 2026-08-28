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
})

const headers = [
  { title: 'Request', key: 'title' },
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
  form.value = {
    to_hospital_id: hospitals.value[0]?.id ?? null,
    type: 'staff',
    title: '',
    description: '',
  }
  isCreateVisible.value = true
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

    <HCard>
      <div style="margin-bottom:16px">
        <HSegmented
          :model-value="direction"
          :options="[
            { value: 'all', title: 'All' },
            { value: 'incoming', title: 'Incoming' },
            { value: 'outgoing', title: 'Outgoing' },
          ]"
          @update:model-value="setDirection"
        />
      </div>
      <HTable
        :headers="headers"
        :items="items"
        empty="No assistance requests yet"
      >
        <template #cell-type="{ item }">
          {{ labelize(item.type) }}
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <HButton
            variant="ghost"
            size="sm"
            @click="formError = ''; selected = item"
          >
            Manage
          </HButton>
        </template>
      </HTable>
    </HCard>

    <HModal
      v-model="isCreateVisible"
      title="Request assistance"
      :error="formError"
      :persistent="saving"
    >
      <div class="h-stack">
        <HSelect
          v-model="form.to_hospital_id"
          :items="hospitals"
          item-title="name"
          item-value="id"
          label="Destination hospital"
        />
        <HSelect
          v-model="form.type"
          :items="assistanceTypes"
          label="Type"
        />
        <HInput
          v-model="form.title"
          label="Title"
        />
        <HTextarea
          v-model="form.description"
          label="Details"
        />
      </div>
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
      <div v-if="selected">
        <p style="color:var(--muted);margin-top:0">
          {{ selected.from_hospital?.name }} → {{ selected.to_hospital?.name }}
        </p>
        <p>{{ selected.description }}</p>
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
