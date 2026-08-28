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
    <HPage
      title="Roles"
      subtitle="Permissions and default workspaces"
    >
      <HButton
        v-if="ability.can('manage', 'Role')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Add role
      </HButton>
    </HPage>

    <div class="h-grid cols-3">
      <HCard
        v-for="role in roles"
        :key="role.id"
        :title="role.name"
      >
        <p style="color:var(--muted);margin-top:0">
          {{ role.workspace }} · {{ role.description }}
        </p>
        <div class="h-actions">
          <HBadge
            v-for="permission in (role.permissions || []).slice(0, 6)"
            :key="permission.id"
          >
            {{ permission.name }}
          </HBadge>
        </div>
        <p
          v-if="(role.permissions || []).length > 6"
          style="color:var(--muted);font-size:0.85rem"
        >
          +{{ role.permissions.length - 6 }} more
        </p>
        <HButton
          v-if="ability.can('manage', 'Role')"
          variant="ghost"
          size="sm"
          style="margin-top:10px"
          @click="openEdit(role)"
        >
          Edit
        </HButton>
      </HCard>
    </div>

    <HDialog
      v-model="isDialogVisible"
      :title="editing ? 'Update role' : 'Add role'"
      wide
    >
      <div class="h-stack">
        <HInput
          v-model="form.name"
          label="Name"
        />
        <HTextarea
          v-model="form.description"
          label="Description"
        />
        <HSelect
          v-model="form.workspace"
          :items="workspaces"
          item-title="title"
          item-value="value"
          label="Workspace"
        />
        <div
          v-for="(group, name) in groupedPermissions"
          :key="name"
          class="h-perm-group"
        >
          <h4>{{ name }}</h4>
          <label
            v-for="permission in group"
            :key="permission.id"
            class="h-check"
          >
            <input
              v-model="form.permission_ids"
              type="checkbox"
              :value="permission.id"
            >
            {{ permission.name }}
          </label>
        </div>
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
