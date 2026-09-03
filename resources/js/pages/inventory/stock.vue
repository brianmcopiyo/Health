<script setup>
import { formatQty } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const balances = ref([])
const stores = ref([])
const meta = ref(asPageMeta())
const list = useListQuery(['store_id', 'low_stock'])
const { page, q, filterValues } = list

const load = async () => {
  stores.value = asList(await $api('/inventory/stores'))
  const payload = await $api('/inventory/stock', { query: list.apiQuery() })
  balances.value = asList(payload)
  meta.value = asPageMeta(payload)
}

list.sync(load)
const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Stock on hand"
      subtitle="Balances by store"
    >
      <HExportActions
        dataset="inventory-stock"
        :query="list.apiQuery()"
        :disabled="pending"
      />
      <HButton
        variant="ghost"
        to="inventory"
      >
        Dashboard
      </HButton>
    </HPage>
    <HCard flush>
      <HListToolbar
        v-model:search="q"
        v-model:values="filterValues"
        search-placeholder="Search items"
        search-button
        :result-count="list.resultCount(meta)"
        :filters="[
          { key: 'store_id', type: 'select', label: 'Store', placeholder: 'All stores', items: stores, itemTitle: 'name', itemValue: 'id', optional: true, empty: null },
          { key: 'low_stock', type: 'select', label: 'Stock', placeholder: 'All stock', optional: true, empty: null, items: [
            { title: 'Low stock', value: '1' },
          ] },
        ]"
        @search="list.onSearch(load)"
        @change="list.onChange(load)"
      />
      <HTable
        :loading="pending"
        :headers="[
          { title: 'Item', key: 'item.name', fill: true },
          { title: 'On hand', key: 'quantity' },
          { title: 'Value', key: 'value' },
          { title: 'Status', key: 'status' },
        ]"
        :items="balances"
        empty="No stock balances"
      >
        <template #cell-item.name="{ item }">
          <HCell
            :to="{ name: 'inventory-items-id', params: { id: item.item_id } }"
            :secondary="joinContext(item.item?.sku, item.store?.name)"
          >
            {{ item.item?.name }}
          </HCell>
        </template>
        <template #cell-quantity="{ item }">
          {{ formatQty(item.quantity) }}
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => list.onPage(value, load)"
      />
    </HCard>
  </div>
</template>
