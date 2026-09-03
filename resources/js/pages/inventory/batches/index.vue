<script setup>
import { formatDate, formatQty } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const batches = ref([])
const stores = ref([])
const meta = ref(asPageMeta())
const list = useListQuery(['status', 'store_id', 'expiring'])
const { page, q, filterValues } = list

const load = async () => {
  stores.value = asList(await $api('/inventory/stores'))
  const payload = await $api('/inventory/batches', { query: list.apiQuery() })
  batches.value = asList(payload)
  meta.value = asPageMeta(payload)
}

list.sync(load)
const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Batches & expiry"
      subtitle="Lot tracking and expiry monitoring"
    >
      <HExportActions
        dataset="inventory-batches"
        :query="list.apiQuery()"
        :disabled="pending"
      />
    </HPage>
    <HCard flush>
      <HListToolbar
        v-model:search="q"
        v-model:values="filterValues"
        search-placeholder="Search batch or item"
        search-button
        :result-count="list.resultCount(meta)"
        :filters="[
          { key: 'status', type: 'select', label: 'Status', placeholder: 'All statuses', optional: true, empty: null, items: [
            { title: 'Available', value: 'available' },
            { title: 'Reserved', value: 'reserved' },
            { title: 'Expired', value: 'expired' },
            { title: 'Depleted', value: 'depleted' },
            { title: 'Quarantined', value: 'quarantined' },
          ] },
          { key: 'expiring', type: 'select', label: 'Expiry', placeholder: 'Any expiry', optional: true, empty: null, items: [
            { title: 'Expiring within 90 days', value: '1' },
          ] },
          { key: 'store_id', type: 'select', label: 'Store', placeholder: 'All stores', items: stores, itemTitle: 'name', itemValue: 'id', optional: true, empty: null, more: true },
        ]"
        @search="list.onSearch(load)"
        @change="list.onChange(load)"
      />
      <HTable
        :loading="pending"
        :headers="[
          { title: 'Batch', key: 'batch_number', fill: true },
          { title: 'Expiry', key: 'expiry_date' },
          { title: 'Qty', key: 'quantity' },
          { title: 'Status', key: 'status' },
        ]"
        :items="batches"
        empty="No batches"
      >
        <template #cell-batch_number="{ item }">
          <HCell
            :to="{ name: 'inventory-batches-id', params: { id: item.id } }"
            :secondary="joinContext(item.item?.name, item.store?.name)"
          >
            {{ item.batch_number }}
          </HCell>
        </template>
        <template #cell-expiry_date="{ item }">
          {{ formatDate(item.expiry_date) }}
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
