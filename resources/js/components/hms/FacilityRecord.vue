<script setup>
import EncounterChart from '@/components/hms/EncounterChart.vue'
import { facilityRecordTo, formatWhen } from '@/utils/helpers'
import { facilityStatuses, labelize, statusColor } from '@/utils/status'

const props = defineProps({
  kind: { type: String, default: 'facility' },
})

const route = useRoute()
const router = useRouter()
const ability = useAbility()
const record = ref(null)
const tab = ref('overview')
const types = ref([])
const departments = ref([])
const parents = ref([])
const patients = ref([])
const availableBeds = ref([])
const statusOpen = ref(false)
const editOpen = ref(false)
const assignOpen = ref(false)
const moveOpen = ref(false)
const bedOpen = ref(false)
const removing = ref(false)
const saving = ref(false)
const formError = ref('')
const chartOpen = ref(false)
const encounterId = ref(null)
const statusForm = ref({ status: 'available', current_utilization: 0, resource_notes: '' })
const form = ref({
  name: '',
  facility_type_id: null,
  parent_id: null,
  department_id: null,
  status: 'available',
  capacity: 1,
  current_utilization: 0,
  resource_notes: '',
  notes: '',
})
const assignForm = ref({ patient_id: null, facility_id: null })
const moveForm = ref({ parent_id: null })
const bedForm = ref({ name: '', capacity: 1 })
const transferOpen = ref(false)
const transferForm = ref({ assignment_id: null, facility_id: null })

const slug = computed(() => record.value?.type?.slug || props.kind)
const isWard = computed(() => slug.value === 'ward')
const isBed = computed(() => slug.value === 'bed')
const listTo = computed(() => (isWard.value ? 'wards' : isBed.value ? 'beds' : 'facilities'))
const listLabel = computed(() => (isWard.value ? 'Wards' : isBed.value ? 'Beds' : 'Facilities'))

const tabs = computed(() => {
  if (isWard.value) {
    return [
      { title: 'Overview', value: 'overview' },
      { title: 'Beds', value: 'beds' },
      { title: 'Patients', value: 'patients' },
      { title: 'Team', value: 'team' },
      { title: 'History', value: 'history' },
    ]
  }

  if (isBed.value) {
    return [
      { title: 'Overview', value: 'overview' },
      { title: 'Patient', value: 'patient' },
      { title: 'History', value: 'history' },
    ]
  }

  return [
    { title: 'Overview', value: 'overview' },
    { title: 'Units', value: 'units' },
    { title: 'Staff', value: 'team' },
    { title: 'History', value: 'history' },
  ]
})

const load = async () => {
  record.value = await $api(`/facilities/${route.params.id}`)
  statusForm.value = {
    status: record.value.status,
    current_utilization: record.value.current_utilization,
    resource_notes: record.value.resource_notes || '',
  }
  if (!tabs.value.some(item => item.value === tab.value))
    tab.value = 'overview'
}

const openStatus = () => {
  formError.value = ''
  statusForm.value = {
    status: record.value.status,
    current_utilization: record.value.current_utilization,
    resource_notes: record.value.resource_notes || '',
  }
  statusOpen.value = true
}

const saveStatus = async () => {
  await wrapSave(saving, formError, async () => {
    record.value = await $api(`/facilities/${record.value.id}/status`, { method: 'PATCH', body: statusForm.value })
    statusOpen.value = false
    await load()
  })
}

const openEdit = async () => {
  formError.value = ''
  types.value = asList(await $api('/facility-types'))
  departments.value = asList(await $api('/departments').catch(() => []))
  parents.value = asList(await $api('/facilities', { query: { type: 'ward', per_page: 80 } }).catch(() => []))
    .filter(item => item.id !== record.value.id)
  form.value = {
    name: record.value.name,
    facility_type_id: record.value.facility_type_id,
    parent_id: record.value.parent_id,
    department_id: record.value.department_id,
    status: record.value.status,
    capacity: record.value.capacity,
    current_utilization: record.value.current_utilization,
    resource_notes: record.value.resource_notes,
    notes: record.value.notes,
  }
  editOpen.value = true
}

