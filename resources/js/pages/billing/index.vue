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
const encounters = ref([])
const formOpen = ref(false)
const payOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const form = ref({
  patient_id: null,
  encounter_id: null,
  items: [{ description: '', quantity: 1, unit_amount: 0 }],
})
const payForm = ref({
  id: null,
  amount: 0,
  method: 'cash',
})

const encounterOptions = computed(() => encounters.value.map(item => ({
  title: `${labelize(item.type)} · ${item.chief_complaint || labelize(item.status)}`,
  value: item.id,
})))

const load = async () => {
  invoices.value = asList(await $api('/invoices'))
  if (ability.can('create', 'Invoice') || ability.can('update', 'Invoice'))
    patients.value = asList(await $api('/patients'))
}

const loadEncounters = async patientId => {
  form.value.encounter_id = null
  if (!patientId) {
    encounters.value = []
    return
  }
  encounters.value = asList(await $api('/encounters', { query: { patient_id: patientId } }))
}

const openCreate = () => {
  formError.value = ''
  encounters.value = []
  form.value = { patient_id: null, encounter_id: null, items: [{ description: '', quantity: 1, unit_amount: 0 }] }
  formOpen.value = true
}

const addItem = () => {
  form.value.items.push({ description: '', quantity: 1, unit_amount: 0 })
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    const items = form.value.items.filter(item => item.description)
    await $api('/invoices', {
      method: 'POST',
      body: {
        patient_id: form.value.patient_id,
        encounter_id: form.value.encounter_id,
        ...(items.length ? { items } : {}),
      },
    })
    formOpen.value = false
    await load()
  })
}

const updateStatus = async (invoice, status) => {
  await $api(`/invoices/${invoice.id}/status`, { method: 'PATCH', body: { status } })
  await load()
}

const openPay = invoice => {
  formError.value = ''
  payForm.value = {
    id: invoice.id,
    amount: invoice.total,
    method: 'cash',
  }
  payOpen.value = true
}

const savePayment = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/invoices/${payForm.value.id}/payments`, {
      method: 'POST',
      body: {
        amount: Number(payForm.value.amount),
        method: payForm.value.method,
      },
    })
    payOpen.value = false
    await load()
  })
}

await withPageLoad(load)
</script>

<template>
  <div>
    <HPage
      title="Billing"
      subtitle="Charges from encounters, services, and payments"
    >
      <HButton
        v-if="ability.can('create', 'Invoice')"
        @click="openCreate"
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
          { title: 'Encounter', key: 'encounter.type' },
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
        <template #cell-encounter.type="{ item }">
          {{ item.encounter ? labelize(item.encounter.type) : '—' }}
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
              @click="openPay(item)"
            >
              Record payment
            </HButton>
          </div>
        </template>
      </HTable>
    </HCard>

    <HOffcanvas
      v-model="formOpen"
      title="Create invoice"
      size="lg"
      :error="formError"
      :persistent="saving"
    >
      <HSelect
        v-model="form.patient_id"
        :items="patients"
        item-title="full_name"
        item-value="id"
        label="Patient"
        @update:model-value="loadEncounters"
      />
      <HSelect
        v-if="encounterOptions.length"
        v-model="form.encounter_id"
        :items="encounterOptions"
        label="Encounter"
      />
      <p style="color:var(--muted);font-size:13px">
        Leave lines empty to open the encounter charge sheet.
      </p>
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
          :disabled="saving"
          @click="formOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving || (!form.patient_id && !form.encounter_id)"
          @click="save"
        >
          Save
        </HButton>
      </template>
    </HOffcanvas>

    <HModal
      v-model="payOpen"
      title="Record payment"
      :error="formError"
      :persistent="saving"
    >
      <div class="h-stack">
        <HInput
          v-model="payForm.amount"
          type="number"
          label="Amount"
        />
        <HSelect
          v-model="payForm.method"
          :items="['cash', 'card', 'mobile_money', 'insurance']"
          label="Method"
        />
      </div>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="payOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving || !payForm.amount"
          @click="savePayment"
        >
          Save payment
        </HButton>
      </template>
    </HModal>
  </div>
</template>
