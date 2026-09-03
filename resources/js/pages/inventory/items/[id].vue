<script setup>
import { formatQty, formatWhen } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const route = useRoute()
const record = ref(null)
const tab = ref('overview')
const tabs = [
  { title: 'Overview', value: 'overview' },
  { title: 'Stock', value: 'stock' },
  { title: 'Movements', value: 'movements' },
]

const load = async () => {
  record.value = await $api(`/inventory/items/${route.params.id}`)
}

const { pending, run } = usePageQuery(load)
watch(() => route.params.id, () => run())
</script>

<template>
  <HRecord
    :title="record?.name || 'Item'"
    :subtitle="record?.sku || ''"
    :status="record?.status"
    :statuses="record?.is_controlled ? ['controlled'] : []"
    :back="{ name: 'inventory-items' }"
    back-label="Items"
    :tabs="tabs"
    :tab="tab"
    :loading="pending"
    :missing="!pending && !record"
    @update:tab="tab = $event"
  >
    <template v-if="record && tab === 'overview'">
      <div class="h-detail">
        <HCard title="Item">
          <div class="h-metric">
            <span>Type</span>
            <strong>{{ labelize(record.kind) }}</strong>
          </div>
          <div class="h-metric">
            <span>On hand</span>
            <strong>{{ formatQty(record.stock_quantity) }}</strong>
          </div>
          <div class="h-metric">
            <span>Reorder</span>
            <strong>{{ record.reorder_level }}</strong>
          </div>
        </HCard>
      </div>
    </template>
    <template v-else-if="record && tab === 'stock'">
      <HCard
        title="Store balances"
        flush
      >
        <HTable
          :headers="[{ title: 'Store', key: 'store.name', fill: true }, { title: 'Qty', key: 'quantity' }]"
          :items="record.balances || []"
          empty="No balances"
        >
          <template #cell-store.name="{ item }">
            <HCell :secondary="item.location?.name">
              {{ item.store?.name }}
            </HCell>
          </template>
        </HTable>
      </HCard>
      <HCard
        title="Open batches"
        flush
      >
        <HTable
          :headers="[{ title: 'Batch', key: 'batch_number', fill: true }, { title: 'Expiry', key: 'expiry_date' }, { title: 'Qty', key: 'quantity' }, { title: 'Status', key: 'status' }]"
          :items="record.batches || []"
          empty="No open batches"
        >
          <template #cell-batch_number="{ item }">
            <HCell :secondary="item.store?.name">
              {{ item.batch_number }}
            </HCell>
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
        </HTable>
      </HCard>
    </template>
    <template v-else-if="record">
      <HCard
        title="Movements"
        flush
      >
        <HTable
          :headers="[{ title: 'When', key: 'occurred_at' }, { title: 'Type', key: 'type' }, { title: 'Qty', key: 'quantity' }]"
          :items="record.movements || []"
          empty="No movements"
        >
          <template #cell-occurred_at="{ item }">
            {{ formatWhen(item.occurred_at) }}
          </template>
          <template #cell-type="{ item }">
            <HBadge :tone="statusColor(item.type)">
              {{ labelize(item.type) }}
            </HBadge>
          </template>
        </HTable>
      </HCard>
    </template>
  </HRecord>
</template>
