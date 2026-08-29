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
const saving = ref(false)
const formError = ref('')
const stockOpen = ref(false)
const stockForm = ref({ id: null, stock_qty: 0, reorder_level: 0 })

const load = async () => {
  prescriptions.value = asList(await $api('/prescriptions', { query: { queue: true, per_page: 50 } }))
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

const { pending } = usePageQuery(load)
const rxHeaders = [
  { title: 'Patient', key: 'patient.first_name' },
  { title: 'Medicines', key: 'items' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions' },
]
const stockHeaders = [
  { title: 'Medicine', key: 'name' },
  { title: 'Strength', key: 'strength' },
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
      <HTable
        :loading="pending"
        :headers="rxHeaders"
        :items="prescriptions"
        empty="No prescriptions in the pharmacy queue"
      >
        <template #cell-patient.first_name="{ item }">
          <RouterLink
            v-if="item.patient?.id"
            class="h-inline-link"
            :to="{ name: 'patients-id', params: { id: item.patient.id } }"
          >
            {{ item.patient.first_name }} {{ item.patient.last_name }}
          </RouterLink>
          <span v-else>—</span>
        </template>
        <template #cell-items="{ item }">
          {{ (item.items || []).map(row => row.medication?.name).join(', ') }}
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <div class="h-actions">
            <HButton
              v-if="ability.can('update', 'Pharmacy') && item.status === 'pending'"
              size="sm"
              variant="ghost"
              :disabled="saving"
              @click="updateRx(item, 'verified')"
            >
              Verify
            </HButton>
            <HButton
              v-if="ability.can('update', 'Pharmacy') && item.status !== 'dispensed' && item.status !== 'cancelled'"
              size="sm"
              :disabled="saving"
              @click="updateRx(item, 'dispensed')"
            >
              Dispense
            </HButton>
            <HButton
              v-if="ability.can('update', 'Pharmacy') && !['dispensed', 'cancelled'].includes(item.status)"
              variant="ghost"
              size="sm"
              :disabled="saving"
              @click="updateRx(item, 'cancelled')"
            >
              Cancel
            </HButton>
          </div>
        </template>
      </HTable>
    </HCard>

    <HCard
      title="Medication stock"
      flush
    >
      <HTable
        :loading="pending"
        :headers="stockHeaders"
        :items="medications"
        empty="No formulary items"
      >
        <template #cell-actions="{ item }">
          <HButton
            v-if="ability.can('update', 'Pharmacy')"
            variant="ghost"
            size="sm"
            @click="openStock(item)"
          >
            Adjust
          </HButton>
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
          :disabled="saving"
          @click="saveStock"
        >
          Save
        </HButton>
      </template>
    </HModal>
  </div>
</template>
