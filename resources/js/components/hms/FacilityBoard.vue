<script setup>
import { facilityStatuses, labelize, statusColor } from '@/utils/status'

const props = defineProps({
  moduleKey: {
    type: String,
    required: true,
  },
  title: {
    type: String,
    required: true,
  },
  subject: {
    type: String,
    required: true,
  },
})

const ability = useAbility()
const modulesWithOrders = ['emergency', 'theatre', 'laboratory', 'imaging', 'pharmacy']

const emptyBoard = () => ({
  stats: {
    total: 0,
    available: 0,
    occupied: 0,
    maintenance: 0,
    unavailable: 0,
    reserved: 0,
    capacity: 0,
    utilization: 0,
    remaining: 0,
  },
  facilities: [],
  departments: [],
  orders: modulesWithOrders.includes(props.moduleKey) ? [] : null,
  assignments: props.moduleKey === 'beds' ? [] : null,
})

const board = ref(emptyBoard())
const statusDialog = ref(false)
const selected = ref(null)
const form = ref({
  status: 'available',
  current_utilization: 0,
  resource_notes: '',
})

const orderForm = ref({
  patient_id: null,
  item_name: '',
  notes: '',
})
const patients = ref([])
const assignmentForm = ref({
  patient_id: null,
  facility_id: null,
})

const utilization = computed(() => {
  if (!board.value.stats.capacity)
    return '0%'

  return `${Math.round((board.value.stats.utilization / board.value.stats.capacity) * 100)}%`
})

const availableBeds = computed(() => board.value.facilities.filter(item => item.status === 'available' && item.remaining_capacity > 0))

const load = async () => {
  const payload = await $api(`/modules/${props.moduleKey}`)
  board.value = {
    ...emptyBoard(),
    ...payload,
    stats: { ...emptyBoard().stats, ...(payload?.stats || {}) },
    facilities: asList(payload?.facilities),
    departments: asList(payload?.departments),
    orders: payload?.orders === undefined ? emptyBoard().orders : asList(payload.orders),
    assignments: payload?.assignments === undefined ? emptyBoard().assignments : asList(payload.assignments),
  }

  if (ability.can('read', 'Patient') && (board.value.orders || board.value.assignments))
    patients.value = asList(await $api('/patients'))
}

const openStatus = item => {
  selected.value = item
  form.value = {
    status: item.status,
    current_utilization: item.current_utilization,
    resource_notes: item.resource_notes || '',
  }
  statusDialog.value = true
}

const saveStatus = async () => {
  await $api(`/modules/${props.moduleKey}/facilities/${selected.value.id}/status`, {
    method: 'PATCH',
    body: form.value,
  })
  statusDialog.value = false
  await load()
}

const createOrder = async () => {
  await $api('/service-orders', {
    method: 'POST',
    body: {
      module_key: props.moduleKey,
      ...orderForm.value,
    },
  })
  orderForm.value = { patient_id: null, item_name: '', notes: '' }
  await load()
}

const updateOrder = async (order, status) => {
  await $api(`/service-orders/${order.id}`, {
    method: 'PATCH',
    body: { status },
  })
  await load()
}

const assignBed = async () => {
  await $api('/bed-assignments', {
    method: 'POST',
    body: assignmentForm.value,
  })
  assignmentForm.value = { patient_id: null, facility_id: null }
  await load()
}

const discharge = async assignment => {
  await $api(`/bed-assignments/${assignment.id}/discharge`, { method: 'PATCH' })
  await load()
}

await withPageLoad(load)

let timer
onMounted(() => {
  timer = setInterval(() => {
    withPageLoad(load)
  }, 15000)
})
onBeforeUnmount(() => {
  if (timer)
    clearInterval(timer)
})

