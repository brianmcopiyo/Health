<script setup>
import { paymentMethods } from '@/utils/clinicalOptions'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Invoice',
  },
})

const ability = useAbility()
const invoices = ref([])
const meta = ref(asPageMeta())
const page = ref(1)
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
  const payload = await $api('/invoices', { query: { page: page.value } })
  invoices.value = asList(payload)
  meta.value = asPageMeta(payload)
  if (ability.can('create', 'Invoice') || ability.can('update', 'Invoice'))
    patients.value = asList(await $api('/patients', { query: compactListQuery() }))
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
      <HPager
        :meta="meta"
        @update:page="value => { page = value; load() }"
      />
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
        required
        :disabled="saving"
        @update:model-value="loadEncounters"
      />
      <HSelect
        v-if="encounterOptions.length"
        v-model="form.encounter_id"
        :items="encounterOptions"
        label="Encounter"
        hint="Leave lines empty to open the encounter charge sheet"
        :disabled="saving"
      />
      <fieldset
        v-for="(item, index) in form.items"
        :key="index"
        class="h-grid cols-3"
        style="margin-top:12px"
        :disabled="saving"
      >
        <HInput
          v-model="item.description"
          label="Description"
        />
        <HNumber
          v-model="item.quantity"
          label="Qty"
          :min="1"
        />
        <HNumber
          v-model="item.unit_amount"
          label="Unit amount"
          :min="0"
        />
      </fieldset>
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
      <fieldset
        class="h-stack"
        :disabled="saving"
      >
        <HNumber
          v-model="payForm.amount"
          label="Amount"
          :min="1"
          required
        />
        <HRadioGroup
          v-model="payForm.method"
          :items="paymentMethods"
          label="Method"
        />
      </fieldset>
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
