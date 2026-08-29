<script setup>
import { formatWhen } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {},
})

const encounters = ref([])
const meta = ref(asPageMeta())
const page = ref(1)
const scope = ref('open')

const load = async () => {
  const payload = await $api('/encounters', {
    query: {
      page: page.value,
      open: scope.value === 'open' || undefined,
    },
  })
  encounters.value = asList(payload)
  meta.value = asPageMeta(payload)
}

const filterValues = computed({
  get: () => ({ scope: scope.value }),
  set: next => {
    scope.value = next.scope
  },
})

const applyFilters = () => {
  page.value = 1
  load()
}

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
        :query="{ open: scope === 'open' || undefined }"
        :disabled="pending"
      />
    </HPage>

    <HCard flush>
      <HListToolbar
        v-model:values="filterValues"
        :filters="[
          { key: 'scope', type: 'segmented', empty: 'open', options: [
            { value: 'open', title: 'Open' },
            { value: 'all', title: 'All' },
          ] },
        ]"
        @change="applyFilters"
      />
      <HTable
        :loading="pending"
        :headers="[
          { title: 'Patient', key: 'patient.first_name' },
          { title: 'Type', key: 'type' },
          { title: 'Complaint', key: 'chief_complaint' },
          { title: 'Status', key: 'status' },
          { title: 'When', key: 'created_at' },
        ]"
        :items="encounters"
        empty="No encounters in this view"
      >
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
        <template #cell-type="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'encounters-id', params: { id: item.id } }"
          >
            {{ labelize(item.type) }}
          </RouterLink>
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
        @update:page="value => { page = value; load() }"
      />
    </HCard>
  </div>
</template>
