<script setup>
definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const ability = useAbility()
const all = ref([])
const list = useListQuery(['is_active'])
const { q, filterValues } = list
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const form = ref({ name: '', parent_id: null })

const rows = computed(() => {
  const term = String(q.value || '').trim().toLowerCase()
  const active = list.values.is_active
  return all.value.filter(item => {
    if (term && !String(item.name || '').toLowerCase().includes(term))
      return false
    if (active === '1')
      return Boolean(item.is_active)
    if (active === '0')
      return !item.is_active
    return true
  })
})

const load = async () => {
  all.value = asList(await $api('/inventory/categories'))
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/inventory/categories', { method: 'POST', body: form.value })
    formOpen.value = false
    form.value = { name: '', parent_id: null }
    await load()
  })
}

list.sync(load)
const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Categories"
      subtitle="Medicines, supplies, consumables, and equipment"
    >
      <HExportActions
        dataset="inventory-categories"
        :query="list.apiQuery()"
        :disabled="pending"
      />
      <HButton
        v-if="ability.can('create', 'Inventory')"
        @click="formOpen = true"
      >
        <HIcon name="plus" />
        Add category
      </HButton>
    </HPage>
    <HCard flush>
      <HListToolbar
        v-model:search="q"
        v-model:values="filterValues"
        search-placeholder="Search categories"
        search-button
        :result-count="list.resultCount({ total: rows.length })"
        :filters="[
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
        :headers="[{ title: 'Category', key: 'name', fill: true }, { title: 'Items', key: 'items_count' }]"
        :items="rows"
        empty="No categories"
      >
        <template #cell-name="{ item }">
          <HCell :secondary="item.parent?.name">
            {{ item.name }}
          </HCell>
        </template>
      </HTable>
    </HCard>
    <HModal
      v-model="formOpen"
      title="Add category"
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
          v-model="form.parent_id"
          :items="all"
          item-title="name"
          item-value="id"
          label="Parent"
          clearable
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
