<script setup>
import { facilityRecordTo } from '@/utils/helpers'
import { facilityStatuses, labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Facility',
  },
})

const route = useRoute()
const ability = useAbility()
const facilities = ref([])
const types = ref([])
const meta = ref(asPageMeta())
const page = ref(1)
const search = ref('')
const status = ref(null)
const typeId = ref(null)
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
  { title: 'Facility', key: 'name' },
  { title: 'Type', key: 'type.name' },
  { title: 'Status', key: 'status' },
  { title: 'Capacity', key: 'capacity' },
  { title: 'In use', key: 'current_utilization' },
  { title: 'Remaining', key: 'remaining_capacity' },
  { title: 'Actions', key: 'actions' },
]

const load = async () => {
  const query = { page: page.value }
  if (search.value)
    query.q = search.value
  if (status.value)
    query.status = status.value
  if (typeId.value)
    query.facility_type_id = typeId.value
  if (route.query.department_id)
    query.department_id = route.query.department_id

  const [items, facilityTypes] = await Promise.all([
    $api('/facilities', { query }),
    $api('/facility-types'),
  ])

  facilities.value = asList(items)
  meta.value = asPageMeta(items)
  types.value = asList(facilityTypes)
  if (ability.can('update', 'Facility') || ability.can('create', 'Facility'))
    departments.value = asList(await $api('/departments').catch(() => []))
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

const filterValues = computed({
  get: () => ({ typeId: typeId.value, status: status.value }),
  set: next => {
    typeId.value = next.typeId
    status.value = next.status
  },
})

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
        :query="{ q: search || undefined, status: status || undefined, facility_type_id: typeId || undefined, department_id: route.query.department_id || undefined }"
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
        v-model:search="search"
        v-model:values="filterValues"
        search-placeholder="Search facilities"
        search-mode="live"
        :filters="[
          { key: 'typeId', type: 'select', label: 'Type', placeholder: 'All types', items: types, itemTitle: 'name', itemValue: 'id' },
          { key: 'status', type: 'select', label: 'Status', placeholder: 'All statuses', items: facilityStatuses },
        ]"
        @change="load"
      />
      <HTable
        :loading="pending"
        :headers="headers"
        :items="facilities"
        empty="No facilities match these filters"
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
        <template #cell-actions="{ item }">
          <div class="h-actions">
            <HButton
              variant="ghost"
              size="icon"
              :to="facilityRecordTo(item)"
            >
              <HIcon name="eye" />
            </HButton>
            <HButton
              v-if="ability.can('update', 'Facility')"
              variant="ghost"
              size="icon"
              @click="openEdit(item)"
            >
              <HIcon name="edit" />
            </HButton>
            <HButton
              v-if="ability.can('manage', 'Facility')"
              variant="ghost"
              size="sm"
              @click="formError = ''; removing = item"
            >
              Remove
            </HButton>
          </div>
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => { page = value; load() }"
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
          :disabled="saving"
          @click="removeFacility"
        >
          Remove
        </HButton>
      </template>
    </HModal>
  </div>
</template>
