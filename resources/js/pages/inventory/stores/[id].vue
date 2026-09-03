<script setup>
import { formatDate, formatQty, formatWhen } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const route = useRoute()
const record = ref(null)
const tab = ref('stock')
const tabs = [
  { title: 'Stock', value: 'stock' },
  { title: 'Locations', value: 'locations' },
  { title: 'Batches', value: 'batches' },
  { title: 'Movements', value: 'movements' },
]

const load = async () => {
  record.value = await $api(`/inventory/stores/${route.params.id}`)
}

const { pending, run } = usePageQuery(load)
watch(() => route.params.id, () => run())
</script>

<template>
  <HRecord
    :title="record?.name || 'Store'"
    :subtitle="record ? [record.type, record.department?.name].filter(Boolean).join(' · ') : ''"
    :back="{ name: 'inventory-stores' }"
    back-label="Stores"
    :tabs="tabs"
    :tab="tab"
    :loading="pending"
    :missing="!pending && !record"
    @update:tab="tab = $event"
  >
    <template v-if="record && tab === 'stock'">
      <HCard
        title="On hand"
        flush
      >
        <HTable
          :headers="[{ title: 'Item', key: 'item.name', fill: true }, { title: 'Qty', key: 'quantity' }]"
          :items="record.stock || []"
          empty="No stock in this store"
        >
          <template #cell-item.name="{ item }">
            <HCell
              :to="item.item_id ? { name: 'inventory-items-id', params: { id: item.item_id } } : null"
              :secondary="item.item?.sku"
            >
              {{ item.item?.name }}
            </HCell>
          </template>
          <template #cell-quantity="{ item }">
            {{ formatQty(item.quantity) }}
          </template>
        </HTable>
      </HCard>
    </template>
    <template v-else-if="record && tab === 'locations'">
      <HCard
        title="Locations"
        flush
      >
        <HTable
          :headers="[{ title: 'Location', key: 'name', fill: true }]"
          :items="record.locations || []"
          empty="No locations in this store"
        >
          <template #cell-name="{ item }">
            <HCell>
              {{ item.name }}
            </HCell>
          </template>
        </HTable>
      </HCard>
    </template>
    <template v-else-if="record && tab === 'batches'">
      <HCard
        title="Open batches"
        flush
      >
        <HTable
          :headers="[{ title: 'Batch', key: 'batch_number', fill: true }, { title: 'Expiry', key: 'expiry_date' }, { title: 'Qty', key: 'quantity' }]"
          :items="record.batches || []"
          empty="No open batches"
        >
          <template #cell-batch_number="{ item }">
            <HCell :secondary="item.item?.name">
              {{ item.batch_number }}
            </HCell>
          </template>
          <template #cell-expiry_date="{ item }">
            {{ formatDate(item.expiry_date) }}
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
          :headers="[{ title: 'When', key: 'occurred_at' }, { title: 'Item', key: 'item.name', fill: true }, { title: 'Type', key: 'type' }, { title: 'Qty', key: 'quantity' }]"
          :items="record.movements || []"
          empty="No movements"
        >
          <template #cell-item.name="{ item }">
            <HCell :secondary="item.item?.sku">
              {{ item.item?.name }}
            </HCell>
          </template>
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
