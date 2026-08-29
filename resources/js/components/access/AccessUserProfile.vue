<script setup>
import { ref, watch } from 'vue'
import { useAbility } from '@/composables/useAbility'
import { asList } from '@/composables/usePageLoad'
import { $api } from '@/utils/api'
import { formatWhen } from '@/utils/helpers'
import { canWriteUser } from '@/utils/access'
import AccessUserFormModal from './AccessUserFormModal.vue'

const props = defineProps({
  record: { type: Object, default: null },
})

const emit = defineEmits(['saved'])
const ability = useAbility()
const roles = ref([])
const formOpen = ref(false)

const loadRoles = async () => {
  if (canWriteUser(ability))
    roles.value = asList(await $api('/roles'))
}

const openEdit = async () => {
  await loadRoles()
  formOpen.value = true
}

watch(() => props.record?.id, () => {
  formOpen.value = false
})
</script>

<template>
  <div
    v-if="record"
    class="h-detail"
  >
    <HCard title="Profile">
      <template
        v-if="ability.can('update', 'User')"
        #actions
      >
        <HButton
          variant="ghost"
          size="sm"
          @click="openEdit"
        >
          <HIcon name="edit" />
          Edit
        </HButton>
      </template>
      <div class="h-metric">
        <span>Name</span>
        <strong>{{ record.name }}</strong>
      </div>
      <div class="h-metric">
        <span>Email</span>
        <strong>{{ record.email }}</strong>
      </div>
      <div class="h-metric">
        <span>Phone</span>
        <strong>{{ record.phone || '—' }}</strong>
      </div>
      <div class="h-metric">
        <span>Title</span>
        <strong>{{ record.job_title || '—' }}</strong>
      </div>
      <div class="h-metric">
        <span>Profile photo</span>
        <strong>{{ record.has_avatar ? 'On file' : 'None' }}</strong>
      </div>
      <slot name="account-extra" />
    </HCard>

    <HCard title="Access">
      <div class="h-metric">
        <span>Role</span>
        <strong>
          <RouterLink
            v-if="record.role?.id"
            class="h-inline-link"
            :to="{ name: 'admin-roles-id', params: { id: record.role.id } }"
          >
            {{ record.role.name }}
          </RouterLink>
          <span v-else>—</span>
        </strong>
      </div>
      <div class="h-metric">
        <span>Status</span>
        <strong>{{ record.status }}</strong>
      </div>
      <slot name="access-extra" />
    </HCard>

    <HCard title="Activity">
      <div class="h-metric">
        <span>Last login</span>
        <strong>{{ formatWhen(record.last_login_at) }}</strong>
      </div>
      <div class="h-metric">
        <span>Created</span>
        <strong>{{ formatWhen(record.created_at) }}</strong>
      </div>
    </HCard>

    <HCard title="Permissions">
      <div class="h-actions">
        <HBadge
          v-for="permission in (record.permissions || [])"
          :key="permission.id"
        >
          {{ permission.name }}
        </HBadge>
      </div>
      <p
        v-if="!(record.permissions || []).length"
        class="h-muted"
      >
        No permissions on this role
      </p>
    </HCard>

    <AccessUserFormModal
      v-model="formOpen"
      :user="record"
      :roles="roles"
      @saved="emit('saved')"
    >
      <template #extra="slotProps">
        <slot
          name="form-extra"
          v-bind="slotProps"
        />
      </template>
    </AccessUserFormModal>
  </div>
</template>
