<script setup>
definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const ability = useAbility()
const rows = ref([])
const meta = ref(asPageMeta())
const stores = ref([])
const list = useListQuery(['store_id', 'is_active'])
const { page, q, filterValues } = list
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const form = ref({ store_id: null, name: '' })

const load = async () => {
  stores.value = asList(await $api('/inventory/stores'))
  const payload = await $api('/inventory/locations', { query: list.apiQuery() })
  rows.value = asList(payload)
  meta.value = asPageMeta(payload)
}

const openCreate = () => {
  formError.value = ''
  form.value = { store_id: list.values.store_id || stores.value[0]?.id || null, name: '' }
  formOpen.value = true
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/inventory/locations', { method: 'POST', body: form.value })
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
      title="Stock locations"
      subtitle="Bins and shelves inside stores"
    >
      <HExportActions
        dataset="inventory-locations"
        :query="list.apiQuery()"
        :disabled="pending"
      />
      <HButton
        v-if="ability.can('create', 'Inventory')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Add location
      </HButton>
    </HPage>
    <HCard flush>
      <HListToolbar
        v-model:search="q"
        v-model:values="filterValues"
        search-placeholder="Search locations"
        search-button
        :result-count="list.resultCount(meta)"
        :filters="[
          { key: 'store_id', type: 'select', label: 'Store', placeholder: 'All stores', items: stores, itemTitle: 'name', itemValue: 'id', optional: true, empty: null },
          { key: 'is_active', type: 'select', label: 'Status', placeholder: 'All statuses', optional: true, empty: null, more: true, items: [
            { title: 'Active', value: '1' },
            { title: 'Inactive', value: '0' },
          ] },
        ]"
        @search="list.onSearch(load)"
        @change="list.onChange(load)"
      />
      <HTable
        :loading="pending"
        :headers="[{ title: 'Location', key: 'name', fill: true }]"
        :items="rows"
        empty="No locations"
      >
        <template #cell-name="{ item }">
          <HCell :secondary="item.store?.name">
            {{ item.name }}
          </HCell>
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => list.onPage(value, load)"
      />
    </HCard>
    <HModal
      v-model="formOpen"
      title="Add location"
      :error="formError"
      :persistent="saving"
    >
      <HFormGrid>
        <HSelect
          v-model="form.store_id"
          :items="stores"
          item-title="name"
          item-value="id"
          label="Store"
        />
        <HInput
          v-model="form.name"
          label="Name"
          required
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
