<script setup>
definePage({
  meta: {
    action: 'read',
    subject: 'Role',
  },
})

const ability = useAbility()
const roles = ref([])
const permissions = ref([])
const isDialogVisible = ref(false)
const editing = ref(null)
const form = ref({
  name: '',
  description: '',
  workspace: 'reception',
  permission_ids: [],
})
const workspaces = ref([])

const groupedPermissions = computed(() => {
  const groups = {}
  permissions.value.forEach(permission => {
    const group = permission.group || 'General'
    groups[group] = groups[group] || []
    groups[group].push(permission)
  })

  return groups
})

const load = async () => {
  roles.value = asList(await $api('/roles'))
  if (ability.can('manage', 'Role')) {
    permissions.value = asList(await $api('/permissions'))
    workspaces.value = asList(await $api('/modules/workspaces'))
  }
}

const openCreate = () => {
  editing.value = null
  form.value = {
    name: '',
    description: '',
    workspace: workspaces.value[0]?.value || 'reception',
    permission_ids: [],
  }
  isDialogVisible.value = true
}

const openEdit = role => {
  editing.value = role
  form.value = {
    name: role.name,
    description: role.description,
    workspace: role.workspace,
    permission_ids: (role.permissions || []).map(permission => permission.id),
  }
  isDialogVisible.value = true
}

const save = async () => {
  if (editing.value)
    await $api(`/roles/${editing.value.id}`, { method: 'PUT', body: form.value })
  else
    await $api('/roles', { method: 'POST', body: form.value })

  isDialogVisible.value = false
  await load()
}

await withPageLoad(load)
</script>

<template>
  <div>
    <div class="d-flex justify-space-between mb-6">
      <h4 class="text-h4">
        Roles
      </h4>
      <VBtn
        v-if="ability.can('manage', 'Role')"
        prepend-icon="tabler-plus"
        @click="openCreate"
      >
        Add role
      </VBtn>
    </div>

    <VRow>
      <VCol
        v-for="role in roles"
        :key="role.id"
        cols="12"
        md="6"
        lg="4"
      >
        <VCard>
          <VCardItem>
            <VCardTitle>{{ role.name }}</VCardTitle>
            <VCardSubtitle>{{ role.workspace }} · {{ role.description }}</VCardSubtitle>
          </VCardItem>
          <VCardText>
            <VChip
              v-for="permission in role.permissions.slice(0, 6)"
              :key="permission.id"
              size="small"
              class="me-1 mb-1"
            >
              {{ permission.name }}
            </VChip>
            <div
              v-if="role.permissions.length > 6"
              class="text-sm mt-2"
            >
              +{{ role.permissions.length - 6 }} more
            </div>
          </VCardText>
          <VCardActions v-if="ability.can('manage', 'Role')">
            <VBtn
              variant="text"
              @click="openEdit(role)"
            >
              Edit
            </VBtn>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>
  </div>

  <VDialog
    v-model="isDialogVisible"
    max-width="720"
  >
    <VCard :title="editing ? 'Update role' : 'Add role'">
      <VCardText>
        <AppTextField
          v-model="form.name"
          label="Name"
          class="mb-4"
        />
        <AppTextarea
          v-model="form.description"
          label="Description"
          class="mb-4"
        />
        <AppSelect
          v-model="form.workspace"
          :items="workspaces"
          item-title="title"
          item-value="value"
          label="Workspace"
          class="mb-4"
        />
        <div
          v-for="(group, name) in groupedPermissions"
          :key="name"
          class="mb-4"
        >
          <div class="text-subtitle-1 mb-2">
            {{ name }}
          </div>
          <VCheckbox
            v-for="permission in group"
            :key="permission.id"
            v-model="form.permission_ids"
            :label="permission.name"
            :value="permission.id"
            multiple
          />
        </div>
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
