<script setup>
import { formatQty, formatWhen } from '@/utils/helpers'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const route = useRoute()
const record = ref(null)

const load = async () => {
  record.value = await $api(`/inventory/receipts/${route.params.id}`)
}

const { pending, run } = usePageQuery(load)
watch(() => route.params.id, () => run())
</script>

<template>
  <HRecord
    :title="record?.reference || 'Receipt'"
    :subtitle="record?.store?.name || ''"
    :back="{ name: 'inventory-receipts' }"
    back-label="Receipts"
    :loading="pending"
    :missing="!pending && !record"
  >
    <template v-if="record">
      <HCard title="Receipt">
        <div class="h-metric">
          <span>Received</span>
          <strong>{{ formatWhen(record.received_at) }}</strong>
        </div>
        <div class="h-metric">
          <span>Supplier</span>
          <strong>{{ record.supplier?.name || '—' }}</strong>
        </div>
      </HCard>
      <HCard
        title="Lines"
        flush
      >
        <HTable
          :headers="[{ title: 'Item', key: 'item.name' }, { title: 'Batch', key: 'batch.batch_number' }, { title: 'Qty', key: 'quantity' }]"
          :items="record.items || []"
          empty="No lines"
        >
          <template #cell-quantity="{ item }">
            {{ formatQty(item.quantity) }}
          </template>
        </HTable>
      </HCard>
    </template>
  </HRecord>
</template>
