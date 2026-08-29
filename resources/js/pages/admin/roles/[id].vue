<script setup>
import { labelize } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Role',
  },
})

const route = useRoute()
const record = ref(null)
const tab = ref('overview')

const tabs = [
  { title: 'Overview', value: 'overview' },
  { title: 'Permissions', value: 'permissions' },
  { title: 'Users', value: 'users' },
]

const load = async () => {
  record.value = await $api(`/roles/${route.params.id}`)
}

const { pending, run } = usePageQuery(load)
watch(() => route.params.id, () => run())
</script>

<template>
  <HRecord
    :title="record?.name || 'Role'"
    :subtitle="record?.workspace || ''"
    :status="record?.is_system ? 'system' : ''"
    :back="{ name: 'admin-roles' }"
    back-label="Roles"
    :tabs="tabs"
    :tab="tab"
    :loading="pending"
    :missing="!pending && !record"
    @update:tab="tab = $event"
  >
    <div
      v-if="record && tab === 'overview'"
      class="h-detail"
    >
      <HCard title="Role">
        <div class="h-metric">
          <span>Workspace</span>
          <strong>{{ labelize(record.workspace) }}</strong>
        </div>
        <div class="h-metric">
          <span>Hospital</span>
          <strong>{{ record.hospital?.name || 'System' }}</strong>
        </div>
        <p class="h-muted">
          {{ record.description || 'No description' }}
        </p>
      </HCard>
    </div>

    <HCard
      v-if="record && tab === 'permissions'"
      title="Permissions"
    >
      <div class="h-actions">
        <HBadge
          v-for="permission in (record.permissions || [])"
          :key="permission.id"
        >
          {{ permission.name }}
        </HBadge>
      </div>
      <HEmpty
        v-if="!(record.permissions || []).length"
        message="No permissions assigned"
      />
    </HCard>

    <HCard
      v-if="record && tab === 'users'"
      title="Users"
      flush
    >
      <HTable
        :headers="[
          { title: 'Name', key: 'name' },
          { title: 'Email', key: 'email' },
          { title: 'Department', key: 'department.name' },
        ]"
        :items="record.users || []"
        empty="No users assigned this role"
      >
        <template #cell-name="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'admin-users-id', params: { id: item.id } }"
          >
            {{ item.name }}
          </RouterLink>
        </template>
        <template #cell-department.name="{ item }">
          <RouterLink
            v-if="item.department?.id"
            class="h-inline-link"
            :to="{ name: 'admin-departments-id', params: { id: item.department.id } }"
          >
            {{ item.department.name }}
          </RouterLink>
          <span v-else>—</span>
        </template>
      </HTable>
    </HCard>
  </HRecord>
</template>
