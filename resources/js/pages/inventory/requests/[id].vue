<script setup>
import { formatQty, formatWhen } from '@/utils/helpers'
import { labelize } from '@/utils/status'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const route = useRoute()
const record = ref(null)

const load = async () => {
  record.value = await $api(`/inventory/requests/${route.params.id}`)
}

const { pending, run } = usePageQuery(load)
watch(() => route.params.id, () => run())
</script>

<template>
  <HRecord
    :title="record?.reference || 'Request'"
    :subtitle="record?.to_store?.name || ''"
    :status="record?.status"
    :back="{ name: 'inventory-requests' }"
    back-label="Requests"
    :loading="pending"
    :missing="!pending && !record"
  >
    <template v-if="record">
      <HCard title="Request">
        <div class="h-metric">
          <span>Status</span>
          <strong>{{ labelize(record.status) }}</strong>
        </div>
        <div class="h-metric">
          <span>When</span>
          <strong>{{ formatWhen(record.requested_at) }}</strong>
        </div>
      </HCard>
      <HCard
        title="Lines"
        flush
      >
        <HTable
          :headers="[{ title: 'Item', key: 'item.name', fill: true }, { title: 'Qty', key: 'quantity' }]"
          :items="record.items || []"
          empty="No lines"
        >
          <template #cell-item.name="{ item }">
            <HCell :secondary="item.item?.sku">
              {{ item.item?.name }}
            </HCell>
          </template>
          <template #cell-quantity="{ item }">
            {{ formatQty(item.quantity) }}
          </template>
        </HTable>
      </HCard>
    </template>
  </HRecord>
</template>
