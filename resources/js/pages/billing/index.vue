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
const catalog = ref({ services: [], medications: [], inventory: [], packages: [] })
const overrideOpen = ref(false)
const overrideLine = ref(null)
const overrideForm = ref({ unit_amount: 0, reason: '' })
const kinds = [
  { title: 'Service', value: 'service' },
  { title: 'Medication', value: 'medication' },
  { title: 'Product', value: 'inventory' },
  { title: 'Package', value: 'package' },
]
const form = ref(blankForm())
const payForm = ref({
  id: null,
  amount: 0,
  method: 'cash',
})

function blankLine() {
  return {
    kind: 'service',
    service_id: null,
    medication_id: null,
    inventory_item_id: null,
    package_id: null,
    description: '',
    quantity: 1,
    unit: 'each',
    unit_amount: 0,
    discount_amount: 0,
    amount: 0,
    override: false,
    override_reason: '',
  }
}

function blankForm() {
  return {
    patient_id: null,
    encounter_id: null,
    items: [blankLine()],
  }
}

const money = value => Number(value || 0).toLocaleString()

const catalogItems = line => {
  if (line.kind === 'medication')
    return catalog.value.medications
  if (line.kind === 'inventory')
    return catalog.value.inventory
  if (line.kind === 'package')
    return catalog.value.packages
  return catalog.value.services
}

const selectedId = line => line.service_id || line.medication_id || line.inventory_item_id || line.package_id

