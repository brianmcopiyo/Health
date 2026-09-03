<script setup>
import { facilityRecordTo } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Department',
  },
})

const route = useRoute()
const ability = useAbility()
const record = ref(null)
const tab = ref('overview')
const directory = ref([])
const saving = ref(false)
const formError = ref('')
const staffForm = ref({ user_id: null, assignment_role: 'staff', shift: 'day' })

const tabs = [
  { title: 'Overview', value: 'overview' },
  { title: 'Staff', value: 'staff' },
  { title: 'Services', value: 'services' },
  { title: 'Facilities', value: 'facilities' },
  { title: 'Activity', value: 'activity' },
]

const staffRows = computed(() => {
  const assignments = record.value?.staff_assignments || []
  if (assignments.length) {
    return assignments.map(item => ({
      id: item.user?.id || item.id,
      name: item.user?.name || 'Staff',
      job_title: item.user?.job_title || labelize(item.assignment_role),
      role: item.user?.role,
    }))
  }

  return record.value?.users || []
})

const load = async () => {
  record.value = await $api(`/departments/${route.params.id}`)
  if (ability.can('update', 'User') || ability.can('manage', 'User'))
    directory.value = asList(await $api('/users/directory').catch(() => []))
}

const addStaff = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/staff-assignments', {
      method: 'POST',
      body: { ...staffForm.value, department_id: record.value.id },
    })
    staffForm.value = { user_id: null, assignment_role: 'staff', shift: 'day' }
    await load()
  })
}

const { pending, run } = usePageQuery(load)
watch(() => route.params.id, () => run())
</script>

<template>
  <HRecord
    :title="record?.name || 'Department'"
    :subtitle="record ? labelize(record.module_key) : ''"
    :status="record?.is_active ? 'active' : 'inactive'"
    :back="{ name: 'admin-departments' }"
    back-label="Departments"
    :tabs="tabs"
    :tab="tab"
    :loading="pending"
    :missing="!pending && !record"
    @update:tab="tab = $event"
  >
    <div
      v-if="formError"
      class="h-alert"
    >
      {{ formError }}
    </div>

    <div
      v-if="record && tab === 'overview'"
      class="h-detail"
    >
      <HCard title="Department">
        <div class="h-metric">
          <span>Module</span>
          <strong>{{ labelize(record.module_key) }}</strong>
        </div>
        <div class="h-metric">
          <span>Staff</span>
          <strong>{{ record.users_count || record.users?.length || 0 }}</strong>
        </div>
        <div class="h-metric">
          <span>Facilities</span>
          <strong>{{ record.facilities_count || record.facilities?.length || 0 }}</strong>
        </div>
        <div class="h-metric">
          <span>Encounters</span>
          <strong>{{ record.encounters_count || record.encounters?.length || 0 }}</strong>
        </div>
      </HCard>
    </div>

    <HCard
      v-if="record && tab === 'staff'"
      title="Staff"
      flush
    >
      <HTable
        :headers="[
          { title: 'Name', key: 'name', fill: true },
          { title: 'Role', key: 'role.name' },
        ]"
        :items="staffRows"
        empty="No staff assigned to this department"
      >
        <template #cell-name="{ item }">
          <HCell
            :to="{ name: 'admin-users-id', params: { id: item.id } }"
            :secondary="item.job_title"
          >
            {{ item.name }}
          </HCell>
        </template>
      </HTable>
      <fieldset
        v-if="ability.can('update', 'User') || ability.can('manage', 'User')"
        class="h-form"
        style="padding: 1rem"
      >
        <HSelect
          v-model="staffForm.user_id"
          :items="directory"
          item-title="name"
          item-value="id"
          label="Assign staff"
          placeholder="Select a staff member"
        />
        <HButton
          size="sm"
          :loading="saving"
          :disabled="saving || !staffForm.user_id"
          @click="addStaff"
        >
          Assign
        </HButton>
      </fieldset>
    </HCard>

    <HCard
      v-if="record && tab === 'services'"
      title="Services"
      flush
    >
      <HTable
        :headers="[
          { title: 'Service', key: 'name', fill: true },
        ]"
        :items="record.services || []"
        empty="No services mapped to this department"
      >
        <template #cell-name="{ item }">
          <HCell :secondary="item.category">
            {{ item.name }}
          </HCell>
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
          { title: 'Facility', key: 'name', fill: true },
          { title: 'Status', key: 'status' },
        ]"
        :items="record.facilities || []"
        empty="No facilities mapped"
      >
        <template #cell-name="{ item }">
          <HCell
            :to="facilityRecordTo(item)"
            :secondary="item.type?.name"
          >
            {{ item.name }}
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
      title="Recent encounters"
      flush
    >
      <HTable
        :headers="[
          { title: 'Patient', key: 'patient.first_name', fill: true },
          { title: 'Clinician', key: 'clinician.name' },
          { title: 'Status', key: 'status' },
        ]"
        :items="record.encounters || []"
        empty="No recent encounters"
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
  </HRecord>
</template>
