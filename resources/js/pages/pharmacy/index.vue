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

const load = async () => {
  prescriptions.value = asList(await $api('/prescriptions', { query: { queue: true, per_page: 50 } }))
  medications.value = asList(await $api('/medications'))
}

const updateRx = async (item, status) => {
  await wrapSave(saving, formError, async () => {
    await $api(`/prescriptions/${item.id}/status`, { method: 'PATCH', body: { status } })
    await load()
  })
}

await withPageLoad(load)
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
      style="margin-top:18px"
    >
      {{ formError }}
    </div>

    <HCard
      title="Prescriptions awaiting dispensing"
      style="margin-top:18px"
    >
      <HTable
        :headers="[
          { title: 'Patient', key: 'patient.first_name' },
          { title: 'Medicines', key: 'items' },
          { title: 'Status', key: 'status' },
          { title: 'Actions', key: 'actions' },
        ]"
        :items="prescriptions"
        empty="No prescriptions in the pharmacy queue"
      >
        <template #cell-patient.first_name="{ item }">
          {{ item.patient?.first_name }} {{ item.patient?.last_name }}
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
              v-if="ability.can('update', 'Pharmacy') && item.status !== 'dispensed'"
              size="sm"
              :disabled="saving"
              @click="updateRx(item, 'dispensed')"
            >
              Dispense
            </HButton>
          </div>
        </template>
      </HTable>
    </HCard>

    <HCard
      title="Medication stock"
      style="margin-top:18px"
    >
      <HTable
        :headers="[
          { title: 'Medicine', key: 'name' },
          { title: 'Strength', key: 'strength' },
          { title: 'Stock', key: 'stock_qty' },
          { title: 'Reorder at', key: 'reorder_level' },
        ]"
        :items="medications"
        empty="No formulary items"
      />
    </HCard>
  </div>
</template>
