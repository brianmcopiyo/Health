<script setup>
import { formatDate, formatQty } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const batches = ref([])
const meta = ref(asPageMeta())
const page = ref(1)

const load = async () => {
  const payload = await $api('/inventory/batches', { query: { page: page.value } })
  batches.value = asList(payload)
  meta.value = asPageMeta(payload)
}

const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Batches & expiry"
      subtitle="Lot tracking and expiry monitoring"
    />
    <HCard flush>
      <HTable
        :loading="pending"
        :headers="[
          { title: 'Batch', key: 'batch_number' },
          { title: 'Item', key: 'item.name' },
          { title: 'Store', key: 'store.name' },
          { title: 'Expiry', key: 'expiry_date' },
          { title: 'Qty', key: 'quantity' },
          { title: 'Status', key: 'status' },
        ]"
        :items="batches"
        empty="No batches"
      >
        <template #cell-batch_number="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'inventory-batches-id', params: { id: item.id } }"
          >
            {{ item.batch_number }}
          </RouterLink>
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
        @update:page="value => { page = value; load() }"
      />
    </HCard>
  </div>
</template>