const saveEdit = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/facilities/${record.value.id}`, { method: 'PUT', body: form.value })
    editOpen.value = false
    await load()
  })
}

const removeRecord = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/facilities/${record.value.id}`, { method: 'DELETE' })
    await router.push({ name: listTo.value })
  })
}

const openAssign = async () => {
  formError.value = ''
  patients.value = asList(await $api('/patients', { query: compactListQuery() }).catch(() => []))
  assignForm.value = {
    patient_id: null,
    facility_id: isBed.value ? record.value.id : null,
  }
  if (isWard.value)
    availableBeds.value = (record.value.beds || []).filter(item => item.status === 'available')
  assignOpen.value = true
}

const assignBed = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/bed-assignments', { method: 'POST', body: assignForm.value })
    assignOpen.value = false
    await load()
  })
}

const discharge = async assignment => {
  await wrapSave(saving, formError, async () => {
    await $api(`/bed-assignments/${assignment.id}/discharge`, { method: 'PATCH' })
    await load()
  })
}

const openTransfer = async assignment => {
  formError.value = ''
  availableBeds.value = asList(await $api('/facilities', { query: { type: 'bed', per_page: 80 } }).catch(() => []))
    .filter(item => item.status === 'available' && item.id !== assignment.facility_id)
  transferForm.value = { assignment_id: assignment.id, facility_id: null }
  transferOpen.value = true
}

const transferAssignment = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/bed-assignments/${transferForm.value.assignment_id}/transfer`, {
      method: 'PATCH',
      body: { facility_id: transferForm.value.facility_id },
    })
    transferOpen.value = false
    await load()
  })
}

const openMove = async () => {
  formError.value = ''
  parents.value = asList(await $api('/facilities', { query: { type: 'ward', per_page: 80 } }).catch(() => []))
  moveForm.value = { parent_id: record.value.parent_id }
  moveOpen.value = true
}

const moveBed = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/facilities/${record.value.id}`, { method: 'PUT', body: { parent_id: moveForm.value.parent_id || null } })
    moveOpen.value = false
    await load()
  })
}

