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
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const editing = ref(null)
const removing = ref(null)
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
  formError.value = ''
  editing.value = null
  form.value = {
    name: '',
    description: '',
    workspace: workspaces.value[0]?.value || 'reception',
    permission_ids: [],
  }
  formOpen.value = true
}

const openEdit = role => {
  formError.value = ''
  editing.value = role
  form.value = {
    name: role.name,
    description: role.description,
    workspace: role.workspace,
    permission_ids: (role.permissions || []).map(permission => permission.id),
  }
  formOpen.value = true
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    if (editing.value)
      await $api(`/roles/${editing.value.id}`, { method: 'PUT', body: form.value })
    else
      await $api('/roles', { method: 'POST', body: form.value })

    formOpen.value = false
    await load()
  })
}

const removeRole = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/roles/${removing.value.id}`, { method: 'DELETE' })
    removing.value = null
    await load()
  })
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

    <HGrid cols="3">
      <HCard
        v-for="role in roles"
        :key="role.id"
        :title="role.name"
      >
        <template
          v-if="ability.can('manage', 'Role')"
          #actions
        >
          <HButton
            variant="ghost"
            size="sm"
            @click="openEdit(role)"
          >
            Edit
          </HButton>
          <HButton
            v-if="!role.is_system"
            variant="ghost"
            size="sm"
            @click="formError = ''; removing = role"
          >
            Remove
          </HButton>
        </template>
        <p class="h-muted is-clamp">
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
          class="h-muted"
        >
          +{{ role.permissions.length - 6 }} more
        </p>
      </HCard>
    </HGrid>

    <HOffcanvas
      v-model="formOpen"
      :title="editing ? 'Update role' : 'Add role'"
      size="lg"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
        class="h-stack"
        :disabled="saving"
      >
        <HInput
          v-model="form.name"
          label="Name"
          required
        />
        <HTextarea
          v-model="form.description"
          label="Description"
          hint="Shown to administrators when assigning this role"
        />
        <HSelect
          v-model="form.workspace"
          :items="workspaces"
          item-title="title"
          item-value="value"
          label="Workspace"
          required
        />
        <HMultiSelect
          v-for="(group, name) in groupedPermissions"
          :key="name"
          v-model="form.permission_ids"
          :items="group"
          item-title="name"
          item-value="id"
          :label="name"
          :placeholder="`Choose ${String(name).toLowerCase()} permissions`"
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
    </HOffcanvas>

    <HModal
      :model-value="Boolean(removing)"
      title="Remove role"
      :error="formError"
      :persistent="saving"
      @update:model-value="val => { if (!val) removing = null }"
    >
      <p>Remove {{ removing?.name }}?</p>
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
          @click="removeRole"
        >
          Remove
        </HButton>
      </template>
    </HModal>
  </div>
</template>
