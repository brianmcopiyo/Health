<script setup>
import { formatWhen } from '@/utils/helpers'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const ability = useAbility()
const rows = ref([])
const meta = ref(asPageMeta())
const stores = ref([])
const list = useListQuery(['from_store_id', 'to_store_id', 'from', 'to'])
const { page, q, filterValues } = list
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const catalog = ref([])
const form = ref({ from_store_id: null, to_store_id: null, items: [{ item_id: null, quantity: 1 }] })

const load = async () => {
  stores.value = asList(await $api('/inventory/stores'))
  const payload = await $api('/inventory/returns', { query: list.apiQuery() })
  rows.value = asList(payload)
  meta.value = asPageMeta(payload)
}

const openCreate = async () => {
  formError.value = ''
  stores.value = asList(await $api('/inventory/stores'))
  catalog.value = asList(await $api('/inventory/items', { query: { per_page: 100 } }))
  form.value = { from_store_id: stores.value.find(item => item.type === 'ward')?.id || stores.value[0]?.id, to_store_id: stores.value.find(item => item.is_default)?.id || null, items: [{ item_id: null, quantity: 1 }] }
  formOpen.value = true
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/inventory/returns', { method: 'POST', body: { ...form.value, items: form.value.items.filter(item => item.item_id) } })
    formOpen.value = false
    await load()
  })
}

list.sync(load)
const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage title="Stock returns" subtitle="Return unused stock to pharmacy or store">
      <HExportActions
        dataset="inventory-returns"
        :query="list.apiQuery()"
        :disabled="pending"
      />
      <HButton
        v-if="ability.can('create', 'Inventory')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Return stock
      </HButton>
    </HPage>
    <HCard flush>
      <HListToolbar
        v-model:search="q"
        v-model:values="filterValues"
        search-placeholder="Search returns"
        search-button
        :result-count="list.resultCount(meta)"
        :filters="[
          { key: 'from_store_id', type: 'select', label: 'From store', placeholder: 'All stores', items: stores, itemTitle: 'name', itemValue: 'id', optional: true, empty: null },
          { key: 'to_store_id', type: 'select', label: 'To store', placeholder: 'All stores', items: stores, itemTitle: 'name', itemValue: 'id', optional: true, empty: null },
          { key: 'from', type: 'date', label: 'From', optional: true, empty: null, more: true },
          { key: 'to', type: 'date', label: 'To', optional: true, empty: null, more: true },
        ]"
        @search="list.onSearch(load)"
        @change="list.onChange(load)"
      />
      <HTable
        :loading="pending"
        :headers="[{ title: 'Return', key: 'reference', fill: true }, { title: 'When', key: 'occurred_at' }]"
        :items="rows"
        empty="No returns"
      >
        <template #cell-reference="{ item }">
          <HCell
            :to="{ name: 'inventory-returns-id', params: { id: item.id } }"
            :secondary="joinContext(item.from_store?.name, item.to_store?.name)"
          >
            {{ item.reference }}
          </HCell>
        </template>
        <template #cell-occurred_at="{ item }">
          {{ formatWhen(item.occurred_at) }}
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => list.onPage(value, load)"
      />
    </HCard>
    <HOffcanvas
      v-model="formOpen"
      title="Return stock"
      :error="formError"
      :persistent="saving"
    >
      <HFormGrid>
        <HSelect
          v-model="form.from_store_id"
          :items="stores"
          item-title="name"
          item-value="id"
          label="From"
        />
        <HSelect
          v-model="form.to_store_id"
          :items="stores"
          item-title="name"
          item-value="id"
          label="To"
        />
      </HFormGrid>
      <div
        v-for="(line, index) in form.items"
        :key="index"
        class="h-form-grid"
      >
        <HSelect
          v-model="line.item_id"
          :items="catalog"
          item-title="name"
          item-value="id"
          label="Item"
        />
        <HNumber
          v-model="line.quantity"
          label="Quantity"
          :min="0.001"
        />
      </div>
      <HButton
        variant="ghost"
        @click="form.items.push({ item_id: null, quantity: 1 })"
      >
        Add line
      </HButton>
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
          Return
        </HButton>
      </template>
    </HOffcanvas>
  </div>
</template>
