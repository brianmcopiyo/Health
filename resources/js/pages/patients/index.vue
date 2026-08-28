<script setup>
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Patient',
  },
})

const ability = useAbility()
const patients = ref([])
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const editing = ref(null)
const form = ref({
  first_name: '',
  last_name: '',
  sex: null,
  date_of_birth: null,
  phone: '',
  address: '',
  mrn: '',
})

const load = async () => {
  patients.value = asList(await $api('/patients'))
}

const openCreate = () => {
  formError.value = ''
  editing.value = null
  form.value = {
    first_name: '',
    last_name: '',
    sex: null,
    date_of_birth: null,
    phone: '',
    address: '',
    mrn: '',
  }
  formOpen.value = true
}

const openEdit = item => {
  formError.value = ''
  editing.value = item
  form.value = {
    first_name: item.first_name,
    last_name: item.last_name,
    sex: item.sex,
    date_of_birth: item.date_of_birth,
    phone: item.phone,
    address: item.address,
    mrn: item.mrn,
  }
  formOpen.value = true
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    if (editing.value)
      await $api(`/patients/${editing.value.id}`, { method: 'PUT', body: form.value })
    else
      await $api('/patients', { method: 'POST', body: form.value })

    formOpen.value = false
    await load()
  })
}

await withPageLoad(load)
</script>

<template>
  <div>
    <HPage
      title="Patients"
      subtitle="Hospital patient register"
    >
      <HButton
        v-if="ability.can('create', 'Patient')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Register patient
      </HButton>
    </HPage>

    <HCard>
      <HTable
        :headers="[
          { title: 'MRN', key: 'mrn' },
          { title: 'Name', key: 'full_name' },
          { title: 'Sex', key: 'sex' },
          { title: 'Phone', key: 'phone' },
          { title: 'Status', key: 'status' },
          { title: 'Actions', key: 'actions' },
        ]"
        :items="patients"
        empty="No patients registered yet"
      >
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <HButton
            v-if="ability.can('update', 'Patient')"
            variant="ghost"
            size="icon"
            @click="openEdit(item)"
          >
            <HIcon name="edit" />
          </HButton>
        </template>
      </HTable>
    </HCard>

    <HModal
      v-model="formOpen"
      :title="editing ? 'Update patient' : 'Register patient'"
      size="lg"
      :error="formError"
      :persistent="saving"
    >
      <div class="h-grid cols-2">
        <HInput
          v-model="form.first_name"
          label="First name"
        />
        <HInput
          v-model="form.last_name"
          label="Last name"
        />
        <HSelect
          v-model="form.sex"
          :items="['male', 'female', 'other']"
          label="Sex"
        />
        <HInput
          v-model="form.date_of_birth"
          type="date"
          label="Date of birth"
        />
        <HInput
          v-model="form.phone"
          label="Phone"
        />
        <HInput
          v-model="form.mrn"
          label="MRN (optional)"
        />
      </div>
      <HInput
        v-model="form.address"
        label="Address"
        style="margin-top:12px"
      />
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="formOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving"
          @click="save"
        >
          Save
        </HButton>
      </template>
    </HModal>
  </div>
</template>