const quoteBody = line => ({
  quantity: line.quantity || 1,
  patient_id: form.value.patient_id || undefined,
  ...(line.kind === 'medication'
    ? { medication_id: line.medication_id }
    : line.kind === 'inventory'
      ? { inventory_item_id: line.inventory_item_id }
      : line.kind === 'package'
        ? { package_id: line.package_id }
        : { service_id: line.service_id }),
  ...(line.override
    ? {
        override: true,
        unit_amount: line.unit_amount,
        override_reason: line.override_reason,
      }
    : {}),
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

const openCreate = async () => {
  formError.value = ''
  encounters.value = []
  form.value = blankForm()
  catalog.value = await $api('/pricing/catalog')
  formOpen.value = true
}

const addItem = () => {
  form.value.items.push(blankLine())
}

const setKind = (line, kind) => {
  line.kind = kind
  line.service_id = null
  line.medication_id = null
  line.inventory_item_id = null
  line.package_id = null
  line.unit_amount = 0
  line.discount_amount = 0
  line.amount = 0
  line.unit = 'each'
  line.override = false
  line.override_reason = ''
}

const setBillable = async (line, id) => {
  line.service_id = line.kind === 'service' ? id : null
  line.medication_id = line.kind === 'medication' ? id : null
  line.inventory_item_id = line.kind === 'inventory' ? id : null
  line.package_id = line.kind === 'package' ? id : null
  line.override = false
  line.override_reason = ''
  await quoteLine(line)
}

const quoteLine = async line => {
  if (!selectedId(line) || !line.quantity)
    return
  try {
    const quote = await $api('/pricing/quote', {
      method: 'POST',
      body: quoteBody(line),
    })
    line.description = quote.description
    line.unit = quote.unit || 'each'
    line.unit_amount = quote.unit_price
    line.discount_amount = quote.discount_amount
    line.amount = quote.line_total
  }
  catch (error) {
    formError.value = error?.data?.message || error?.message || 'Unable to quote this line'
  }
}

const openOverride = line => {
  formError.value = ''
  overrideLine.value = line
  overrideForm.value = { unit_amount: Number(line.unit_amount || 0), reason: line.override_reason || '' }
  overrideOpen.value = true
}

const applyOverride = async () => {
  const line = overrideLine.value
  if (!line)
    return
  line.override = true
  line.unit_amount = overrideForm.value.unit_amount
  line.override_reason = overrideForm.value.reason
  await quoteLine(line)
  if (!formError.value)
    overrideOpen.value = false
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    const items = form.value.items.filter(item => selectedId(item)).map(item => ({
      quantity: item.quantity,
      service_id: item.service_id || undefined,
      medication_id: item.medication_id || undefined,
      inventory_item_id: item.inventory_item_id || undefined,
      package_id: item.package_id || undefined,
      ...(item.override
        ? {
            override: true,
            unit_amount: item.unit_amount,
            override_reason: item.override_reason,
          }
        : {}),
    }))
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

watch(() => form.value.patient_id, () => form.value.items.forEach(quoteLine))

const updateStatus = async (invoice, status) => {
  await wrapSave(saving, formError, async () => {
    await $api(`/invoices/${invoice.id}/status`, { method: 'PATCH', body: { status } })
    await load()
  })
}

const cancelInvoice = async invoice => {
  await wrapSave(saving, formError, async () => {
    await $api(`/invoices/${invoice.id}/status`, { method: 'PATCH', body: { status: 'cancelled' } })
    await load()
  })
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

const { pending } = usePageQuery(load)
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

    <div
      v-if="formError && !formOpen && !payOpen"
      class="h-alert"
    >
      {{ formError }}
    </div>

    <HCard flush>
      <HTable
        :loading="pending"
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
        <template #cell-number="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'billing-id', params: { id: item.id } }"
          >
            {{ item.number }}
          </RouterLink>
        </template>
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
              variant="ghost"
              size="sm"
              :to="{ name: 'billing-id', params: { id: item.id } }"
            >
              View
            </HButton>
            <HButton
              v-if="ability.can('update', 'Invoice') && item.status === 'draft'"
              size="sm"
              @click="updateStatus(item, 'issued')"
            >
              Issue
            </HButton>
            <HButton
              v-if="ability.can('update', 'Invoice') && item.status !== 'paid' && item.status !== 'cancelled'"
              variant="ghost"
              size="sm"
              @click="openPay(item)"
            >
              Record payment
            </HButton>
            <HActionMenu v-if="ability.can('update', 'Invoice') && ['draft', 'issued'].includes(item.status)">
              <template #default="{ close }">
                <button
                  type="button"
                  class="h-action-item is-danger"
                  @click="cancelInvoice(item); close()"
                >
                  Cancel invoice
                </button>
              </template>
            </HActionMenu>
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
      <HFormGrid>
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
      </HFormGrid>
      <HTable
        :headers="[
          { title: 'Product', key: 'product' },
          { title: 'Quantity', key: 'quantity' },
          { title: 'Unit', key: 'unit' },
          { title: 'Price', key: 'unit_amount' },
          { title: 'Discount', key: 'discount_amount' },
          { title: 'Total', key: 'amount' },
        ]"
        :items="form.items"
        empty="Add a service or product"
      >
        <template #cell-product="{ item }">
          <div class="h-stack">
            <HSelect
              :model-value="item.kind"
              :items="kinds"
              :clearable="false"
              :disabled="saving"
              @update:model-value="value => setKind(item, value)"
            />
            <HSelect
              :model-value="selectedId(item)"
              :items="catalogItems(item)"
              item-title="name"
              item-value="id"
              :disabled="saving"
              @update:model-value="value => setBillable(item, value)"
            />
          </div>
        </template>
        <template #cell-quantity="{ item }">
          <HNumber
            v-model="item.quantity"
            :min="1"
            :disabled="saving"
            @update:model-value="quoteLine(item)"
          />
        </template>
        <template #cell-unit="{ item }">
          {{ item.unit || 'each' }}
        </template>
        <template #cell-unit_amount="{ item }">
          <div class="h-stack">
            <strong>{{ money(item.unit_amount) }}</strong>
            <HButton
              v-if="ability.can('override', 'Invoice')"
              variant="ghost"
              size="sm"
              @click="openOverride(item)"
            >
              Override Price
            </HButton>
          </div>
        </template>
        <template #cell-discount_amount="{ item }">
          {{ money(item.discount_amount || 0) }}
        </template>
        <template #cell-amount="{ item }">
          {{ money(item.amount || 0) }}
        </template>
      </HTable>
      <HButton
        variant="ghost"
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
          placeholder="e.g. 150.00"
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

    <HModal
      v-model="overrideOpen"
      title="Override Price"
      :error="formError"
    >
      <fieldset class="h-stack">
        <HNumber
          v-model="overrideForm.unit_amount"
          label="New price"
          :min="0"
          required
        />
        <HTextarea
          v-model="overrideForm.reason"
          label="Reason"
          required
        />
      </fieldset>
      <template #actions>
        <HButton
          variant="ghost"
          @click="overrideOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="!overrideForm.reason"
          @click="applyOverride"
        >
          Apply override
        </HButton>
      </template>
    </HModal>

  </div>
</template>
