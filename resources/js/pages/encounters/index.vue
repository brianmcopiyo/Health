<script setup>
import { formatWhen } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {},
})

const encounters = ref([])
const departments = ref([])
const meta = ref(asPageMeta())
const list = useListQuery(['scope', 'type', 'status', 'department_id', 'from', 'to'])
const { page, q, filterValues } = list
if (!list.values.scope)
  list.values.scope = 'open'

const encounterQuery = extra => {
  const query = list.apiQuery(extra)
  if (query.scope === 'open' || !query.scope)
    query.open = 1
  delete query.scope
  return query
}

const load = async () => {
  departments.value = asList(await $api('/departments').catch(() => []))
  const payload = await $api('/encounters', { query: encounterQuery() })
  encounters.value = asList(payload)
  meta.value = asPageMeta(payload)
}

list.sync(load)
const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Encounters"
      subtitle="Visits across reception, OPD, emergency, and wards"
    >
      <HExportActions
        dataset="encounters"
        :query="encounterQuery()"
        :disabled="pending"
      />
    </HPage>

    <HCard flush>
      <HListToolbar
        v-model:search="q"
        v-model:values="filterValues"
        search-placeholder="Search patient or complaint"
        search-button
        :result-count="list.resultCount(meta)"
        :filters="[
          { key: 'scope', type: 'segmented', empty: 'open', options: [
            { value: 'open', title: 'Open' },
            { value: 'all', title: 'All' },
          ] },
          { key: 'type', type: 'select', label: 'Type', placeholder: 'All types', optional: true, empty: null, items: [
            { title: 'Reception', value: 'reception' },
            { title: 'OPD', value: 'opd' },
            { title: 'Emergency', value: 'emergency' },
            { title: 'Admission', value: 'admission' },
            { title: 'Procedure', value: 'procedure' },
            { title: 'Follow-up', value: 'follow_up' },
            { title: 'Referral', value: 'referral' },
          ] },
          { key: 'status', type: 'select', label: 'Status', placeholder: 'All statuses', optional: true, empty: null, more: true, items: [
            { title: 'Waiting', value: 'waiting' },
            { title: 'In progress', value: 'in_progress' },
            { title: 'Completed', value: 'completed' },
            { title: 'Cancelled', value: 'cancelled' },
            { title: 'Transferred', value: 'transferred' },
          ] },
          { key: 'department_id', type: 'select', label: 'Department', placeholder: 'All departments', items: departments, itemTitle: 'name', itemValue: 'id', optional: true, empty: null, more: true },
          { key: 'from', type: 'date', label: 'From', optional: true, empty: null, more: true },
          { key: 'to', type: 'date', label: 'To', optional: true, empty: null, more: true },
        ]"
        @search="list.onSearch(load)"
        @change="list.onChange(load)"
      />
      <HTable
        :loading="pending"
        :headers="[
          { title: 'Patient', key: 'patient.first_name', fill: true },
          { title: 'Status', key: 'status' },
          { title: 'When', key: 'created_at' },
        ]"
        :items="encounters"
        empty="No encounters in this view"
      >
        <template #cell-patient.first_name="{ item }">
          <HCell
            :to="{ name: 'encounters-id', params: { id: item.id } }"
            :secondary="joinContext(item.patient?.mrn, labelize(item.type), item.chief_complaint)"
          >
            {{ item.patient?.full_name || `${item.patient?.first_name || ''} ${item.patient?.last_name || ''}`.trim() || '—' }}
          </HCell>
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-created_at="{ item }">
          {{ formatWhen(item.created_at) }}
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => list.onPage(value, load)"
      />
    </HCard>
  </div>
</template>
