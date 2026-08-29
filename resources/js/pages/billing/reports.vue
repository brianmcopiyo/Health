<script setup>
definePage({
  meta: {
    action: 'read',
    subject: 'Invoice',
  },
})

const report = ref(null)
const filters = ref({ from: '', to: '' })

const load = async () => {
  report.value = await $api('/invoices/reports', {
    query: {
      from: filters.value.from || undefined,
      to: filters.value.to || undefined,
    },
  })
}

const { pending } = usePageQuery(load)

const sections = computed(() => ([
  ['By date', report.value?.by_date],
  ['By service', report.value?.by_service],
  ['By category', report.value?.by_category],
  ['By patient', report.value?.by_customer],
  ['By hospital', report.value?.by_branch],
  ['By user', report.value?.by_user],
  ['By payment method', report.value?.by_payment_method],
  ['Discounts', report.value?.discounts],
  ['Refunds', report.value?.refunds],
]))
</script>

<template>
  <div>
    <HPage
      title="Sales reports"
      subtitle="Charges, collections, discounts, refunds, and outstanding balances"
    >
      <HExportActions
        dataset="invoice-reports"
        :query="filters"
        :disabled="pending"
      />
    </HPage>

    <HCard>
      <HListToolbar
        :show-search="false"
        :filters="[
          { key: 'from', type: 'date', label: 'From' },
          { key: 'to', type: 'date', label: 'To' },
        ]"
        :values="filters"
        @update:values="value => { filters = value; load() }"
      />
    </HCard>

    <HGrid
      v-if="report"
      cols="4"
      kind="stats"
    >
      <HStat
        icon="receipt"
        title="Revenue"
        :value="report.summary.revenue"
      />
      <HStat
        icon="coin"
        title="Collected"
        :value="report.summary.collected"
      />
      <HStat
        icon="clock"
        title="Outstanding"
        :value="report.summary.outstanding"
        :tone="Number(report.summary.outstanding) > 0 ? 'warn' : 'ok'"
      />
      <HStat
        icon="refresh"
        title="Refunds"
        :value="report.summary.refunds"
      />
    </HGrid>

    <HCard
      v-for="[title, rows] in sections"
      :key="title"
      :title="title"
      flush
    >
      <HTable
        :loading="pending"
        :headers="[
          { title: 'Label', key: 'label' },
          { title: 'Amount', key: 'amount' },
        ]"
        :items="rows || []"
        empty="No rows in this range"
      />
    </HCard>
  </div>
</template>
