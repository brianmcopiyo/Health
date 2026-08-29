<script setup>
import { facilityRecordTo, formatWhen } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'User',
  },
})

const route = useRoute()
const record = ref(null)
const tab = ref('overview')

const tabs = [
  { title: 'Overview', value: 'overview' },
  { title: 'Assignments', value: 'assignments' },
  { title: 'Encounters', value: 'encounters' },
  { title: 'Activity', value: 'activity' },
]

const load = async () => {
  record.value = await $api(`/users/${route.params.id}`)
}

watch(() => route.params.id, () => withPageLoad(load))
await withPageLoad(load)
</script>

<template>
  <HRecord
    :title="record?.name || 'User'"
    :subtitle="record?.email || ''"
    :back="{ name: 'admin-users' }"
    back-label="Users"
    :tabs="tabs"
    :tab="tab"
    :missing="!record"
    @update:tab="tab = $event"
  >
    <div
      v-if="record && tab === 'overview'"
      class="h-detail"
    >
      <HCard title="Account">
        <div class="h-metric">
          <span>Role</span>
          <strong>
            <RouterLink
              v-if="record.role?.id"
              class="h-inline-link"
              :to="{ name: 'admin-roles-id', params: { id: record.role.id } }"
            >
              {{ record.role.name }}
            </RouterLink>
            <span v-else>—</span>
          </strong>
        </div>
        <div class="h-metric">
          <span>Department</span>
          <strong>
            <RouterLink
              v-if="record.department?.id"
              class="h-inline-link"
              :to="{ name: 'admin-departments-id', params: { id: record.department.id } }"
            >
              {{ record.department.name }}
            </RouterLink>
            <span v-else>—</span>
          </strong>
        </div>
        <div class="h-metric">
          <span>Hospital</span>
          <strong>{{ record.hospital?.name || '—' }}</strong>
        </div>
        <div class="h-metric">
          <span>Title</span>
          <strong>{{ record.job_title || '—' }}</strong>
        </div>
      </HCard>
      <HCard title="Permissions">
        <div class="h-actions">
          <HBadge
            v-for="permission in (record.permissions || []).slice(0, 12)"
            :key="permission.id"
          >
            {{ permission.name }}
          </HBadge>
        </div>
        <p
          v-if="!(record.permissions || []).length"
          class="h-muted"
        >
          No permissions on this role
        </p>
      </HCard>
    </div>

    <HCard
      v-if="record && tab === 'assignments'"
      title="Assignments"
      flush
    >
      <HTable
        :headers="[
          { title: 'Assignment', key: 'assignment_role' },
          { title: 'Department', key: 'department.name' },
          { title: 'Unit', key: 'facility.name' },
        ]"
        :items="record.staff_assignments || []"
        empty="No ward or unit assignments"
      >
        <template #cell-department.name="{ item }">
          <RouterLink
            v-if="item.department?.id"
            class="h-inline-link"
            :to="{ name: 'admin-departments-id', params: { id: item.department.id } }"
          >
            {{ item.department.name }}
          </RouterLink>
          <span v-else>—</span>
        </template>
        <template #cell-facility.name="{ item }">
          <RouterLink
            v-if="item.facility?.id"
            class="h-inline-link"
            :to="facilityRecordTo(item.facility)"
          >
            {{ item.facility.name }}
          </RouterLink>
          <span v-else>—</span>
        </template>
      </HTable>
    </HCard>

    <HCard
      v-if="record && tab === 'encounters'"
      title="Encounters"
      flush
    >
      <HTable
        :headers="[
          { title: 'Patient', key: 'patient.first_name' },
          { title: 'Type', key: 'type' },
          { title: 'Status', key: 'status' },
        ]"
        :items="record.encounters || []"
        empty="No encounters assigned to this clinician"
      >
        <template #cell-patient.first_name="{ item }">
          <RouterLink
            v-if="item.patient?.id"
            class="h-inline-link"
            :to="{ name: 'patients-id', params: { id: item.patient.id } }"
          >
            {{ item.patient.first_name }} {{ item.patient.last_name }}
          </RouterLink>
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
      </HTable>
    </HCard>

    <HCard
      v-if="record && tab === 'activity'"
      title="Activity"
      flush
    >
      <HTable
        :headers="[
          { title: 'Action', key: 'action' },
          { title: 'Record', key: 'auditable_type' },
          { title: 'When', key: 'created_at' },
        ]"
        :items="record.activity || []"
        empty="No recent activity"
      >
        <template #cell-created_at="{ item }">
          {{ formatWhen(item.created_at) }}
        </template>
      </HTable>
    </HCard>
  </HRecord>
</template>
