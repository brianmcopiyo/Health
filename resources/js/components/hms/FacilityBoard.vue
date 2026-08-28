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
    withPageLoad(load, { silent: true })
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
  { title: 'Actions', key: 'actions' },
]
</script>

<template>
  <div>
    <HPage
      :title="title"
      subtitle="Live capacity, availability, and utilization"
    >
      <HButton
        variant="ghost"
        @click="load"
      >
        <HIcon name="refresh" />
        Refresh
      </HButton>
    </HPage>

    <div class="h-grid cols-4">
      <HStat
        title="Available"
        :value="board.stats.available"
      />
      <HStat
        title="Occupied"
        :value="board.stats.occupied"
      />
      <HStat
        title="Remaining capacity"
        :value="board.stats.remaining"
      />
      <HStat
        title="Utilization"
        :value="utilization"
      />
    </div>

    <HCard
      title="Operational units"
      style="margin-top:18px"
    >
      <HTable
        :headers="headers"
        :items="board.facilities"
        empty="No operational units in this module"
      >
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <HButton
            v-if="ability.can('update', subject)"
            variant="ghost"
            size="icon"
            @click="openStatus(item)"
          >
            <HIcon name="edit" />
          </HButton>
        </template>
      </HTable>
    </HCard>

    <HCard
      v-if="board.orders"
      title="Orders"
      style="margin-top:18px"
    >
      <div
        v-if="ability.can('create', subject)"
        class="h-grid cols-3"
        style="margin-bottom:16px"
      >
        <HSelect
          v-model="orderForm.patient_id"
          :items="patients"
          item-title="full_name"
          item-value="id"
          label="Patient"
        />
        <HInput
          v-model="orderForm.item_name"
          label="Test / item"
        />
        <div style="display:flex;align-items:flex-end">
          <HButton
            :disabled="!orderForm.patient_id || !orderForm.item_name"
            @click="createOrder"
          >
            Add order
          </HButton>
        </div>
      </div>
      <HTable
        :headers="[
          { title: 'Patient', key: 'patient.first_name' },
          { title: 'Item', key: 'item_name' },
          { title: 'Status', key: 'status' },
          { title: 'Actions', key: 'actions' },
        ]"
        :items="board.orders"
        empty="No orders yet"
      >
        <template #cell-patient.first_name="{ item }">
          {{ item.patient?.first_name }} {{ item.patient?.last_name }}
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <div class="h-actions">
            <HButton
              v-if="ability.can('update', subject) && item.status === 'pending'"
              variant="ghost"
              size="sm"
              @click="updateOrder(item, 'in_progress')"
            >
              Start
            </HButton>
            <HButton
              v-if="ability.can('update', subject) && item.status !== 'completed'"
              size="sm"
              @click="updateOrder(item, 'completed')"
            >
              Complete
            </HButton>
          </div>
        </template>
      </HTable>
    </HCard>

    <HCard
      v-if="board.assignments"
      title="Bed assignments"
      style="margin-top:18px"
    >
      <div
        v-if="ability.can('create', 'Bed')"
        class="h-grid cols-3"
        style="margin-bottom:16px"
      >
        <HSelect
          v-model="assignmentForm.patient_id"
          :items="patients"
          item-title="full_name"
          item-value="id"
          label="Patient"
        />
        <HSelect
          v-model="assignmentForm.facility_id"
          :items="availableBeds"
          item-title="name"
          item-value="id"
          label="Bed"
        />
        <div style="display:flex;align-items:flex-end">
          <HButton
            :disabled="!assignmentForm.patient_id || !assignmentForm.facility_id"
            @click="assignBed"
          >
            Assign bed
          </HButton>
        </div>
      </div>
      <HTable
        :headers="[
          { title: 'Patient', key: 'patient.first_name' },
          { title: 'Bed', key: 'facility.name' },
          { title: 'Status', key: 'status' },
          { title: 'Actions', key: 'actions' },
        ]"
        :items="board.assignments"
        empty="No bed assignments"
      >
        <template #cell-patient.first_name="{ item }">
          {{ item.patient?.first_name }} {{ item.patient?.last_name }}
        </template>
        <template #cell-actions="{ item }">
          <HButton
            v-if="ability.can('update', 'Bed')"
            variant="ghost"
            size="sm"
            @click="discharge(item)"
          >
            Discharge
          </HButton>
        </template>
      </HTable>
    </HCard>

    <HDialog
      v-model="statusDialog"
      title="Update unit status"
    >
      <div class="h-stack">
        <HSelect
          v-model="form.status"
          :items="facilityStatuses"
          label="Status"
        />
        <HInput
          v-model="form.current_utilization"
          type="number"
          label="Current utilization"
        />
        <HTextarea
          v-model="form.resource_notes"
          label="Resource notes"
        />
      </div>
      <template #actions>
        <HButton
          variant="ghost"
          @click="statusDialog = false"
        >
          Cancel
        </HButton>
        <HButton @click="saveStatus">
          Save
        </HButton>
      </template>
    </HDialog>
  </div>
</template>
