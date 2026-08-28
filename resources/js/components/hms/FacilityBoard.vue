<script setup>
import EncounterChart from '@/components/hms/EncounterChart.vue'
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
const statusOpen = ref(false)
const orderOpen = ref(false)
const assignmentOpen = ref(false)
const selected = ref(null)
const saving = ref(false)
const formError = ref('')
const form = ref({
  status: 'available',
  current_utilization: 0,
  resource_notes: '',
})

const orderForm = ref({
  patient_id: null,
  encounter_id: null,
  item_name: '',
  notes: '',
})
const resultOpen = ref(false)
const resultForm = ref({ id: null, result: '' })
const patients = ref([])
const orderEncounters = ref([])
const chartOpen = ref(false)
const encounterId = ref(null)
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

const encounterOptions = computed(() => orderEncounters.value.map(item => ({
  title: `${labelize(item.type)} · ${item.chief_complaint || labelize(item.status)}`,
  value: item.id,
})))

watch(() => orderForm.value.patient_id, async id => {
  orderForm.value.encounter_id = null
  if (!id) {
    orderEncounters.value = []
    return
  }
  try {
    orderEncounters.value = asList(await $api('/encounters', { query: { patient_id: id, open: true } }))
  }
  catch {
    orderEncounters.value = []
  }
})

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
  formError.value = ''
  selected.value = item
  form.value = {
    status: item.status,
    current_utilization: item.current_utilization,
    resource_notes: item.resource_notes || '',
  }
  statusOpen.value = true
}

const openOrder = () => {
  formError.value = ''
  orderForm.value = { patient_id: null, encounter_id: null, item_name: '', notes: '' }
  orderOpen.value = true
}

const openAssignment = () => {
  formError.value = ''
  assignmentForm.value = { patient_id: null, facility_id: null }
  assignmentOpen.value = true
}

const saveStatus = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/modules/${props.moduleKey}/facilities/${selected.value.id}/status`, {
      method: 'PATCH',
      body: form.value,
    })
    statusOpen.value = false
    await load()
  })
}

const createOrder = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/service-orders', {
      method: 'POST',
      body: {
        module_key: props.moduleKey,
        ...orderForm.value,
      },
    })
    orderOpen.value = false
    await load()
  })
}

const updateOrder = async (order, status, result = null) => {
  const body = { status }
  if (result !== null)
    body.result = result
  await $api(`/service-orders/${order.id}`, {
    method: 'PATCH',
    body,
  })
  await load()
}

const openResult = order => {
  formError.value = ''
  resultForm.value = { id: order.id, result: order.result || '' }
  resultOpen.value = true
}

const saveResult = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/service-orders/${resultForm.value.id}`, {
      method: 'PATCH',
      body: { status: 'completed', result: resultForm.value.result },
    })
    resultOpen.value = false
    await load()
  })
}

const assignBed = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/bed-assignments', {
      method: 'POST',
      body: assignmentForm.value,
    })
    assignmentOpen.value = false
    await load()
  })
}

const discharge = async assignment => {
  await $api(`/bed-assignments/${assignment.id}/discharge`, { method: 'PATCH' })
  await load()
}

