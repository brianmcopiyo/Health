<script setup>
import { paymentMethods } from '@/utils/clinicalOptions'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Invoice',
  },
})

const route = useRoute()
const ability = useAbility()
const record = ref(null)
const tab = ref('overview')
const payOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const payForm = ref({ amount: 0, method: 'cash' })

const tabs = [
  { title: 'Overview', value: 'overview' },
  { title: 'Items', value: 'items' },
  { title: 'Payments', value: 'payments' },
]

const load = async () => {
  record.value = await $api(`/invoices/${route.params.id}`)
}

const updateStatus = async status => {
  await wrapSave(saving, formError, async () => {
    record.value = await $api(`/invoices/${record.value.id}/status`, { method: 'PATCH', body: { status } })
  })
}

const openPay = () => {
  formError.value = ''
  payForm.value = { amount: record.value.total, method: 'cash' }
  payOpen.value = true
}

const savePayment = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/invoices/${record.value.id}/payments`, {
      method: 'POST',
      body: { amount: Number(payForm.value.amount), method: payForm.value.method },
    })
    payOpen.value = false
    await load()
  })
}

const { pending, run } = usePageQuery(load)
watch(() => route.params.id, () => run())
</script>

<template>
  <HRecord
    :title="record?.number || 'Invoice'"
    :subtitle="record ? `Total ${record.total}` : ''"
    :status="record?.status"
    :back="{ name: 'billing' }"
    back-label="Billing"
    :tabs="tabs"
    :tab="tab"
    :loading="pending"
    :missing="!pending && !record"
    @update:tab="tab = $event"
  >
    <div
      v-if="formError && !payOpen"
      class="h-alert"
    >
      {{ formError }}
    </div>

    <div
      v-if="record && tab === 'overview'"
      class="h-detail"
    >
      <HCard title="Invoice">
        <template
          v-if="ability.can('update', 'Invoice') && record.status === 'draft'"
          #actions
        >
          <HButton
            size="sm"
            @click="updateStatus('issued')"
          >
            Issue
          </HButton>
        </template>
        <div class="h-metric">
          <span>Patient</span>
          <strong>
            <RouterLink
              v-if="record.patient?.id"
              class="h-inline-link"
              :to="{ name: 'patients-id', params: { id: record.patient.id } }"
            >
              {{ record.patient.first_name }} {{ record.patient.last_name }}
            </RouterLink>
            <span v-else>—</span>
          </strong>
        </div>
        <div class="h-metric">
          <span>Encounter</span>
          <strong>
            <RouterLink
              v-if="record.encounter?.id"
              class="h-inline-link"
              :to="{ name: 'encounters-id', params: { id: record.encounter.id } }"
            >
              {{ labelize(record.encounter.type) }}
            </RouterLink>
            <span v-else>—</span>
          </strong>
        </div>
        <div class="h-metric">
          <span>Total</span>
          <strong>{{ record.total }}</strong>
        </div>
      </HCard>
    </div>

    <HCard
      v-if="record && tab === 'items'"
      title="Line items"
      flush
    >
      <HTable
        :headers="[
          { title: 'Description', key: 'description' },
          { title: 'Qty', key: 'quantity' },
          { title: 'Amount', key: 'unit_amount' },
        ]"
        :items="record.items || []"
        empty="No line items"
      />
    </HCard>

    <HCard
      v-if="record && tab === 'payments'"
      title="Payments"
      flush
    >
      <template
        v-if="ability.can('update', 'Invoice') && record.status !== 'paid' && record.status !== 'cancelled'"
        #actions
      >
        <HButton
          size="sm"
          @click="openPay"
        >
          Record payment
        </HButton>
      </template>
      <HTable
        :headers="[
          { title: 'Amount', key: 'amount' },
          { title: 'Method', key: 'method' },
        ]"
        :items="record.payments || []"
        empty="No payments recorded"
      />
    </HCard>

    <HModal
      v-model="payOpen"
      title="Record payment"
      :error="formError"
      :persistent="saving"
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
  </HRecord>
</template>
