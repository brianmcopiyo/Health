<script setup>
import { formatQty, formatWhen } from '@/utils/helpers'
import { labelize } from '@/utils/status'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const route = useRoute()
const record = ref(null)

const load = async () => {
  record.value = await $api(`/inventory/issues/${route.params.id}`)
}

const { pending, run } = usePageQuery(load)
watch(() => route.params.id, () => run())
</script>

<template>
  <HRecord
    :title="record?.reference || 'Issue'"
    :subtitle="record?.store?.name || ''"
    :back="{ name: 'inventory-issues' }"
    back-label="Issues"
    :loading="pending"
    :missing="!pending && !record"
  >
    <template v-if="record">
      <HCard title="Issue">
        <div class="h-metric">
          <span>Kind</span>
          <strong>{{ labelize(record.kind) }}</strong>
        </div>
        <div class="h-metric">
          <span>Department</span>
          <strong>{{ record.department?.name || '—' }}</strong>
        </div>
        <div class="h-metric">
          <span>Patient</span>
          <strong>{{ record.patient ? [record.patient.first_name, record.patient.last_name].join(' ') : '—' }}</strong>
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
          :headers="[{ title: 'Item', key: 'item.name', fill: true }, { title: 'Qty', key: 'quantity' }]"
          :items="record.items || []"
          empty="No lines"
        >
          <template #cell-item.name="{ item }">
            <HCell :secondary="item.batch?.batch_number">
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
