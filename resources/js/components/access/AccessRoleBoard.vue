<script setup>
import { ref } from 'vue'
import { useAbility } from '@/composables/useAbility'
import { asList, useDelayedVisible, usePageQuery, wrapSave } from '@/composables/usePageLoad'
import { $api } from '@/utils/api'
import { canWriteRole } from '@/utils/access'
import AccessConfirmModal from './AccessConfirmModal.vue'
import AccessRoleFormOverlay from './AccessRoleFormOverlay.vue'

const props = defineProps({
  workspacesEndpoint: { type: String, default: '/workspaces' },
  defaultWorkspace: { type: String, default: 'admin' },
  namePlaceholder: { type: String, default: 'e.g. Operations' },
})

const ability = useAbility()
const roles = ref([])
const permissions = ref([])
const workspaces = ref([])
const formOpen = ref(false)
const editing = ref(null)
const removing = ref(null)
const saving = ref(false)
const formError = ref('')

const load = async () => {
  roles.value = asList(await $api('/roles'))
  if (canWriteRole(ability)) {
    permissions.value = asList(await $api('/permissions'))
    workspaces.value = asList(await $api(props.workspacesEndpoint))
  }
}

const openCreate = () => {
  editing.value = null
  formOpen.value = true
}

const openEdit = role => {
  editing.value = role
  formOpen.value = true
}

const removeRole = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/roles/${removing.value.id}`, { method: 'DELETE' })
    removing.value = null
    await load()
  })
}

const { pending } = usePageQuery(load)
const showSkel = useDelayedVisible(() => pending.value && !roles.value.length)
</script>

<template>
  <div>
    <HPage
      title="Roles"
      subtitle="Permissions and default workspaces"
    >
      <HButton
        v-if="ability.can('create', 'Role')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Add role
      </HButton>
    </HPage>

    <HGrid
      v-if="pending && !roles.length"
      cols="3"
      :class="{ 'is-hold': !showSkel }"
    >
      <HCard
        v-for="n in 6"
        :key="n"
      >
        <HSkeleton :lines="2" />
      </HCard>
    </HGrid>
    <HEmpty
      v-else-if="!roles.length"
      message="No roles have been defined yet"
    />
    <HGrid
      v-else
      cols="3"
    >
      <HCard
        v-for="role in roles"
        :key="role.id"
        :title="role.name"
      >
        <template #actions>
          <HButton
            variant="ghost"
            size="sm"
            :to="{ name: 'admin-roles-id', params: { id: role.id } }"
          >
            View
          </HButton>
          <HButton
            v-if="ability.can('update', 'Role')"
            variant="ghost"
            size="sm"
            @click="openEdit(role)"
          >
            Edit
          </HButton>
          <HButton
            v-if="ability.can('delete', 'Role') && !role.is_system"
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

    <AccessRoleFormOverlay
      v-model="formOpen"
      :role="editing"
      :permissions="permissions"
      :workspaces="workspaces"
      :default-workspace="defaultWorkspace"
      :name-placeholder="namePlaceholder"
      @saved="load"
    />

    <AccessConfirmModal
      :model-value="Boolean(removing)"
      title="Remove role"
      :message="`Remove ${removing?.name}?`"
      :error="formError"
      :saving="saving"
      @update:model-value="val => { if (!val) removing = null }"
      @confirm="removeRole"
    />
  </div>
</template>
