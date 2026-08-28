<script setup>
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Invoice',
  },
})

const ability = useAbility()
const invoices = ref([])
const patients = ref([])
const isDialogVisible = ref(false)
const form = ref({
  patient_id: null,
  items: [{ description: '', quantity: 1, unit_amount: 0 }],
})

const load = async () => {
  invoices.value = asList(await $api('/invoices'))
  if (ability.can('create', 'Invoice'))
    patients.value = asList(await $api('/patients'))
}

const addItem = () => {
  form.value.items.push({ description: '', quantity: 1, unit_amount: 0 })
}

const save = async () => {
  await $api('/invoices', { method: 'POST', body: form.value })
  isDialogVisible.value = false
  form.value = { patient_id: null, items: [{ description: '', quantity: 1, unit_amount: 0 }] }
  await load()
}

const updateStatus = async (invoice, status) => {
  await $api(`/invoices/${invoice.id}/status`, { method: 'PATCH', body: { status } })
  await load()
}

await withPageLoad(load)
</script>

<template>
  <div>
    <HPage
      title="Billing"
      subtitle="Invoices and payment status"
    >
      <HButton
        v-if="ability.can('create', 'Invoice')"
        @click="isDialogVisible = true"
      >
        <HIcon name="plus" />
        New invoice
      </HButton>
    </HPage>

    <HCard>
      <HTable
        :headers="[
          { title: 'Number', key: 'number' },
          { title: 'Patient', key: 'patient.first_name' },
          { title: 'Total', key: 'total' },
          { title: 'Status', key: 'status' },
          { title: 'Actions', key: 'actions' },
        ]"
        :items="invoices"
        empty="No invoices yet"
      >
        <template #cell-patient.first_name="{ item }">
          {{ item.patient?.first_name }} {{ item.patient?.last_name }}
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <div class="h-actions">
            <HButton
              v-if="ability.can('update', 'Invoice') && item.status === 'draft'"
              size="sm"
              @click="updateStatus(item, 'issued')"
            >
              Issue
            </HButton>
            <HButton
              v-if="ability.can('update', 'Invoice') && item.status !== 'paid'"
              variant="ghost"
              size="sm"
              @click="updateStatus(item, 'paid')"
            >
              Mark paid
            </HButton>
          </div>
        </template>
      </HTable>
    </HCard>

    <HDialog
      v-model="isDialogVisible"
      title="Create invoice"
      wide
    >
      <HSelect
        v-model="form.patient_id"
        :items="patients"
        item-title="full_name"
        item-value="id"
        label="Patient"
      />
      <div
        v-for="(item, index) in form.items"
        :key="index"
        class="h-grid cols-3"
        style="margin-top:12px"
      >
        <HInput
          v-model="item.description"
          label="Description"
        />
        <HInput
          v-model="item.quantity"
          type="number"
          label="Qty"
        />
        <HInput
          v-model="item.unit_amount"
          type="number"
          label="Unit amount"
        />
      </div>
      <HButton
        variant="ghost"
        style="margin-top:12px"
        @click="addItem"
      >
        Add line
      </HButton>
      <template #actions>
        <HButton
          variant="ghost"
          @click="isDialogVisible = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="!form.patient_id"
          @click="save"
        >
          Save
        </HButton>
      </template>
    </HDialog>
  </div>
</template>
