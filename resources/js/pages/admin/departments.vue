<script setup>
import { labelize } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Department',
  },
})

const ability = useAbility()
const all = ref([])
const catalog = ref([])
const list = useListQuery(['is_active', 'module_key'])
const { q, filterValues } = list
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const editing = ref(null)
const removing = ref(null)
const restoring = ref(false)
const form = ref({
  name: '',
  module_key: 'reception',
  is_active: true,
})

const departments = computed(() => {
  const term = String(q.value || '').trim().toLowerCase()
  const active = list.values.is_active
  const moduleKey = list.values.module_key
  return all.value.filter(item => {
    if (term && !String(item.name || '').toLowerCase().includes(term))
      return false
    if (moduleKey && item.module_key !== moduleKey)
      return false
    if (active === '1')
      return Boolean(item.is_active)
    if (active === '0')
      return !item.is_active
    return true
  })
})

const load = async () => {
  all.value = asList(await $api('/departments'))
  catalog.value = asList(await $api('/modules/catalog'))
}

const openCreate = () => {
  formError.value = ''
  editing.value = null
  form.value = { name: '', module_key: catalog.value[0]?.key || 'reception', is_active: true }
  formOpen.value = true
}

const openEdit = item => {
  formError.value = ''
  editing.value = item
  form.value = { name: item.name, module_key: item.module_key, is_active: item.is_active }
  formOpen.value = true
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    if (editing.value)
      await $api(`/departments/${editing.value.id}`, { method: 'PUT', body: form.value })
    else
      await $api('/departments', { method: 'POST', body: form.value })

    formOpen.value = false
    await load()
  })
}

const removeDepartment = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/departments/${removing.value.id}`, { method: 'DELETE' })
    removing.value = null
    await load()
  })
}

const restoreDefaults = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/departments/restore-defaults', { method: 'POST' })
    restoring.value = false
    await load()
  })
}

list.sync(load)
const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Departments"
      subtitle="Map hospital departments to modules"
    >
      <HExportActions
        dataset="departments"
        :disabled="pending"
      />
      <HButton
        v-if="ability.can('manage', 'Department')"
        variant="ghost"
        @click="formError = ''; restoring = true"
      >
        Restore defaults
      </HButton>
      <HButton
        v-if="ability.can('manage', 'Department')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Add department
      </HButton>
    </HPage>

    <HCard flush>
      <HListToolbar
        v-model:search="q"
        v-model:values="filterValues"
        search-placeholder="Search departments"
        search-button
        :result-count="list.resultCount({ total: departments.length })"
        :filters="[
          { key: 'module_key', type: 'select', label: 'Module', placeholder: 'All modules', items: catalog, itemTitle: 'title', itemValue: 'key', optional: true, empty: null },
          { key: 'is_active', type: 'select', label: 'Status', placeholder: 'All statuses', optional: true, empty: null, items: [
            { title: 'Active', value: '1' },
            { title: 'Inactive', value: '0' },
          ] },
        ]"
        @search="list.onSearch(load)"
        @change="list.onChange(load)"
      />
      <HTable
        :loading="pending"
        :headers="[
          { title: 'Name', key: 'name', fill: true },
          { title: 'Active', key: 'is_active' },
          { title: 'Facilities', key: 'facilities_count' },
          { title: 'Staff', key: 'users_count' },
          { title: 'Actions', key: 'actions' },
        ]"
        :items="departments"
        empty="No departments configured"
      >
        <template #cell-name="{ item }">
          <HCell
            :to="{ name: 'admin-departments-id', params: { id: item.id } }"
            :secondary="joinContext(item.hospital?.name, labelize(item.module_key))"
          >
            {{ item.name }}
          </HCell>
        </template>
        <template #cell-is_active="{ item }">
          {{ item.is_active ? 'Yes' : 'No' }}
        </template>
        <template #cell-facilities_count="{ item }">
          <HCell :to="{ name: 'facilities', query: { department_id: item.id } }">
            {{ item.facilities_count }}
          </HCell>
        </template>
        <template #cell-actions="{ item }">
          <HActionMenu
            :actions="[
              { label: 'View', icon: 'eye', to: { name: 'admin-departments-id', params: { id: item.id } } },
              { label: 'Edit', icon: 'edit', if: ability.can('manage', 'Department'), onSelect: () => openEdit(item) },
              { label: 'Remove', icon: 'trash', danger: true, if: ability.can('manage', 'Department'), onSelect: () => { formError = ''; removing = item } },
            ]"
          />
        </template>
      </HTable>
    </HCard>

    <HModal
      v-model="formOpen"
      :title="editing ? 'Update department' : 'Add department'"
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
          placeholder="e.g. Emergency"
          required
        />
        <HSelect
          v-model="form.module_key"
          :items="catalog"
          item-title="title"
          item-value="key"
          label="Module"
          required
        />
        <HSwitch
          span
          v-model="form.is_active"
          label="Department is active"
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
          :loading="saving"
          :disabled="saving"
          @click="save"
        >
          Save
        </HButton>
      </template>
    </HModal>

    <HModal
      :model-value="Boolean(removing)"
      title="Remove department"
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
          :loading="saving"
          :disabled="saving"
          @click="removeDepartment"
        >
          Remove
        </HButton>
      </template>
    </HModal>

    <HModal
      v-model="restoring"
      title="Restore default departments"
      :error="formError"
      :persistent="saving"
    >
      <p>Add any missing default departments for this hospital.</p>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="restoring = false"
        >
          Cancel
        </HButton>
        <HButton
          :loading="saving"
          :disabled="saving"
          @click="restoreDefaults"
        >
          Restore
        </HButton>
      </template>
    </HModal>

  </div>
</template>
