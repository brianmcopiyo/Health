<script setup>
import { formatQty, formatWhen } from '@/utils/helpers'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const route = useRoute()
const record = ref(null)

const load = async () => {
  record.value = await $api(`/inventory/counts/${route.params.id}`)
}

const { pending, run } = usePageQuery(load)
watch(() => route.params.id, () => run())
</script>

<template>
  <HRecord
    :title="record?.reference || 'Count'"
    :subtitle="record?.store?.name || ''"
    :back="{ name: 'inventory-counts' }"
    back-label="Counts"
    :loading="pending"
    :missing="!pending && !record"
  >
    <template v-if="record">
      <HCard title="Count">
        <div class="h-metric">
          <span>When</span>
          <strong>{{ formatWhen(record.counted_at) }}</strong>
        </div>
      </HCard>
      <HCard
        title="Variance"
        flush
      >
        <HTable
          :headers="[{ title: 'Item', key: 'item.name', fill: true }, { title: 'System', key: 'system_quantity' }, { title: 'Counted', key: 'counted_quantity' }, { title: 'Variance', key: 'variance' }]"
          :items="record.items || []"
          empty="No lines"
        >
          <template #cell-item.name="{ item }">
            <HCell :secondary="item.item?.sku">
              {{ item.item?.name }}
            </HCell>
          </template>
          <template #cell-system_quantity="{ item }">
            {{ formatQty(item.system_quantity) }}
          </template>
          <template #cell-counted_quantity="{ item }">
            {{ formatQty(item.counted_quantity) }}
          </template>
          <template #cell-variance="{ item }">
            {{ formatQty(item.variance) }}
          </template>
        </HTable>
      </HCard>
    </template>
  </HRecord>
</template>
