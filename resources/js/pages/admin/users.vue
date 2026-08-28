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
  { title: 'Actions', key: 'actions' },
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
  }
  else {
    await $api('/users', { method: 'POST', body: payload })
  }
  isDialogVisible.value = false
  await load()
}

await withPageLoad(load)
</script>

<template>
  <div>
    <HPage
      title="Users"
      subtitle="Hospital access and role assignment"
    >
      <HButton
        v-if="ability.can('manage', 'User')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Add user
      </HButton>
    </HPage>

    <HCard>
      <HTable
        :headers="headers"
        :items="users"
        empty="No users in this hospital"
      >
        <template #cell-actions="{ item }">
          <HButton
            v-if="ability.can('manage', 'User')"
            variant="ghost"
            size="icon"
            @click="openEdit(item)"
          >
            <HIcon name="edit" />
          </HButton>
        </template>
      </HTable>
    </HCard>

    <HDialog
      v-model="isDialogVisible"
      :title="editing ? 'Update user' : 'Add user'"
    >
      <div class="h-stack">
        <HInput
          v-model="form.name"
          label="Name"
        />
        <HInput
          v-model="form.email"
          label="Email"
        />
        <HInput
          v-model="form.password"
          :label="editing ? 'New password' : 'Password'"
          type="password"
        />
        <HSelect
          v-model="form.role_id"
          :items="roles"
          item-title="name"
          item-value="id"
          label="Role"
        />
        <HSelect
          v-if="ability.can('manage', 'Hospital')"
          v-model="form.hospital_id"
          :items="hospitals"
          item-title="name"
          item-value="id"
          label="Hospital"
        />
        <HInput
          v-model="form.job_title"
          label="Job title"
        />
      </div>
      <template #actions>
        <HButton
          variant="ghost"
          @click="isDialogVisible = false"
        >
          Cancel
        </HButton>
        <HButton @click="save">
          Save
        </HButton>
      </template>
    </HDialog>
  </div>
</template>
