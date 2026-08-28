<script setup>
definePage({
  meta: {
    action: 'read',
    subject: 'User',
  },
})

const ability = useAbility()
const users = ref([])
const roles = ref([])
const hospitals = ref([])
const isDialogVisible = ref(false)
const editing = ref(null)
const form = ref({
  name: '',
  email: '',
  password: '',
  role_id: null,
  hospital_id: null,
  phone: '',
  job_title: '',
})

const headers = [
  { title: 'Name', key: 'name' },
  { title: 'Email', key: 'email' },
  { title: 'Role', key: 'role.name' },
  { title: 'Hospital', key: 'hospital.name' },
  { title: 'Actions', key: 'actions', sortable: false },
]

const load = async () => {
  users.value = asList(await $api('/users'))
  if (ability.can('manage', 'User'))
    roles.value = asList(await $api('/roles'))
}

const openCreate = async () => {
  editing.value = null
  if (ability.can('manage', 'Hospital'))
    hospitals.value = asList(await $api('/hospitals'))
  form.value = {
    name: '',
    email: '',
    password: '',
    role_id: roles.value.find(role => role.slug === 'nurse')?.id ?? roles.value[0]?.id,
    hospital_id: null,
    phone: '',
    job_title: '',
  }
  isDialogVisible.value = true
}

const openEdit = item => {
  editing.value = item
  form.value = {
    name: item.name,
    email: item.email,
    password: '',
    role_id: item.role_id,
    hospital_id: item.hospital_id,
    phone: item.phone,
    job_title: item.job_title,
  }
  isDialogVisible.value = true
}

const save = async () => {
  const payload = { ...form.value }
  if (editing.value) {
    if (!payload.password)
      delete payload.password
    await $api(`/users/${editing.value.id}`, { method: 'PUT', body: payload })
  } else {
    await $api('/users', { method: 'POST', body: payload })
  }
  isDialogVisible.value = false
  await load()
}

await withPageLoad(load)
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Users</VCardTitle>
      <template #append>
        <VBtn
          v-if="ability.can('manage', 'User')"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          Add user
        </VBtn>
      </template>
    </VCardItem>
    <VDataTable
      :headers="headers"
      :items="users"
    >
      <template #item.actions="{ item }">
        <IconBtn
          v-if="ability.can('manage', 'User')"
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
    <VCard :title="editing ? 'Update user' : 'Add user'">
      <VCardText>
        <AppTextField
          v-model="form.name"
          label="Name"
          class="mb-4"
        />
        <AppTextField
          v-model="form.email"
          label="Email"
          class="mb-4"
        />
        <AppTextField
          v-model="form.password"
          :label="editing ? 'New password' : 'Password'"
          type="password"
          class="mb-4"
        />
        <AppSelect
          v-model="form.role_id"
          :items="roles"
          item-title="name"
          item-value="id"
          label="Role"
          class="mb-4"
        />
        <AppSelect
          v-if="ability.can('manage', 'Hospital')"
          v-model="form.hospital_id"
          :items="hospitals"
          item-title="name"
          item-value="id"
          label="Hospital"
          class="mb-4"
        />
        <AppTextField
          v-model="form.job_title"
          label="Job title"
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
