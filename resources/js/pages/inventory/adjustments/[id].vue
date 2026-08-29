<script setup>
import { formatQty, formatWhen } from '@/utils/helpers'
import { labelize } from '@/utils/status'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const route = useRoute()
const record = ref(null)

const load = async () => {
  record.value = await $api(`/inventory/adjustments/${route.params.id}`)
}

const { pending, run } = usePageQuery(load)
watch(() => route.params.id, () => run())
</script>

<template>
  <HRecord
    :title="record?.reference || 'Adjustment'"
    :subtitle="record?.store?.name || ''"
    :back="{ name: 'inventory-adjustments' }"
    back-label="Adjustments"
    :loading="pending"
    :missing="!pending && !record"
  >
    <template v-if="record">
      <HCard title="Adjustment">
        <div class="h-metric">
          <span>Reason</span>
          <strong>{{ labelize(record.reason) }}</strong>
        </div>
        <div class="h-metric">
          <span>When</span>
          <strong>{{ formatWhen(record.occurred_at) }}</strong>
        </div>
      </HCard>
      <HCard
        title="Lines"
        flush
      >
        <HTable
          :headers="[{ title: 'Item', key: 'item.name' }, { title: 'Direction', key: 'direction' }, { title: 'Qty', key: 'quantity' }]"
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
