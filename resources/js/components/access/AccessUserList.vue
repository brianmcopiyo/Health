<script setup>
import { computed, ref } from 'vue'
import { useAbility } from '@/composables/useAbility'
import { useCookie } from '@/composables/useCookie'
import { asList, asPageMeta, usePageQuery, wrapSave } from '@/composables/usePageLoad'
import { $api } from '@/utils/api'
import { formatWhen } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'
import {
  accountStatusItems,
  bulkStatusItems,
  canWriteUser,
  userListQuery,
  userSortItems,
  sortDirItems,
} from '@/utils/access'
import AccessConfirmModal from './AccessConfirmModal.vue'
import AccessUserFormModal from './AccessUserFormModal.vue'

const props = defineProps({
  subtitle: { type: String, default: 'Accounts and role assignment' },
  empty: { type: String, default: 'No users match these filters' },
  defaultRoleSlug: { type: String, default: '' },
  extraHeaders: { type: Array, default: () => [] },
  namePlaceholder: String,
  emailPlaceholder: String,
})

const ability = useAbility()
const userData = useCookie('userData')
const users = ref([])
const roles = ref([])
const meta = ref(asPageMeta())
const page = ref(1)
const search = ref('')
const selected = ref([])
const formOpen = ref(false)
const editing = ref(null)
const removing = ref(null)
const saving = ref(false)
const formError = ref('')
const filterValues = ref({
  status: null,
  role_id: null,
  sort: 'name',
  sort_dir: 'asc',
})

const headers = computed(() => [
  ...(ability.can('update', 'User') ? [{ title: '', key: 'select', fit: true }] : []),
  { title: 'Name', key: 'name', fill: true },
  { title: 'Role', key: 'role.name' },
  ...props.extraHeaders,
  { title: 'Status', key: 'status' },
  { title: 'Last login', key: 'last_login_at' },
  { title: 'Actions', key: 'actions' },
])

const filters = computed(() => [
  { key: 'status', type: 'select', label: 'Status', placeholder: 'All statuses', optional: true, empty: null, items: accountStatusItems },
  { key: 'role_id', type: 'select', label: 'Role', placeholder: 'All roles', optional: true, empty: null, items: roles.value, itemTitle: 'name', itemValue: 'id' },
  { key: 'sort', type: 'select', label: 'Sort', items: userSortItems, clearable: false, empty: 'name' },
  { key: 'sort_dir', type: 'select', label: 'Order', items: sortDirItems, clearable: false, empty: 'asc' },
])

const pageIds = computed(() => users.value.map(item => item.id))
const allSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every(id => selected.value.includes(id)))

const toggleAll = () => {
  selected.value = allSelected.value
    ? selected.value.filter(id => !pageIds.value.includes(id))
    : [...new Set([...selected.value, ...pageIds.value])]
}

const load = async () => {
  const payload = await $api('/users', { query: userListQuery(page.value, search.value, filterValues.value) })
  users.value = asList(payload)
  meta.value = asPageMeta(payload)
  if (canWriteUser(ability) || ability.can('read', 'Role'))
    roles.value = asList(await $api('/roles'))
}

const openCreate = () => {
  editing.value = null
  formOpen.value = true
}

const openEdit = item => {
  editing.value = item
  formOpen.value = true
}

const removeUser = async () => {
  const id = removing.value?.id
  await wrapSave(saving, formError, async () => {
    await $api(`/users/${id}`, { method: 'DELETE' })
    removing.value = null
    selected.value = selected.value.filter(item => item !== id)
    await load()
  }, 'Removed')
}

const applyBulk = async status => {
  if (!selected.value.length)
    return
  await wrapSave(saving, formError, async () => {
    await $api('/users/bulk-status', { method: 'POST', body: { user_ids: selected.value, status } })
    selected.value = []
    await load()
  }, 'Users updated')
}

const onSearch = () => {
  page.value = 1
  load()
}

const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Users"
      :subtitle="subtitle"
    >
      <HExportActions
        dataset="users"
        :query="{
          q: search || undefined,
          status: filterValues.status || undefined,
          role_id: filterValues.role_id || undefined,
          sort: filterValues.sort || undefined,
          sort_dir: filterValues.sort_dir || undefined,
          ids: selected.length ? selected.join(',') : undefined,
        }"
        :disabled="pending"
      />
      <HButton
        v-if="ability.can('create', 'User')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Add user
      </HButton>
    </HPage>

    <HCard flush>
      <HListToolbar
        v-model:search="search"
        v-model:values="filterValues"
        search-placeholder="Search name, email or phone"
        search-button
        :filters="filters"
        @search="onSearch"
        @change="onSearch"
      >
        <template
          v-if="ability.can('update', 'User') && selected.length"
          #actions
        >
          <HSelect
            :model-value="null"
            :items="bulkStatusItems"
            item-title="title"
            item-value="value"
            label="Bulk status"
            placeholder="Change status"
            :disabled="saving"
            @update:model-value="value => { if (value) applyBulk(value) }"
          />
        </template>
      </HListToolbar>
      <HTable
        :loading="pending"
        :headers="headers"
        :items="users"
        :empty="empty"
      >
        <template #cell-select="{ item }">
          <HCheckbox
            v-model="selected"
            :value="item.id"
          />
        </template>
        <template #cell-name="{ item }">
          <HCell
            :to="{ name: 'admin-users-id', params: { id: item.id } }"
            :secondary="item.email"
          >
            {{ item.name }}
          </HCell>
        </template>
        <template
          v-for="header in extraHeaders"
          :key="header.key"
          #[`cell-${header.key}`]="slotProps"
        >
          <slot
            :name="`cell-${header.key}`"
            v-bind="slotProps"
          >
            {{ slotProps.item?.[header.key.split('.')[0]]?.[header.key.split('.')[1]] ?? '—' }}
          </slot>
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-last_login_at="{ item }">
          {{ formatWhen(item.last_login_at) }}
        </template>
        <template #cell-actions="{ item }">
          <HActionMenu
            :actions="[
              { label: 'View', icon: 'eye', to: { name: 'admin-users-id', params: { id: item.id } } },
              { label: 'Edit', icon: 'edit', if: ability.can('update', 'User'), onSelect: () => openEdit(item) },
              { label: 'Remove', icon: 'trash', danger: true, if: ability.can('delete', 'User') && item.id !== userData?.id, onSelect: () => { formError = ''; removing = item } },
            ]"
          />
        </template>
      </HTable>
      <div
        v-if="ability.can('update', 'User') && users.length"
        class="h-actions"
        style="padding: 0.75rem 1rem"
      >
        <HCheckbox
          :model-value="allSelected"
          label="Select page"
          @update:model-value="toggleAll"
        />
      </div>
      <HPager
        :meta="meta"
        @update:page="value => { page = value; load() }"
      />
    </HCard>

    <AccessUserFormModal
      v-model="formOpen"
      :user="editing"
      :roles="roles"
      :default-role-slug="defaultRoleSlug"
      :name-placeholder="namePlaceholder"
      :email-placeholder="emailPlaceholder"
      @saved="load"
    >
      <template #extra="slotProps">
        <slot
          name="form-extra"
          v-bind="slotProps"
        />
      </template>
    </AccessUserFormModal>

    <AccessConfirmModal
      :model-value="Boolean(removing)"
      title="Remove user"
      :message="`Remove access for ${removing?.name}?`"
      :error="formError"
      :saving="saving"
      @update:model-value="val => { if (!val) removing = null }"
      @confirm="removeUser"
    />
  </div>
</template>
