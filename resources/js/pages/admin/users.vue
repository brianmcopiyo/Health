<script setup>
definePage({
  meta: {
    action: 'read',
    subject: 'User',
  },
})

const ability = useAbility()
const userData = useCookie('userData')
const users = ref([])
const meta = ref(asPageMeta())
const page = ref(1)
const roles = ref([])
const hospitals = ref([])
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const editing = ref(null)
const removing = ref(null)
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
  const payload = await $api('/users', { query: { page: page.value } })
  users.value = asList(payload)
  meta.value = asPageMeta(payload)
  if (ability.can('manage', 'User'))
    roles.value = asList(await $api('/roles'))
}

const openCreate = async () => {
  formError.value = ''
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
  formOpen.value = true
}

const openEdit = item => {
  formError.value = ''
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
  formOpen.value = true
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    const payload = { ...form.value }
    if (editing.value) {
      if (!payload.password)
        delete payload.password
      await $api(`/users/${editing.value.id}`, { method: 'PUT', body: payload })
    }
    else {
      await $api('/users', { method: 'POST', body: payload })
    }
    formOpen.value = false
    await load()
  })
}

const removeUser = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/users/${removing.value.id}`, { method: 'DELETE' })
    removing.value = null
    await load()
  })
}

const { pending } = usePageQuery(load)
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

    <HCard flush>
      <HTable
        :loading="pending"
        :headers="headers"
        :items="users"
        empty="No users in this hospital"
      >
        <template #cell-name="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'admin-users-id', params: { id: item.id } }"
          >
            {{ item.name }}
          </RouterLink>
        </template>
        <template #cell-actions="{ item }">
          <div class="h-actions">
            <HButton
              variant="ghost"
              size="sm"
              :to="{ name: 'admin-users-id', params: { id: item.id } }"
            >
              View
            </HButton>
            <HButton
              v-if="ability.can('manage', 'User')"
              variant="ghost"
              size="icon"
              @click="openEdit(item)"
            >
              <HIcon name="edit" />
            </HButton>
            <HButton
              v-if="ability.can('manage', 'User') && item.id !== userData?.id"
              variant="ghost"
              size="sm"
              @click="formError = ''; removing = item"
            >
              Remove
            </HButton>
          </div>
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => { page = value; load() }"
      />
    </HCard>

    <HModal
      v-model="formOpen"
      :title="editing ? 'Update user' : 'Add user'"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
        class="h-form-grid"
        :disabled="saving"
      >
        <HInput
          v-model="form.name"
          label="Name"
          placeholder="e.g. Grace Adeyemi"
          required
        />
        <HInput
          v-model="form.email"
          label="Email"
          type="email"
          icon="mail"
          placeholder="e.g. nurse@hospital.org"
          required
        />
        <HInput
          span
          v-model="form.password"
          :label="editing ? 'New password' : 'Password'"
          :optional="Boolean(editing)"
          :required="!editing"
          type="password"
          icon="lock"
          placeholder="At least 8 characters"
          :hint="editing ? 'Leave blank to keep the current password' : ''"
        />
        <HSelect
          v-model="form.role_id"
          :items="roles"
          item-title="name"
          item-value="id"
          label="Role"
          required
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
          placeholder="e.g. Charge nurse"
        />
        <HInput
          v-model="form.phone"
          label="Phone"
          type="tel"
          icon="phone"
          placeholder="e.g. 024 555 0100"
        />
      </fieldset>
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

    <HModal
      :model-value="Boolean(removing)"
      title="Remove user"
      :error="formError"
      :persistent="saving"
      @update:model-value="val => { if (!val) removing = null }"
    >
      <p>Remove access for {{ removing?.name }}?</p>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="removing = null"
        >
          Keep
        </HButton>
        <HButton
          variant="danger"
          :disabled="saving"
          @click="removeUser"
        >
          Remove
        </HButton>
      </template>
    </HModal>

  </div>
</template>
