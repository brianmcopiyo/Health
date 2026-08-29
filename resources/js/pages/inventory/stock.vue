<script setup>
import { formatQty } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const balances = ref([])
const stores = ref([])
const meta = ref(asPageMeta())
const page = ref(1)
const q = ref('')
const storeId = ref(null)

const load = async () => {
  stores.value = asList(await $api('/inventory/stores'))
  const payload = await $api('/inventory/stock', { query: { page: page.value, q: q.value || undefined, store_id: storeId.value || undefined } })
  balances.value = asList(payload)
  meta.value = asPageMeta(payload)
}

const filterValues = computed({
  get: () => ({ store_id: storeId.value }),
  set: next => { storeId.value = next.store_id },
})

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
        :query="{ q: q || undefined, store_id: storeId || undefined }"
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
        :filters="[{ key: 'store_id', type: 'select', label: 'Store', placeholder: 'All stores', items: stores, itemTitle: 'name', itemValue: 'id' }]"
        @search="page = 1; load()"
        @change="page = 1; load()"
      />
      <HTable
        :loading="pending"
        :headers="[
          { title: 'Item', key: 'item.name' },
          { title: 'Store', key: 'store.name' },
          { title: 'On hand', key: 'quantity' },
          { title: 'Value', key: 'value' },
          { title: 'Status', key: 'status' },
        ]"
        :items="balances"
        empty="No stock balances"
      >
        <template #cell-item.name="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'inventory-items-id', params: { id: item.item_id } }"
          >
            {{ item.item?.name }}
          </RouterLink>
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
        @update:page="value => { page = value; load() }"
      />
    </HCard>
  </div>
</template>