const openChart = item => {
  encounterId.value = item.encounter_id || item.encounter?.id
  if (encounterId.value)
    chartOpen.value = true
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
      <template
        v-if="ability.can('create', subject)"
        #actions
      >
        <HButton
          size="sm"
          @click="openOrder"
        >
          <HIcon name="plus" />
          Add order
        </HButton>
      </template>
      <HTable
        :headers="[
          { title: 'Patient', key: 'patient.first_name' },
          { title: 'Item', key: 'item_name' },
          { title: 'Encounter', key: 'encounter.type' },
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
              v-if="ability.can('update', subject) && item.status === 'requested' && moduleKey === 'laboratory'"
              variant="ghost"
              size="sm"
              @click="updateOrder(item, 'collected')"
            >
              Collect
            </HButton>
            <HButton
              v-if="ability.can('update', subject) && item.status === 'requested' && moduleKey === 'imaging'"
              variant="ghost"
              size="sm"
              @click="updateOrder(item, 'scheduled')"
            >
              Schedule
            </HButton>
            <HButton
              v-if="ability.can('update', subject) && ['requested', 'collected', 'scheduled'].includes(item.status)"
              variant="ghost"
              size="sm"
              @click="updateOrder(item, 'processing')"
            >
              Process
            </HButton>
            <HButton
              v-if="ability.can('update', subject) && item.status !== 'completed' && item.status !== 'cancelled'"
              size="sm"
              @click="openResult(item)"
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
      <template
        v-if="ability.can('create', 'Bed')"
        #actions
      >
        <HButton
          size="sm"
          @click="openAssignment"
        >
          <HIcon name="plus" />
          Assign bed
        </HButton>
      </template>
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
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <div class="h-actions">
            <HButton
              v-if="item.encounter_id || item.encounter"
              size="sm"
              variant="ghost"
              @click="openChart(item)"
            >
              Chart
            </HButton>
            <HButton
              v-if="ability.can('update', 'Bed')"
              variant="ghost"
              size="sm"
              @click="discharge(item)"
            >
              Discharge
            </HButton>
          </div>
        </template>
      </HTable>
    </HCard>

    <HModal
      v-model="statusOpen"
      title="Update unit status"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
        class="h-stack"
        :disabled="saving"
      >
        <HSelect
          v-model="form.status"
          :items="facilityStatuses"
          label="Status"
        />
        <HNumber
          v-model="form.current_utilization"
          label="Current utilization"
          :min="0"
        />
        <HTextarea
          v-model="form.resource_notes"
          label="Resource notes"
        />
      </fieldset>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="statusOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving"
          @click="saveStatus"
        >
          Save
        </HButton>
      </template>
    </HModal>

    <HModal
      v-model="orderOpen"
      title="Add order"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
        class="h-stack"
        :disabled="saving"
      >
        <HSelect
          v-model="orderForm.patient_id"
          :items="patients"
          item-title="full_name"
          item-value="id"
          label="Patient"
          required
        />
        <HSelect
          v-if="encounterOptions.length"
          v-model="orderForm.encounter_id"
          :items="encounterOptions"
          label="Encounter"
        />
        <HInput
          v-model="orderForm.item_name"
          label="Test / item"
          required
        />
        <HTextarea
          v-model="orderForm.notes"
          label="Notes"
        />
      </fieldset>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="orderOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving || !orderForm.patient_id || !orderForm.item_name"
          @click="createOrder"
        >
          Add order
        </HButton>
      </template>
    </HModal>

    <HModal
      v-model="assignmentOpen"
      title="Assign bed"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
        class="h-stack"
        :disabled="saving"
      >
        <HSelect
          v-model="assignmentForm.patient_id"
          :items="patients"
          item-title="full_name"
          item-value="id"
          label="Patient"
          required
        />
        <HSelect
          v-model="assignmentForm.facility_id"
          :items="availableBeds"
          item-title="name"
          item-value="id"
          label="Bed"
          required
        />
      </fieldset>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="assignmentOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving || !assignmentForm.patient_id || !assignmentForm.facility_id"
          @click="assignBed"
        >
          Assign bed
        </HButton>
      </template>
    </HModal>

    <HModal
      v-model="resultOpen"
      title="Record result"
      :error="formError"
      :persistent="saving"
    >
      <HTextarea
        v-model="resultForm.result"
        label="Result"
      />
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="resultOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving || !resultForm.result"
          @click="saveResult"
        >
          Complete order
        </HButton>
      </template>
    </HModal>

    <EncounterChart
      v-model="chartOpen"
      :encounter-id="encounterId"
      @saved="load"
    />
  </div>
</template>
