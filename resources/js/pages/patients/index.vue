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
const isDialogVisible = ref(false)
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
  isDialogVisible.value = true
}

const openEdit = item => {
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
  isDialogVisible.value = true
}

const save = async () => {
  if (editing.value)
    await $api(`/patients/${editing.value.id}`, { method: 'PUT', body: form.value })
  else
    await $api('/patients', { method: 'POST', body: form.value })

  isDialogVisible.value = false
  await load()
}

await withPageLoad(load)
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Patients</VCardTitle>
      <template #append>
        <VBtn
          v-if="ability.can('create', 'Patient')"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          Register patient
        </VBtn>
      </template>
    </VCardItem>
    <VDataTable
      :headers="[
        { title: 'MRN', key: 'mrn' },
        { title: 'Name', key: 'full_name' },
        { title: 'Sex', key: 'sex' },
        { title: 'Phone', key: 'phone' },
        { title: 'Status', key: 'status' },
        { title: 'Actions', key: 'actions', sortable: false },
      ]"
      :items="patients"
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
        <IconBtn
          v-if="ability.can('update', 'Patient')"
          @click="openEdit(item)"
        >
          <VIcon icon="tabler-edit" />
        </IconBtn>
      </template>
    </VDataTable>
  </VCard>

  <VDialog
    v-model="isDialogVisible"
    max-width="640"
  >
    <VCard :title="editing ? 'Update patient' : 'Register patient'">
      <VCardText>
        <VRow>
          <VCol md="6">
            <AppTextField
              v-model="form.first_name"
              label="First name"
            />
          </VCol>
          <VCol md="6">
            <AppTextField
              v-model="form.last_name"
              label="Last name"
            />
          </VCol>
          <VCol md="6">
            <AppSelect
              v-model="form.sex"
              :items="['male', 'female', 'other']"
              label="Sex"
            />
          </VCol>
          <VCol md="6">
            <AppTextField
              v-model="form.date_of_birth"
              type="date"
              label="Date of birth"
            />
          </VCol>
          <VCol md="6">
            <AppTextField
              v-model="form.phone"
              label="Phone"
            />
          </VCol>
          <VCol md="6">
            <AppTextField
              v-model="form.mrn"
              label="MRN (optional)"
            />
          </VCol>
          <VCol cols="12">
            <AppTextField
              v-model="form.address"
              label="Address"
            />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          @click="isDialogVisible = false"
        >
          Cancel
        </VBtn>
        <VBtn @click="save">
          Save
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