const headers = [
  { title: 'Unit', key: 'name' },
  { title: 'Code', key: 'code' },
  { title: 'Status', key: 'status' },
  { title: 'Capacity', key: 'capacity' },
  { title: 'In use', key: 'current_utilization' },
  { title: 'Remaining', key: 'remaining_capacity' },
  { title: 'Actions', key: 'actions', sortable: false },
]
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h4 class="text-h4">
          {{ title }}
        </h4>
        <div class="text-body-1">
          Live capacity, availability, and utilization
        </div>
      </div>
      <VBtn
        variant="tonal"
        prepend-icon="tabler-refresh"
        @click="load"
      >
        Refresh
      </VBtn>
    </div>

    <VRow class="mb-6">
      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard>
          <VCardText>
            <div class="text-body-2 mb-1">
              Available
            </div>
            <div class="text-h5">
              {{ board.stats.available }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard>
          <VCardText>
            <div class="text-body-2 mb-1">
              Occupied
            </div>
            <div class="text-h5">
              {{ board.stats.occupied }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard>
          <VCardText>
            <div class="text-body-2 mb-1">
              Remaining capacity
            </div>
            <div class="text-h5">
              {{ board.stats.remaining }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard>
          <VCardText>
            <div class="text-body-2 mb-1">
              Utilization
            </div>
            <div class="text-h5">
              {{ utilization }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard>
      <VCardItem>
        <VCardTitle>Operational units</VCardTitle>
      </VCardItem>
      <VDataTable
        :headers="headers"
        :items="board.facilities"
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
          <IconBtn
            v-if="ability.can('update', subject)"
            @click="openStatus(item)"
          >
            <VIcon icon="tabler-edit" />
          </IconBtn>
        </template>
      </VDataTable>
    </VCard>

    <VCard
      v-if="board.orders"
      class="mt-6"
    >
      <VCardItem>
        <VCardTitle>Orders</VCardTitle>
      </VCardItem>
      <VCardText v-if="ability.can('create', subject)">
        <VRow>
          <VCol md="4">
            <AppSelect
              v-model="orderForm.patient_id"
              :items="patients"
              item-title="full_name"
              item-value="id"
              label="Patient"
            />
          </VCol>
          <VCol md="4">
            <AppTextField
              v-model="orderForm.item_name"
              label="Test / item"
            />
          </VCol>
          <VCol md="4">
            <VBtn
              :disabled="!orderForm.patient_id || !orderForm.item_name"
              @click="createOrder"
            >
              Add order
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
      <VDataTable
        :headers="[
          { title: 'Patient', key: 'patient.first_name' },
          { title: 'Item', key: 'item_name' },
          { title: 'Status', key: 'status' },
          { title: 'Actions', key: 'actions', sortable: false },
        ]"
        :items="board.orders"
      >
        <template #item.patient.first_name="{ item }">
          {{ item.patient?.first_name }} {{ item.patient?.last_name }}
        </template>
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
          <VBtn
            v-if="ability.can('update', subject) && item.status === 'pending'"
            size="small"
            variant="tonal"
            class="me-2"
            @click="updateOrder(item, 'in_progress')"
          >
            Start
          </VBtn>
          <VBtn
            v-if="ability.can('update', subject) && item.status !== 'completed'"
            size="small"
            @click="updateOrder(item, 'completed')"
          >
            Complete
          </VBtn>
        </template>
      </VDataTable>
    </VCard>

    <VCard
      v-if="board.assignments"
      class="mt-6"
    >
      <VCardItem>
        <VCardTitle>Bed assignments</VCardTitle>
      </VCardItem>
      <VCardText v-if="ability.can('create', 'Bed')">
        <VRow>
          <VCol md="4">
            <AppSelect
              v-model="assignmentForm.patient_id"
              :items="patients"
              item-title="full_name"
              item-value="id"
              label="Patient"
            />
          </VCol>
          <VCol md="4">
            <AppSelect
              v-model="assignmentForm.facility_id"
              :items="availableBeds"
              item-title="name"
              item-value="id"
              label="Bed"
            />
          </VCol>
          <VCol md="4">
            <VBtn
              :disabled="!assignmentForm.patient_id || !assignmentForm.facility_id"
              @click="assignBed"
            >
              Assign bed
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
      <VDataTable
        :headers="[
          { title: 'Patient', key: 'patient.first_name' },
          { title: 'Bed', key: 'facility.name' },
          { title: 'Status', key: 'status' },
          { title: 'Actions', key: 'actions', sortable: false },
        ]"
        :items="board.assignments"
      >
        <template #item.patient.first_name="{ item }">
          {{ item.patient?.first_name }} {{ item.patient?.last_name }}
        </template>
        <template #item.actions="{ item }">
          <VBtn
            v-if="ability.can('update', 'Bed')"
            size="small"
            variant="tonal"
            @click="discharge(item)"
          >
            Discharge
          </VBtn>
        </template>
      </VDataTable>
    </VCard>

    <VDialog
      v-model="statusDialog"
      max-width="520"
    >
      <VCard title="Update unit status">
        <VCardText>
          <AppSelect
            v-model="form.status"
            :items="facilityStatuses"
            label="Status"
            class="mb-4"
          />
          <AppTextField
            v-model.number="form.current_utilization"
            type="number"
            label="Current utilization"
            class="mb-4"
          />
          <AppTextarea
            v-model="form.resource_notes"
            label="Resource notes"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            @click="statusDialog = false"
          >
            Cancel
          </VBtn>
          <VBtn @click="saveStatus">
            Save
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
