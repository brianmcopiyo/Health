<script setup>
import { facilityRecordTo } from '@/utils/helpers'
import { facilityStatuses, labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Facility',
  },
})

const ability = useAbility()
const facilities = ref([])
const types = ref([])
const meta = ref(asPageMeta())
const list = useListQuery(['status', 'facility_type_id', 'department_id'])
const { page, q, filterValues } = list
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const editing = ref(null)
const removing = ref(null)
const departments = ref([])
const form = ref({
  name: '',
  facility_type_id: null,
  parent_id: null,
  status: 'available',
  capacity: 1,
  current_utilization: 0,
  resource_notes: '',
  notes: '',
  department_id: null,
})

const headers = [
  { title: 'Facility', key: 'name', fill: true },
  { title: 'Status', key: 'status' },
  { title: 'Capacity', key: 'capacity' },
  { title: 'In use', key: 'current_utilization' },
  { title: 'Remaining', key: 'remaining_capacity' },
  { title: 'Actions', key: 'actions' },
]

const load = async () => {
  const [items, facilityTypes, departmentRows] = await Promise.all([
    $api('/facilities', { query: list.apiQuery() }),
    $api('/facility-types'),
    $api('/departments').catch(() => []),
  ])

  facilities.value = asList(items)
  meta.value = asPageMeta(items)
  types.value = asList(facilityTypes)
  departments.value = asList(departmentRows)
}

const openCreate = () => {
  formError.value = ''
  editing.value = null
  form.value = {
    name: '',
    facility_type_id: types.value[0]?.id ?? null,
    parent_id: null,
    status: 'available',
    capacity: 1,
    current_utilization: 0,
    resource_notes: '',
    notes: '',
    department_id: null,
  }
  formOpen.value = true
}

const openEdit = item => {
  formError.value = ''
  editing.value = item
  form.value = {
    name: item.name,
    facility_type_id: item.facility_type_id,
    parent_id: item.parent_id,
    status: item.status,
    capacity: item.capacity,
    current_utilization: item.current_utilization,
    resource_notes: item.resource_notes,
    notes: item.notes,
    department_id: item.department_id,
  }
  formOpen.value = true
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    const payload = { ...form.value }
    if (editing.value)
      await $api(`/facilities/${editing.value.id}`, { method: 'PUT', body: payload })
    else
      await $api('/facilities', { method: 'POST', body: payload })

    formOpen.value = false
    await load()
  })
}

const removeFacility = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/facilities/${removing.value.id}`, { method: 'DELETE' })
    removing.value = null
    await load()
  })
}

list.sync(load)
const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Facilities"
      subtitle="Capacity and live unit status"
    >
      <HExportActions
        dataset="facilities"
        :query="list.apiQuery()"
        :disabled="pending"
      />
      <HButton
        v-if="ability.can('create', 'Facility')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Add facility
      </HButton>
    </HPage>

    <HCard flush>
      <HListToolbar
        v-model:search="q"
        v-model:values="filterValues"
        search-placeholder="Search facilities"
        search-button
        :result-count="list.resultCount(meta)"
        :filters="[
          { key: 'facility_type_id', type: 'select', label: 'Type', placeholder: 'All types', items: types, itemTitle: 'name', itemValue: 'id', optional: true, empty: null },
          { key: 'status', type: 'select', label: 'Status', placeholder: 'All statuses', optional: true, empty: null, items: facilityStatuses.map(value => ({ title: labelize(value), value })) },
          { key: 'department_id', type: 'select', label: 'Department', placeholder: 'All departments', items: departments, itemTitle: 'name', itemValue: 'id', optional: true, empty: null, more: true },
        ]"
        @search="list.onSearch(load)"
        @change="list.onChange(load)"
      />
      <HTable
        :loading="pending"
        :headers="headers"
        :items="facilities"
        empty="No facilities match these filters"
      >
        <template #cell-name="{ item }">
          <HCell
            :to="facilityRecordTo(item)"
            :secondary="joinContext(item.type?.name, item.hospital?.name)"
          >
            {{ item.name }}
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
              { label: 'View', icon: 'eye', to: facilityRecordTo(item) },
              { label: 'Edit', icon: 'edit', if: ability.can('update', 'Facility'), onSelect: () => openEdit(item) },
              { label: 'Remove', icon: 'trash', danger: true, if: ability.can('manage', 'Facility'), onSelect: () => { formError = ''; removing = item } },
            ]"
          />
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => list.onPage(value, load)"
      />
    </HCard>

    <HOffcanvas
      v-model="formOpen"
      :title="editing ? 'Update facility' : 'Add facility'"
      size="lg"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
        class="h-form-grid"
        :disabled="saving"
      >
        <HInput
          span
          v-model="form.name"
          label="Name"
          placeholder="e.g. Surgical Ward"
          required
        />
        <HSelect
          v-model="form.facility_type_id"
          :items="types"
          item-title="name"
          item-value="id"
          label="Type"
          required
        />
        <HSelect
          v-model="form.parent_id"
          :items="facilities.filter(item => item.id !== editing?.id)"
          item-title="name"
          item-value="id"
          label="Parent unit"
        />
        <HSelect
          v-model="form.department_id"
          :items="departments"
          item-title="name"
          item-value="id"
          label="Department"
        />
        <HSelect
          v-model="form.status"
          :items="facilityStatuses"
          label="Status"
        />
        <HNumber
          v-model="form.capacity"
          label="Capacity"
          placeholder="e.g. 24"
          :min="1"
        />
        <HNumber
          v-model="form.current_utilization"
          label="Current utilization"
          placeholder="e.g. 18"
          :min="0"
        />
        <HTextarea
          span
          v-model="form.resource_notes"
          label="Resource availability"
          placeholder="Beds, equipment or hours available"
          hint="Visible to referral and assistance matching"
        />
      </fieldset>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="formOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :loading="saving"
          :disabled="saving"
          @click="save"
        >
          Save
        </HButton>
      </template>
    </HOffcanvas>

    <HModal
      :model-value="Boolean(removing)"
      title="Remove facility"
      :error="formError"
      :persistent="saving"
      @update:model-value="val => { if (!val) removing = null }"
    >
      <p>Remove {{ removing?.name }}?</p>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="removing = null"
        >
          Keep
        </HButton>
        <HButton
          variant="danger"
          :loading="saving"
          :disabled="saving"
          @click="removeFacility"
        >
          Remove
        </HButton>
      </template>
    </HModal>
  </div>
</template>
