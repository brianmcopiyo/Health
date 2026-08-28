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
  <VCard>
    <VCardItem>
      <VCardTitle>Billing</VCardTitle>
      <template #append>
        <VBtn
          v-if="ability.can('create', 'Invoice')"
          prepend-icon="tabler-plus"
          @click="isDialogVisible = true"
        >
          New invoice
        </VBtn>
      </template>
    </VCardItem>
    <VDataTable
      :headers="[
        { title: 'Number', key: 'number' },
        { title: 'Patient', key: 'patient.first_name' },
        { title: 'Total', key: 'total' },
        { title: 'Status', key: 'status' },
        { title: 'Actions', key: 'actions', sortable: false },
      ]"
      :items="invoices"
    >
      <template #item.patient.first_name="{ item }">
        {{ item.patient?.first_name }} {{ item.patient?.last_name }}
      </template>
      <template #item.status="{ item }">
        <VChip
          size="small"
          :color="statusColor(item.status)"
          class="text-capitalize"
        >
          {{ labelize(item.status) }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <VBtn
          v-if="ability.can('update', 'Invoice') && item.status === 'draft'"
          size="small"
          class="me-2"
          @click="updateStatus(item, 'issued')"
        >
          Issue
        </VBtn>
        <VBtn
          v-if="ability.can('update', 'Invoice') && item.status !== 'paid'"
          size="small"
          variant="tonal"
          @click="updateStatus(item, 'paid')"
        >
          Mark paid
        </VBtn>
      </template>
    </VDataTable>
  </VCard>

  <VDialog
    v-model="isDialogVisible"
    max-width="720"
  >
    <VCard title="Create invoice">
      <VCardText>
        <AppSelect
          v-model="form.patient_id"
          :items="patients"
          item-title="full_name"
          item-value="id"
          label="Patient"
          class="mb-4"
        />
        <div
          v-for="(item, index) in form.items"
          :key="index"
          class="d-flex gap-4 mb-4"
        >
          <AppTextField
            v-model="item.description"
            label="Description"
          />
          <AppTextField
            v-model.number="item.quantity"
            type="number"
            label="Qty"
          />
          <AppTextField
            v-model.number="item.unit_amount"
            type="number"
            label="Unit amount"
          />
        </div>
        <VBtn
          variant="tonal"
          @click="addItem"
        >
          Add line
        </VBtn>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          @click="isDialogVisible = false"
        >
          Cancel
        </VBtn>
        <VBtn
          :disabled="!form.patient_id"
          @click="save"
        >
          Save
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
