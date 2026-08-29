<script setup>
import { facilityRecordTo } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'manage',
    subject: 'Hospital',
  },
})

const route = useRoute()
const record = ref(null)
const tab = ref('overview')

const tabs = [
  { title: 'Overview', value: 'overview' },
  { title: 'Departments', value: 'departments' },
  { title: 'Facilities', value: 'facilities' },
  { title: 'Staff', value: 'staff' },
  { title: 'Fleet', value: 'fleet' },
]

const load = async () => {
  record.value = await $api(`/hospitals/${route.params.id}`)
}

const { pending, run } = usePageQuery(load)
watch(() => route.params.id, () => run())
</script>

<template>
  <HRecord
    :title="record?.name || 'Hospital'"
    :subtitle="record ? `${record.code} · ${record.city || ''}` : ''"
    :status="record?.is_active ? 'active' : 'inactive'"
    :back="{ name: 'admin-hospitals' }"
    back-label="Hospitals"
    :tabs="tabs"
    :tab="tab"
    :loading="pending"
    :missing="!pending && !record"
    @update:tab="tab = $event"
  >
    <template v-if="record && tab === 'overview'">
      <HGrid
        cols="4"
        kind="stats"
      >
        <HStat
          icon="hospital"
          title="Units"
          :value="record.capacity?.facilities || 0"
        />
        <HStat
          icon="users"
          title="Staff"
          :value="record.capacity?.staff || 0"
        />
        <HStat
          icon="check"
          title="Bed capacity"
          :value="record.capacity?.beds || 0"
        />
        <HStat
          icon="chart"
          title="Occupied"
          :value="record.capacity?.occupied || 0"
        />
      </HGrid>
      <div class="h-detail">
        <HCard title="Hospital">
          <div class="h-metric">
            <span>Region</span>
            <strong>{{ record.region || '—' }}</strong>
          </div>
          <div class="h-metric">
            <span>Phone</span>
            <strong>{{ record.phone || '—' }}</strong>
          </div>
          <div class="h-metric">
            <span>Email</span>
            <strong>{{ record.email || '—' }}</strong>
          </div>
          <p class="h-muted">
            {{ record.address || 'No address on file' }}
          </p>
        </HCard>
      </div>
    </template>

    <HCard
      v-if="record && tab === 'departments'"
      title="Departments"
      flush
    >
      <HTable
        :headers="[
          { title: 'Name', key: 'name' },
          { title: 'Module', key: 'module_key' },
        ]"
        :items="record.departments || []"
        empty="No departments"
      >
        <template #cell-name="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'admin-departments-id', params: { id: item.id } }"
          >
            {{ item.name }}
          </RouterLink>
        </template>
        <template #cell-module_key="{ item }">
          {{ labelize(item.module_key) }}
        </template>
      </HTable>
    </HCard>

    <HCard
      v-if="record && tab === 'facilities'"
      title="Facilities"
      flush
    >
      <HTable
        :headers="[
          { title: 'Unit', key: 'name' },
          { title: 'Type', key: 'type.name' },
          { title: 'Status', key: 'status' },
        ]"
        :items="record.facilities || []"
        empty="No facilities"
      >
        <template #cell-name="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="facilityRecordTo(item)"
          >
            {{ item.name }}
          </RouterLink>
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
      </HTable>
    </HCard>

    <HCard
      v-if="record && tab === 'staff'"
      title="Staff"
      flush
    >
      <HTable
        :headers="[
          { title: 'Name', key: 'name' },
          { title: 'Role', key: 'role.name' },
          { title: 'Title', key: 'job_title' },
        ]"
        :items="record.users || []"
        empty="No staff"
      >
        <template #cell-name="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'admin-users-id', params: { id: item.id } }"
          >
            {{ item.name }}
          </RouterLink>
        </template>
      </HTable>
    </HCard>

    <HCard
      v-if="record && tab === 'fleet'"
      title="Ambulances"
      flush
    >
      <HTable
        :headers="[
          { title: 'Vehicle', key: 'vehicle_code' },
          { title: 'Type', key: 'vehicle_type' },
          { title: 'Status', key: 'status' },
        ]"
        :items="record.ambulances || []"
        empty="No ambulances"
      >
        <template #cell-vehicle_code="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'ambulances-id', params: { id: item.id } }"
          >
            {{ item.vehicle_code }}
          </RouterLink>
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
      </HTable>
    </HCard>
  </HRecord>
</template>
