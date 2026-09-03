<script setup>
definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const ability = useAbility()
const all = ref([])
const list = useListQuery(['type', 'is_active'])
const { q, filterValues } = list
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const form = ref({ name: '', type: 'warehouse', is_default: false })

const stores = computed(() => {
  const term = String(q.value || '').trim().toLowerCase()
  const active = list.values.is_active
  const type = list.values.type
  return all.value.filter(item => {
    if (term && !String(item.name || '').toLowerCase().includes(term))
      return false
    if (type && item.type !== type)
      return false
    if (active === '1')
      return Boolean(item.is_active)
    if (active === '0')
      return !item.is_active
    return true
  })
})

const load = async () => {
  all.value = asList(await $api('/inventory/stores'))
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/inventory/stores', { method: 'POST', body: form.value })
    formOpen.value = false
    await load()
  })
}

list.sync(load)
const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Stores"
      subtitle="Pharmacy, warehouse, and ward stock locations"
    >
      <HExportActions
        dataset="inventory-stores"
        :query="list.apiQuery()"
        :disabled="pending"
      />
      <HButton
        v-if="ability.can('create', 'Inventory')"
        @click="formOpen = true"
      >
        <HIcon name="plus" />
        Add store
      </HButton>
    </HPage>
    <HCard flush>
      <HListToolbar
        v-model:search="q"
        v-model:values="filterValues"
        search-placeholder="Search stores"
        search-button
        :result-count="list.resultCount({ total: stores.length })"
        :filters="[
          { key: 'type', type: 'select', label: 'Type', placeholder: 'All types', optional: true, empty: null, items: [
            { title: 'Warehouse', value: 'warehouse' },
            { title: 'Pharmacy', value: 'pharmacy' },
            { title: 'Department', value: 'department' },
            { title: 'Ward', value: 'ward' },
          ] },
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
        :headers="[{ title: 'Store', key: 'name', fill: true }]"
        :items="stores"
        empty="No stores"
      >
        <template #cell-name="{ item }">
          <HCell
            :to="{ name: 'inventory-stores-id', params: { id: item.id } }"
            :secondary="item.type"
          >
            {{ item.name }}
          </HCell>
        </template>
      </HTable>
    </HCard>
    <HModal
      v-model="formOpen"
      title="Add store"
      :error="formError"
      :persistent="saving"
    >
      <HFormGrid>
        <HInput
          v-model="form.name"
          label="Name"
          required
        />
        <HSelect
          v-model="form.type"
          :items="[{ id: 'warehouse', name: 'Warehouse' }, { id: 'pharmacy', name: 'Pharmacy' }, { id: 'department', name: 'Department' }, { id: 'ward', name: 'Ward' }]"
          item-title="name"
          item-value="id"
          label="Type"
        />
      </HFormGrid>
      <template #actions>
        <HButton
          variant="ghost"
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
  </div>
</template>
