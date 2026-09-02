<script setup>
import { defaultReportFilters, downloadReport, reportQuery } from '@/composables/useReports'

definePage({
  meta: {
    action: 'read',
    subject: 'Report',
  },
})

const route = useRoute()
const router = useRouter()
const toast = useToast()
const meta = ref(null)
const payload = ref(null)
const filters = ref(defaultReportFilters())
const exporting = ref('')

const section = computed(() => String(route.query.section || 'overview'))
const tabs = computed(() => meta.value?.tabs || payload.value?.tabs || [])
const schema = computed(() => {
  const fromMeta = meta.value?.schemas?.[section.value]
  return fromMeta || payload.value?.schema || { filters: ['from', 'to'] }
})
const hospital = computed(() => payload.value?.hospital || meta.value?.hospital || {})
const subtitle = computed(() => {
  const name = hospital.value?.name || 'Hospital reporting'
  const range = payload.value?.range
  return range ? `${name} · ${range.from} to ${range.to}` : name
})

const queryParams = (extra = {}) => reportQuery(section.value, filters.value, extra)

const load = async () => {
  if (!meta.value)
    meta.value = await $api('/reports/meta')

  const allowed = (meta.value.tabs || []).map(item => item.key)
  if (allowed.length && !allowed.includes(section.value)) {
    await router.replace({ query: { ...route.query, section: allowed[0] } })
    return
  }

  payload.value = await $api('/reports', { query: queryParams() })
}

const { pending, run } = usePageQuery(load)
const showSkel = useDelayedVisible(pending)

const openSection = key => {
  if (key === section.value)
    return
  router.replace({ query: { ...route.query, section: key } })
}

const loadTable = async page => {
  const table = await $api('/reports/table', { query: queryParams({ page }) })
  if (payload.value)
    payload.value = { ...payload.value, table }
}

const exportReport = async format => {
  if (exporting.value)
    return

  exporting.value = format
  try {
    await downloadReport(section.value, filters.value, format)
    toast.success('Exported the complete report')
  }
  catch (error) {
    toast.error(error?.data?.message || error?.message || 'Unable to export this report')
  }
  finally {
    exporting.value = ''
  }
}

watch(() => route.query.section, () => run())
watch(filters, () => {
  if (payload.value)
    run({ silent: true })
}, { deep: true })
</script>

<template>
  <div class="h-report">
    <HPage
      title="Reports"
      :subtitle="subtitle"
    >
      <HButton
        variant="ghost"
        :loading="exporting === 'pdf'"
        :disabled="!!exporting || pending"
        @click="exportReport('pdf')"
      >
        <HIcon name="receipt" />
        Export PDF
      </HButton>
      <HButton
        variant="ghost"
        :loading="exporting === 'xlsx'"
        :disabled="!!exporting || pending"
        @click="exportReport('xlsx')"
      >
        <HIcon name="download" />
        Export Excel
      </HButton>
      <HButton
        variant="ghost"
        :disabled="pending"
        @click="run()"
      >
        <HIcon name="refresh" />
        Refresh
      </HButton>
    </HPage>

    <nav
      class="h-record-tabs"
      role="tablist"
      aria-label="Report modules"
    >
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        role="tab"
        :aria-selected="section === tab.key"
        :class="{ 'is-on': section === tab.key }"
        @click="openSection(tab.key)"
      >
        {{ tab.title }}
      </button>
      <template v-if="!tabs.length && pending">
        <span
          v-for="n in 8"
          :key="n"
          class="h-skeleton is-tab"
          :class="{ 'is-hold': !showSkel }"
        />
      </template>
    </nav>

    <HCard title="Filters">
      <HReportFilters
        v-model="filters"
        :schema="schema"
        :options="meta?.options || {}"
      />
    </HCard>

    <HReportBoard
      :payload="payload"
      :loading="pending"
      @page="loadTable"
    />
  </div>
</template>
