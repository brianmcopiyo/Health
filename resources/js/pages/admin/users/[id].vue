<script setup>
import AccessUserProfile from '@/components/access/AccessUserProfile.vue'
import { formatWhen } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'User',
  },
})

const ability = useAbility()
const hospitals = ref([])
const route = useRoute()
const record = ref(null)
const profile = ref(null)
const tab = ref('overview')

const tabs = [
  { title: 'Overview', value: 'overview' },
  { title: 'Assignments', value: 'assignments' },
  { title: 'Encounters', value: 'encounters' },
  { title: 'Activity', value: 'activity' },
]

const load = async () => {
  record.value = await $api(`/users/${route.params.id}`)
  if (ability.can('manage', 'Hospital'))
    hospitals.value = asList(await $api('/hospitals'))
}

const recordActions = computed(() => {
  if (!record.value)
    return []
  return [
    { label: 'Edit', icon: 'edit', if: ability.can('update', 'User'), onSelect: () => { profile.value?.openEdit() } },
  ]
})

const { pending, run } = usePageQuery(load)
watch(() => route.params.id, () => run())
</script>

<template>
  <HRecord
    :title="record?.name || 'User'"
    :subtitle="record?.email || ''"
    :status="record?.status"
    :back="{ name: 'admin-users' }"
    back-label="Users"
    :actions="recordActions"
    :tabs="tabs"
    :tab="tab"
    :loading="pending"
    :missing="!pending && !record"
    @update:tab="tab = $event"
  >
    <AccessUserProfile
      v-show="record && tab === 'overview'"
      v-if="record"
      ref="profile"
      :record="record"
      @saved="run"
    >
      <template #account-extra>
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
      </template>
      <template #access-extra>
        <div
          v-if="(record.memberships || []).length"
          class="h-metric"
        >
          <span>Memberships</span>
          <strong>{{ record.memberships.length }}</strong>
        </div>
      </template>
      <template #form-extra="{ form, ability: can }">
        <HSelect
          v-if="can.can('manage', 'Hospital')"
          v-model="form.hospital_id"
          :items="hospitals"
          item-title="name"
          item-value="id"
          label="Hospital"
        />
      </template>
    </AccessUserProfile>

    <HCard
      v-if="record && tab === 'assignments'"
      title="Assignments"
      flush
    >
      <HTable
        :headers="[
          { title: 'Assignment', key: 'assignment_role', fill: true },
        ]"
        :items="record.staff_assignments || []"
        empty="No ward or unit assignments"
      >
        <template #cell-assignment_role="{ item }">
          <HCell :secondary="joinContext(item.department?.name, item.facility?.name)">
            {{ item.assignment_role }}
          </HCell>
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
          { title: 'Patient', key: 'patient.first_name', fill: true },
          { title: 'Status', key: 'status' },
        ]"
        :items="record.encounters || []"
        empty="No encounters assigned to this clinician"
      >
        <template #cell-patient.first_name="{ item }">
          <HCell
            :to="item.patient?.id ? { name: 'patients-id', params: { id: item.patient.id } } : { name: 'encounters-id', params: { id: item.id } }"
            :secondary="labelize(item.type)"
          >
            {{ item.patient?.full_name || `${item.patient?.first_name || ''} ${item.patient?.last_name || ''}`.trim() || '—' }}
          </HCell>
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
          { title: 'Action', key: 'action', fill: true },
          { title: 'When', key: 'created_at' },
        ]"
        :items="record.activity || []"
        empty="No recent activity"
      >
        <template #cell-action="{ item }">
          <HCell :secondary="item.auditable_type">
            {{ item.action }}
          </HCell>
        </template>
        <template #cell-created_at="{ item }">
          {{ formatWhen(item.created_at) }}
        </template>
      </HTable>
    </HCard>
  </HRecord>
</template>
