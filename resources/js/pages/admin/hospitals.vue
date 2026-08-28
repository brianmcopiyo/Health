<script setup>
definePage({
  meta: {
    action: 'manage',
    subject: 'Hospital',
  },
})

const hospitals = ref([])
const isDialogVisible = ref(false)
const editing = ref(null)
const form = ref({
  name: '',
  code: '',
  city: '',
  region: '',
  phone: '',
  email: '',
  address: '',
  is_active: true,
})

const headers = [
  { title: 'Hospital', key: 'name' },
  { title: 'Code', key: 'code' },
  { title: 'City', key: 'city' },
  { title: 'Region', key: 'region' },
  { title: 'Active', key: 'is_active' },
  { title: 'Actions', key: 'actions', sortable: false },
]

const load = async () => {
  hospitals.value = asList(await $api('/hospitals'))
}

const openCreate = () => {
  editing.value = null
  form.value = {
    name: '',
    code: '',
    city: '',
    region: '',
    phone: '',
    email: '',
    address: '',
    is_active: true,
  }
  isDialogVisible.value = true
}

const openEdit = item => {
  editing.value = item
  form.value = { ...item }
  isDialogVisible.value = true
}

const save = async () => {
  if (editing.value)
    await $api(`/hospitals/${editing.value.id}`, { method: 'PUT', body: form.value })
  else
    await $api('/hospitals', { method: 'POST', body: form.value })

  isDialogVisible.value = false
  await load()
}

await withPageLoad(load)
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Hospitals</VCardTitle>
      <template #append>
        <VBtn
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          Add hospital
        </VBtn>
      </template>
    </VCardItem>
    <VDataTable
      :headers="headers"
      :items="hospitals"
    >
      <template #item.is_active="{ item }">
        <VChip
          size="small"
          :color="item.is_active ? 'success' : 'secondary'"
        >
          {{ item.is_active ? 'Active' : 'Inactive' }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <IconBtn @click="openEdit(item)">
          <VIcon icon="tabler-edit" />
        </IconBtn>
      </template>
    </VDataTable>
  </VCard>

  <VDialog
    v-model="isDialogVisible"
    max-width="640"
  >
    <VCard :title="editing ? 'Update hospital' : 'Add hospital'">
      <VCardText>
        <AppTextField
          v-model="form.name"
          label="Name"
          class="mb-4"
        />
        <AppTextField
          v-model="form.code"
          label="Code"
          class="mb-4"
        />
        <AppTextField
          v-model="form.city"
          label="City"
          class="mb-4"
        />
        <AppTextField
          v-model="form.region"
          label="Region"
          class="mb-4"
        />
        <AppTextField
          v-model="form.phone"
          label="Phone"
          class="mb-4"
        />
        <AppTextField
          v-model="form.email"
          label="Email"
          class="mb-4"
        />
        <AppTextField
          v-model="form.address"
          label="Address"
          class="mb-4"
        />
        <VSwitch
          v-model="form.is_active"
          label="Active"
        />
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
