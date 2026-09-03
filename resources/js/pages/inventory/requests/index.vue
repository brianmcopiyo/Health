<script setup>
import { formatWhen } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const ability = useAbility()
const rows = ref([])
const meta = ref(asPageMeta())
const stores = ref([])
const departments = ref([])
const list = useListQuery(['status', 'to_store_id', 'department_id', 'from', 'to'])
const { page, q, filterValues } = list
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const catalog = ref([])
const form = ref({ to_store_id: null, items: [{ item_id: null, quantity: 1 }] })

const load = async () => {
  stores.value = asList(await $api('/inventory/stores'))
  departments.value = asList(await $api('/departments').catch(() => []))
  const payload = await $api('/inventory/requests', { query: list.apiQuery() })
  rows.value = asList(payload)
  meta.value = asPageMeta(payload)
}

const openCreate = async () => {
  formError.value = ''
  stores.value = asList(await $api('/inventory/stores'))
  catalog.value = asList(await $api('/inventory/items', { query: { per_page: 100 } }))
  form.value = { to_store_id: stores.value.find(item => item.is_default)?.id || null, items: [{ item_id: null, quantity: 1 }] }
  formOpen.value = true
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/inventory/requests', { method: 'POST', body: { ...form.value, items: form.value.items.filter(item => item.item_id) } })
    formOpen.value = false
    await load()
  })
}

list.sync(load)
const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage title="Stock requests" subtitle="Request stock from pharmacy or central store">
      <HExportActions
        dataset="inventory-requests"
        :query="list.apiQuery()"
        :disabled="pending"
      />
      <HButton
        v-if="ability.can('create', 'Inventory')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Request stock
      </HButton>
    </HPage>
    <HCard flush>
      <HListToolbar
        v-model:search="q"
        v-model:values="filterValues"
        search-placeholder="Search requests"
        search-button
        :result-count="list.resultCount(meta)"
        :filters="[
          { key: 'status', type: 'select', label: 'Status', placeholder: 'All statuses', optional: true, empty: null, items: [
            { title: 'Requested', value: 'requested' },
            { title: 'Issued', value: 'issued' },
          ] },
          { key: 'to_store_id', type: 'select', label: 'Store', placeholder: 'All stores', items: stores, itemTitle: 'name', itemValue: 'id', optional: true, empty: null },
          { key: 'department_id', type: 'select', label: 'Department', placeholder: 'All departments', items: departments, itemTitle: 'name', itemValue: 'id', optional: true, empty: null, more: true },
          { key: 'from', type: 'date', label: 'From', optional: true, empty: null, more: true },
          { key: 'to', type: 'date', label: 'To', optional: true, empty: null, more: true },
        ]"
        @search="list.onSearch(load)"
        @change="list.onChange(load)"
      />
      <HTable
        :loading="pending"
        :headers="[{ title: 'Request', key: 'reference', fill: true }, { title: 'Status', key: 'status' }, { title: 'When', key: 'requested_at' }]"
        :items="rows"
        empty="No requests"
      >
        <template #cell-reference="{ item }">
          <HCell
            :to="{ name: 'inventory-requests-id', params: { id: item.id } }"
            :secondary="item.to_store?.name"
          >
            {{ item.reference }}
          </HCell>
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-requested_at="{ item }">
          {{ formatWhen(item.requested_at) }}
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => list.onPage(value, load)"
      />
    </HCard>
    <HOffcanvas
      v-model="formOpen"
      title="Request stock"
      :error="formError"
      :persistent="saving"
    >
      <HFormGrid>
        <HSelect
          v-model="form.to_store_id"
          :items="stores"
          item-title="name"
          item-value="id"
          label="Supplying store"
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
          Submit
        </HButton>
      </template>
    </HOffcanvas>
  </div>
</template>