const unassignWard = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/facilities/${record.value.id}`, { method: 'PUT', body: { parent_id: null } })
    await load()
  })
}

const openAddBed = async () => {
  formError.value = ''
  const type = asList(await $api('/facility-types')).find(item => item.slug === 'bed')
  bedForm.value = { name: '', capacity: 1, facility_type_id: type?.id }
  bedOpen.value = true
}

const addBed = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/facilities', {
      method: 'POST',
      body: {
        ...bedForm.value,
        parent_id: record.value.id,
        department_id: record.value.department_id,
        status: 'available',
      },
    })
    bedOpen.value = false
    await load()
  })
}

const openChart = item => {
  encounterId.value = item.encounter_id || item.encounter?.id
  if (encounterId.value)
    chartOpen.value = true
}

const { pending, run } = usePageQuery(load)
watch(() => route.params.id, () => run())
</script>

<template>
  <HRecord
    :title="record?.name || listLabel"
    :subtitle="record ? (record.type?.name || '') : ''"
    :status="record?.status"
    :back="{ name: listTo }"
    :back-label="listLabel"
    :tabs="tabs"
    :tab="tab"
    :loading="pending"
    :missing="!pending && !record"
    @update:tab="tab = $event"
  >
    <div
      v-if="formError && !statusOpen && !editOpen && !assignOpen && !moveOpen && !bedOpen && !transferOpen"
      class="h-alert"
    >
      {{ formError }}
    </div>

    <template v-if="record && tab === 'overview'">
      <HGrid
        cols="4"
        kind="stats"
      >
        <HStat
          icon="hospital"
          title="Capacity"
          :value="record.capacity"
          hint="Rated from assigned beds when this is a ward"
        />
        <HStat
          icon="users"
          title="In use"
          :value="record.current_utilization"
        />
        <HStat
          icon="check"
          title="Open"
          :value="record.remaining_capacity"
          tone="ok"
        />
      </HGrid>
      <div class="h-detail">
        <HCard title="Unit">
          <template
            v-if="ability.can('update', 'Facility') || (isWard && ability.can('update', 'Ward')) || (isBed && ability.can('update', 'Bed')) || ability.can('manage', 'Facility')"
            #actions
          >
            <HActionMenu
              :compact="false"
              label="More"
              :actions="[
                { label: 'Update status', icon: 'wrench', if: ability.can('update', 'Facility') || (isWard && ability.can('update', 'Ward')) || (isBed && ability.can('update', 'Bed')), onSelect: openStatus },
                { label: 'Edit', icon: 'edit', if: ability.can('update', 'Facility'), onSelect: openEdit },
                { label: 'Move ward', icon: 'transfer', if: isBed && ability.can('update', 'Facility'), onSelect: openMove },
                { label: 'Unassign ward', icon: 'ban', if: isBed && record.parent_id && ability.can('update', 'Facility'), onSelect: unassignWard },
                { label: 'Remove', icon: 'trash', danger: true, if: ability.can('manage', 'Facility'), onSelect: () => { formError = ''; removing = true } },
              ]"
            />
          </template>
          <p>{{ record.resource_notes || 'No resource notes yet.' }}</p>
          <p
            v-if="record.notes"
            class="h-muted"
          >
            {{ record.notes }}
          </p>
        </HCard>
        <HCard title="Relationships">
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
            <span>{{ isBed ? 'Ward' : 'Parent' }}</span>
            <strong>
              <RouterLink
                v-if="record.parent?.id"
                class="h-inline-link"
                :to="facilityRecordTo(record.parent)"
              >
                {{ record.parent.name }}
              </RouterLink>
              <span v-else>{{ isBed ? 'Unassigned' : '—' }}</span>
            </strong>
          </div>
        </HCard>
      </div>
    </template>

    <HCard
      v-if="record && tab === 'beds'"
      title="Beds"
      flush
    >
      <template
        v-if="ability.can('create', 'Facility')"
        #actions
      >
        <HButton
          size="sm"
          @click="openAddBed"
        >
          Add bed
        </HButton>
      </template>
      <HTable
        :headers="[
          { title: 'Bed', key: 'name', fill: true },
          { title: 'Patient', key: 'patient' },
          { title: 'Status', key: 'status' },
        ]"
        :items="record.beds || []"
        empty="No beds are assigned to this ward"
      >
        <template #cell-name="{ item }">
          <HCell
            :to="{ name: 'beds-id', params: { id: item.id } }"
            :secondary="joinContext(record.hospital?.name, record.name)"
          >
            {{ item.name }}
          </HCell>
        </template>
        <template #cell-patient="{ item }">
          <HCell
            :to="(item.active_assignment || item.activeAssignment)?.patient?.id ? { name: 'patients-id', params: { id: (item.active_assignment || item.activeAssignment).patient.id } } : null"
          >
            {{ (item.active_assignment || item.activeAssignment)?.patient ? `${(item.active_assignment || item.activeAssignment).patient.first_name} ${(item.active_assignment || item.activeAssignment).patient.last_name}` : '—' }}
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
      v-if="record && tab === 'patients'"
      title="Current occupants"
      flush
    >
      <template
        v-if="isWard && ability.can('create', 'Bed')"
        #actions
      >
        <HButton
          size="sm"
          @click="openAssign"
        >
          Assign bed
        </HButton>
      </template>
      <HTable
        :headers="[
          { title: 'Patient', key: 'patient.first_name', fill: true },
          { title: 'Status', key: 'status' },
          { title: 'Actions', key: 'actions' },
        ]"
        :items="record.occupants || []"
        empty="No patients are occupying beds in this ward"
      >
        <template #cell-patient.first_name="{ item }">
          <HCell
            :to="item.patient?.id ? { name: 'patients-id', params: { id: item.patient.id } } : null"
            :secondary="item.facility?.name"
          >
            {{ item.patient?.full_name || `${item.patient?.first_name || ''} ${item.patient?.last_name || ''}`.trim() || '—' }}
          </HCell>
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <HActionMenu
            :actions="[
              { label: 'Open chart', icon: 'stethoscope', if: Boolean(item.encounter_id || item.encounter), onSelect: () => openChart(item) },
              { label: 'Transfer', icon: 'transfer', if: ability.can('update', 'Bed') && item.status === 'active', onSelect: () => openTransfer(item) },
              { label: 'Discharge', icon: 'door', if: ability.can('update', 'Bed') && item.status === 'active', onSelect: () => discharge(item) },
            ]"
          />
        </template>
      </HTable>
    </HCard>

    <HCard
      v-if="record && tab === 'patient'"
      title="Current patient"
    >
      <template
        v-if="isBed && ability.can('create', 'Bed') && !record.assignment"
        #actions
      >
        <HButton
          size="sm"
          @click="openAssign"
        >
          Assign patient
        </HButton>
      </template>
      <template v-if="record.assignment">
        <div class="h-metric">
          <span>Patient</span>
          <strong>
            <RouterLink
              class="h-inline-link"
              :to="{ name: 'patients-id', params: { id: record.assignment.patient.id } }"
            >
              {{ record.assignment.patient.first_name }} {{ record.assignment.patient.last_name }}
            </RouterLink>
          </strong>
        </div>
        <div class="h-metric">
          <span>Nurse</span>
          <strong>{{ record.assignment.nurse?.name || '—' }}</strong>
        </div>
        <div class="h-actions">
          <HActionMenu
            :compact="false"
            label="More"
            :actions="[
              { label: 'Open chart', icon: 'stethoscope', if: Boolean(record.assignment.encounter_id || record.assignment.encounter), onSelect: () => openChart(record.assignment) },
              { label: 'Transfer', icon: 'transfer', if: ability.can('update', 'Bed'), onSelect: () => openTransfer(record.assignment) },
              { label: 'Discharge', icon: 'door', if: ability.can('update', 'Bed'), onSelect: () => discharge(record.assignment) },
            ]"
          />
        </div>
      </template>
      <HEmpty
        v-else
        message="This bed is vacant"
      />
    </HCard>

    <HCard
      v-if="record && tab === 'units'"
      title="Child units"
      flush
    >
      <HTable
        :headers="[
          { title: 'Unit', key: 'name', fill: true },
          { title: 'Status', key: 'status' },
        ]"
        :items="[...(record.units || []), ...(record.beds || [])]"
        empty="No child units"
      >
        <template #cell-name="{ item }">
          <HCell
            :to="facilityRecordTo(item)"
            :secondary="joinContext(item.type?.name, item.hospital?.name, item.parent?.name)"
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
      v-if="record && tab === 'team'"
      title="Care team"
      flush
    >
      <HTable
        :headers="[
          { title: 'Staff', key: 'user.name', fill: true },
          { title: 'Shift', key: 'shift' },
        ]"
        :items="record.staff_assignments || []"
        empty="No staff are assigned to this unit"
      >
        <template #cell-user.name="{ item }">
          <HCell
            :to="item.user?.id && ability.can('read', 'User') ? { name: 'admin-users-id', params: { id: item.user.id } } : null"
            :secondary="item.assignment_role"
          >
            {{ item.user?.name || '—' }}
          </HCell>
        </template>
      </HTable>
    </HCard>

    <HCard
      v-if="record && tab === 'history'"
      title="Assignment history"
      flush
    >
      <HTable
        :headers="[
          { title: 'Patient', key: 'patient.first_name', fill: true },
          { title: 'Status', key: 'status' },
          { title: 'When', key: 'assigned_at' },
        ]"
        :items="record.history || []"
        empty="No assignment history"
      >
        <template #cell-patient.first_name="{ item }">
          <HCell
            :to="item.patient?.id ? { name: 'patients-id', params: { id: item.patient.id } } : null"
            :secondary="item.facility?.name"
          >
            {{ item.patient?.full_name || `${item.patient?.first_name || ''} ${item.patient?.last_name || ''}`.trim() || '—' }}
          </HCell>
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-assigned_at="{ item }">
          {{ formatWhen(item.assigned_at) }}
        </template>
      </HTable>
    </HCard>

    <HCard
      v-if="record && tab === 'history'"
      title="Activity"
      flush
    >
      <HTable
        :headers="[
          { title: 'Action', key: 'action' },
          { title: 'Actor', key: 'actor.name' },
          { title: 'When', key: 'created_at' },
        ]"
        :items="record.activity || []"
        empty="No activity recorded"
      >
        <template #cell-created_at="{ item }">
          {{ formatWhen(item.created_at) }}
        </template>
      </HTable>
    </HCard>

    <HModal
      v-model="statusOpen"
      title="Update status"
      :error="formError"
      :persistent="saving"
    >
      <HFormGrid>
        <HSelect
          v-model="statusForm.status"
          :items="facilityStatuses"
          label="Status"
        />
        <HNumber
          v-if="!isWard"
          v-model="statusForm.current_utilization"
          label="Current utilization"
          placeholder="e.g. 18"
          :min="0"
        />
        <HTextarea
          span
          v-model="statusForm.resource_notes"
          label="Resource notes"
          placeholder="Beds, equipment or hours available"
        />
      </HFormGrid>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="statusOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :loading="saving"
          :disabled="saving"
          @click="saveStatus"
        >
          Save
        </HButton>
      </template>
    </HModal>

    <HOffcanvas
      v-model="editOpen"
      title="Edit record"
      :error="formError"
      :persistent="saving"
    >
      <HFormGrid>
        <HInput
          v-model="form.name"
          label="Name"
          :placeholder="isBed ? 'e.g. Bed 12' : 'e.g. Surgical Ward'"
          required
        />
        <HSelect
          v-model="form.department_id"
          :items="departments"
          item-title="name"
          item-value="id"
          label="Department"
        />
        <HSelect
          v-if="isBed"
          v-model="form.parent_id"
          :items="parents"
          item-title="name"
          item-value="id"
          label="Ward"
        />
        <HNumber
          v-if="!isWard"
          v-model="form.capacity"
          label="Capacity"
          placeholder="e.g. 1"
          :min="1"
        />
      </HFormGrid>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="editOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :loading="saving"
          :disabled="saving"
          @click="saveEdit"
        >
          Save
        </HButton>
      </template>
    </HOffcanvas>

    <HModal
      v-model="assignOpen"
      title="Assign bed"
      :error="formError"
      :persistent="saving"
    >
      <HFormGrid>
        <HSelect
          v-model="assignForm.patient_id"
          :items="patients"
          item-title="full_name"
          item-value="id"
          label="Patient"
          required
        />
        <HSelect
          v-if="isWard"
          v-model="assignForm.facility_id"
          :items="availableBeds"
          item-title="name"
          item-value="id"
          label="Bed"
          required
        />
      </HFormGrid>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="assignOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :loading="saving"
          :disabled="saving || !assignForm.patient_id || (isWard && !assignForm.facility_id)"
          @click="assignBed"
        >
          Assign
        </HButton>
      </template>
    </HModal>

    <HModal
      v-model="transferOpen"
      title="Transfer patient"
      :error="formError"
      :persistent="saving"
    >
      <HSelect
        v-model="transferForm.facility_id"
        :items="availableBeds"
        item-title="name"
        item-value="id"
        label="Destination bed"
        required
      />
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="transferOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :loading="saving"
          :disabled="saving || !transferForm.facility_id"
          @click="transferAssignment"
        >
          Transfer
        </HButton>
      </template>
    </HModal>

    <HModal
      v-model="moveOpen"
      title="Move bed"
      :error="formError"
      :persistent="saving"
    >
      <HSelect
        v-model="moveForm.parent_id"
        :items="parents.length ? parents : asList([])"
        item-title="name"
        item-value="id"
        label="Ward"
      />
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="moveOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :loading="saving"
          :disabled="saving"
          @click="moveBed"
        >
          Save
        </HButton>
      </template>
    </HModal>

    <HModal
      v-model="bedOpen"
      title="Add bed"
      :error="formError"
      :persistent="saving"
    >
      <HFormGrid>
        <HInput
          v-model="bedForm.name"
          label="Name"
          placeholder="e.g. Bed 12"
          required
        />
      </HFormGrid>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="bedOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :loading="saving"
          :disabled="saving || !bedForm.name"
          @click="addBed"
        >
          Add
        </HButton>
      </template>
    </HModal>

    <HModal
      v-model="removing"
      title="Remove record"
      :error="formError"
      :persistent="saving"
    >
      <p>Remove {{ record?.name }}?</p>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="removing = false"
        >
          Keep
        </HButton>
        <HButton
          variant="danger"
          :loading="saving"
          :disabled="saving"
          @click="removeRecord"
        >
          Remove
        </HButton>
      </template>
    </HModal>

    <EncounterChart
      v-model="chartOpen"
      :encounter-id="encounterId"
      @saved="load"
    />
  </HRecord>
</template>
