<script setup>
import FacilityBoard from '@/components/hms/FacilityBoard.vue'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Pharmacy',
  },
})

const ability = useAbility()
const prescriptions = ref([])
const medications = ref([])
const rxMeta = ref(asPageMeta())
const list = useListQuery(['queue', 'status'])
const { filterValues } = list
if (!list.values.queue)
  list.values.queue = '1'
const saving = ref(false)
const formError = ref('')
const stockOpen = ref(false)
const stockForm = ref({ id: null, stock_qty: 0, reorder_level: 0 })

const rxQuery = extra => {
  const query = list.apiQuery(extra)
  if (query.queue === '1' || query.queue === true || query.queue === 'true') {
    query.queue = true
    delete query.status
  }
  else {
    delete query.queue
  }
  return query
}

const load = async () => {
  const payload = await $api('/prescriptions', { query: rxQuery({ per_page: 50 }) })
  prescriptions.value = asList(payload)
  rxMeta.value = asPageMeta(payload)
  medications.value = asList(await $api('/medications'))
}

const openStock = item => {
  formError.value = ''
  stockForm.value = { id: item.id, stock_qty: item.stock_qty, reorder_level: item.reorder_level }
  stockOpen.value = true
}

const saveStock = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/medications/${stockForm.value.id}`, {
      method: 'PATCH',
      body: { stock_qty: stockForm.value.stock_qty, reorder_level: stockForm.value.reorder_level },
    })
    stockOpen.value = false
    await load()
  })
}

const updateRx = async (item, status) => {
  await wrapSave(saving, formError, async () => {
    await $api(`/prescriptions/${item.id}/status`, { method: 'PATCH', body: { status } })
    await load()
  })
}

list.sync(load)
const { pending } = usePageQuery(load)
const rxHeaders = [
  { title: 'Patient', key: 'patient.first_name', fill: true },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions' },
]
const stockHeaders = [
  { title: 'Medicine', key: 'name', fill: true },
  { title: 'Stock', key: 'stock_qty' },
  { title: 'Reorder at', key: 'reorder_level' },
  { title: 'Actions', key: 'actions' },
]
</script>

<template>
  <div>
    <FacilityBoard
      module-key="pharmacy"
      title="Pharmacy"
      subject="Pharmacy"
    />

    <div
      v-if="formError"
      class="h-alert"
    >
      {{ formError }}
    </div>

    <HCard
      title="Prescriptions awaiting dispensing"
      flush
    >
      <template #actions>
        <HExportActions
          dataset="pharmacy"
          :query="rxQuery()"
          :disabled="pending"
        />
      </template>
      <HListToolbar
        v-model:values="filterValues"
        :result-count="list.resultCount(rxMeta)"
        :filters="[
          { key: 'queue', type: 'segmented', empty: '1', options: [
            { value: '1', title: 'Open queue' },
            { value: 'all', title: 'All' },
          ] },
          { key: 'status', type: 'select', label: 'Status', placeholder: 'All statuses', optional: true, empty: null, more: true, items: [
            { title: 'Pending', value: 'pending' },
            { title: 'Verified', value: 'verified' },
            { title: 'Dispensed', value: 'dispensed' },
            { title: 'Cancelled', value: 'cancelled' },
          ] },
        ]"
        @change="list.onChange(load)"
      />
      <HTable
        :loading="pending"
        :headers="rxHeaders"
        :items="prescriptions"
        empty="No prescriptions in the pharmacy queue"
      >
        <template #cell-patient.first_name="{ item }">
          <HCell
            :to="item.patient?.id ? { name: 'patients-id', params: { id: item.patient.id } } : null"
            :secondary="(item.items || []).map(row => row.medication?.name).filter(Boolean).join(', ')"
          >
            {{ item.patient?.full_name || `${item.patient?.first_name || ''} ${item.patient?.last_name || ''}`.trim() || '—' }}
          </HCell>
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <HActionMenu
            :actions="[
              { label: 'Verify', icon: 'check', if: ability.can('update', 'Pharmacy') && item.status === 'pending', onSelect: () => updateRx(item, 'verified') },
              { label: 'Dispense', icon: 'send', if: ability.can('update', 'Pharmacy') && item.status !== 'dispensed' && item.status !== 'cancelled', onSelect: () => updateRx(item, 'dispensed') },
              { label: 'Cancel', icon: 'ban', danger: true, if: ability.can('update', 'Pharmacy') && !['dispensed', 'cancelled'].includes(item.status), onSelect: () => updateRx(item, 'cancelled') },
            ]"
          />
        </template>
      </HTable>
      <HPager
        :meta="rxMeta"
        @update:page="value => list.onPage(value, load)"
      />
    </HCard>

    <HCard
      title="Medication stock"
      flush
    >
      <template
        v-if="ability.can('read', 'Inventory')"
        #actions
      >
        <HButton
          size="sm"
          variant="ghost"
          to="inventory"
        >
          Open inventory
        </HButton>
      </template>
      <HTable
        :loading="pending"
        :headers="stockHeaders"
        :items="medications"
        empty="No formulary items"
      >
        <template #cell-name="{ item }">
          <HCell :secondary="item.strength">
            {{ item.name }}
          </HCell>
        </template>
        <template #cell-actions="{ item }">
          <HActionMenu
            :actions="[
              { label: 'Adjust', icon: 'edit', if: ability.can('update', 'Pharmacy'), onSelect: () => openStock(item) },
            ]"
          />
        </template>
      </HTable>
    </HCard>

    <HModal
      v-model="stockOpen"
      title="Adjust stock"
      :error="formError"
      :persistent="saving"
    >
      <HFormGrid>
        <HNumber
          v-model="stockForm.stock_qty"
          label="Stock quantity"
          placeholder="e.g. 120"
          :min="0"
        />
        <HNumber
          v-model="stockForm.reorder_level"
          label="Reorder level"
          placeholder="e.g. 20"
          :min="0"
        />
      </HFormGrid>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="stockOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :loading="saving"
          :disabled="saving"
          @click="saveStock"
        >
          Save
        </HButton>
      </template>
    </HModal>
  </div>
</template>
