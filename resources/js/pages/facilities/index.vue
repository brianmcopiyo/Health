<script setup>
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
const search = ref('')
const status = ref(null)
const typeId = ref(null)
const isDialogVisible = ref(false)
const editing = ref(null)
const form = ref({
  name: '',
  code: '',
  facility_type_id: null,
  parent_id: null,
  status: 'available',
  capacity: 1,
  current_utilization: 0,
  resource_notes: '',
  notes: '',
})

const headers = [
  { title: 'Facility', key: 'name' },
  { title: 'Type', key: 'type.name' },
  { title: 'Status', key: 'status' },
  { title: 'Capacity', key: 'capacity' },
  { title: 'In use', key: 'current_utilization' },
  { title: 'Remaining', key: 'remaining_capacity' },
  { title: 'Actions', key: 'actions', sortable: false },
]

const typeItems = computed(() => [{ id: null, name: 'All types' }, ...(Array.isArray(types.value) ? types.value : [])])
const statusItems = computed(() => [{ value: null, title: 'All statuses' }, ...facilityStatuses.map(item => ({ value: item, title: labelize(item) }))])

const load = async () => {
  const query = {}
  if (search.value)
    query.q = search.value
  if (status.value)
    query.status = status.value
  if (typeId.value)
    query.facility_type_id = typeId.value

  const [items, facilityTypes] = await Promise.all([
    $api('/facilities', { query }),
    $api('/facility-types'),
  ])

  facilities.value = asList(items)
  types.value = asList(facilityTypes)
}

const openCreate = () => {
  editing.value = null
  form.value = {
    name: '',
    code: '',
    facility_type_id: types.value[0]?.id ?? null,
    parent_id: null,
    status: 'available',
    capacity: 1,
    current_utilization: 0,
    resource_notes: '',
    notes: '',
  }
  isDialogVisible.value = true
}

const openEdit = item => {
  editing.value = item
  form.value = {
    name: item.name,
    code: item.code,
    facility_type_id: item.facility_type_id,
    parent_id: item.parent_id,
    status: item.status,
    capacity: item.capacity,
    current_utilization: item.current_utilization,
    resource_notes: item.resource_notes,
    notes: item.notes,
  }
  isDialogVisible.value = true
}

const save = async () => {
  const payload = { ...form.value }
  if (editing.value)
    await $api(`/facilities/${editing.value.id}`, { method: 'PUT', body: payload })
  else
    await $api('/facilities', { method: 'POST', body: payload })

  isDialogVisible.value = false
  await load()
}

await withPageLoad(load)
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Facilities</VCardTitle>
      <template #append>
        <VBtn
          v-if="ability.can('create', 'Facility')"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          Add facility
        </VBtn>
      </template>
    </VCardItem>

    <VCardText>
      <VRow>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            v-model="search"
            placeholder="Search facilities"
            prepend-inner-icon="tabler-search"
            @update:model-value="load"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppSelect
            v-model="typeId"
            :items="typeItems"
            item-title="name"
            item-value="id"
            label="Type"
            @update:model-value="load"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppSelect
            v-model="status"
            :items="statusItems"
            item-title="title"
            item-value="value"
            label="Status"
            @update:model-value="load"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDataTable
      :headers="headers"
      :items="facilities"
    >
      <template #item.status="{ item }">
        <VChip
          size="small"
          :color="statusColor(item.status)"
          class="text-capitalize"
        >
          {{ labelize(item.status) }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <IconBtn :to="{ name: 'facilities-id', params: { id: item.id } }">
          <VIcon icon="tabler-eye" />
        </IconBtn>
        <IconBtn
          v-if="ability.can('update', 'Facility')"
          @click="openEdit(item)"
        >
          <VIcon icon="tabler-edit" />
        </IconBtn>
      </template>
    </VDataTable>
  </VCard>

  <VDialog
    v-model="isDialogVisible"
    max-width="640"
  >
    <VCard :title="editing ? 'Update facility' : 'Add facility'">
      <VCardText>
        <VRow>
          <VCol cols="12">
            <AppTextField
              v-model="form.name"
              label="Name"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="form.code"
              label="Code"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="form.facility_type_id"
              :items="types"
              item-title="name"
              item-value="id"
              label="Type"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppSelect
              v-model="form.status"
              :items="facilityStatuses"
              label="Status"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model.number="form.capacity"
              type="number"
              label="Capacity"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model.number="form.current_utilization"
              type="number"
              label="Current utilization"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.resource_notes"
              label="Resource availability"
            />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          @click="isDialogVisible = false"
        >
          Cancel
        </VBtn>
        <VBtn @click="save">
          Save
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
