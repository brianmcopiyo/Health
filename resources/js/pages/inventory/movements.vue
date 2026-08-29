<script setup>
import { formatQty, formatWhen } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const movements = ref([])
const meta = ref(asPageMeta())
const page = ref(1)

const load = async () => {
  const payload = await $api('/inventory/movements', { query: { page: page.value } })
  movements.value = asList(payload)
  meta.value = asPageMeta(payload)
}

const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Stock movements"
      subtitle="Auditable inventory ledger"
    />
    <HCard flush>
      <HTable
        :loading="pending"
        :headers="[
          { title: 'When', key: 'occurred_at' },
          { title: 'Item', key: 'item.name' },
          { title: 'Store', key: 'store.name' },
          { title: 'Type', key: 'type' },
          { title: 'Qty', key: 'quantity' },
        ]"
        :items="movements"
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
        <template #cell-quantity="{ item }">
          {{ formatQty(item.quantity) }}
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => { page = value; load() }"
      />
    </HCard>
  </div>
</template>
