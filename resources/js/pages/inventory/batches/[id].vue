<script setup>
import { formatDate, formatQty, formatWhen } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const route = useRoute()
const record = ref(null)

const load = async () => {
  record.value = await $api(`/inventory/batches/${route.params.id}`)
}

const { pending, run } = usePageQuery(load)
watch(() => route.params.id, () => run())
</script>

<template>
  <HRecord
    :title="record?.batch_number || 'Batch'"
    :subtitle="record?.item?.name || ''"
    :status="record?.status"
    :back="{ name: 'inventory-batches' }"
    back-label="Batches"
    :loading="pending"
    :missing="!pending && !record"
  >
    <template v-if="record">
      <HCard title="Batch">
        <div class="h-metric">
          <span>Store</span>
          <strong>{{ record.store?.name || '—' }}</strong>
        </div>
        <div class="h-metric">
          <span>Expiry</span>
          <strong>{{ formatDate(record.expiry_date) }}</strong>
        </div>
        <div class="h-metric">
          <span>On hand</span>
          <strong>{{ formatQty(record.quantity) }}</strong>
        </div>
        <div class="h-metric">
          <span>Status</span>
          <HBadge :tone="statusColor(record.status)">
            {{ labelize(record.status) }}
          </HBadge>
        </div>
      </HCard>
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
