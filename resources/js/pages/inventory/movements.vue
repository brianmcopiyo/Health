<script setup>
import { formatQty, formatWhen } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const movements = ref([])
const stores = ref([])
const meta = ref(asPageMeta())
const list = useListQuery(['type', 'store_id', 'from', 'to'])
const { page, q, filterValues } = list

const load = async () => {
  stores.value = asList(await $api('/inventory/stores'))
  const payload = await $api('/inventory/movements', { query: list.apiQuery() })
  movements.value = asList(payload)
  meta.value = asPageMeta(payload)
}

list.sync(load)
const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Stock movements"
      subtitle="Auditable inventory ledger"
    >
      <HExportActions
        dataset="inventory-movements"
        :query="list.apiQuery()"
        :disabled="pending"
      />
    </HPage>
    <HCard flush>
      <HListToolbar
        v-model:search="q"
        v-model:values="filterValues"
        search-placeholder="Search item"
        search-button
        :result-count="list.resultCount(meta)"
        :filters="[
          { key: 'type', type: 'select', label: 'Type', placeholder: 'All types', optional: true, empty: null, items: [
            { title: 'Receive', value: 'receive' },
            { title: 'Issue', value: 'issue' },
            { title: 'Dispense', value: 'dispense' },
            { title: 'Transfer in', value: 'transfer_in' },
            { title: 'Transfer out', value: 'transfer_out' },
            { title: 'Adjustment in', value: 'adjustment_in' },
            { title: 'Adjustment out', value: 'adjustment_out' },
            { title: 'Return in', value: 'return_in' },
            { title: 'Return out', value: 'return_out' },
            { title: 'Count in', value: 'count_in' },
            { title: 'Count out', value: 'count_out' },
            { title: 'Opening', value: 'opening' },
          ] },
          { key: 'store_id', type: 'select', label: 'Store', placeholder: 'All stores', items: stores, itemTitle: 'name', itemValue: 'id', optional: true, empty: null },
          { key: 'from', type: 'date', label: 'From', optional: true, empty: null, more: true },
          { key: 'to', type: 'date', label: 'To', optional: true, empty: null, more: true },
        ]"
        @search="list.onSearch(load)"
        @change="list.onChange(load)"
      />
      <HTable
        :loading="pending"
        :headers="[
          { title: 'Item', key: 'item.name', fill: true },
          { title: 'Type', key: 'type' },
          { title: 'Qty', key: 'quantity' },
          { title: 'When', key: 'occurred_at' },
        ]"
        :items="movements"
        empty="No movements"
      >
        <template #cell-occurred_at="{ item }">
          {{ formatWhen(item.occurred_at) }}
        </template>
        <template #cell-item.name="{ item }">
          <HCell :secondary="joinContext(item.item?.sku, item.store?.name)">
            {{ item.item?.name }}
          </HCell>
        </template>
        <template #cell-type="{ item }">
          <HBadge :tone="statusColor(item.type)">
            {{ labelize(item.type) }}
          </HBadge>
        </template>
        <template #cell-quantity="{ item }">
          {{ formatQty(item.quantity) }}
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => list.onPage(value, load)"
      />
    </HCard>
  </div>
</template>
