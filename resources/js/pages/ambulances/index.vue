<script setup>
import { ambulanceStatuses, labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Ambulance',
  },
})

const ability = useAbility()
const ambulances = ref([])
const trips = ref([])
const hospitals = ref([])
const isVehicleDialogVisible = ref(false)
const dispatching = ref(null)
const saving = ref(false)
const formError = ref('')
const form = ref({
  vehicle_code: '',
  vehicle_type: 'van',
  status: 'available',
  capacity: 2,
  notes: '',
})
const dispatchForm = ref({
  origin: '',
  destination: '',
  destination_hospital_id: null,
  notes: '',
})

const headers = [
  { title: 'Vehicle', key: 'vehicle_code' },
  { title: 'Type', key: 'vehicle_type' },
  { title: 'Status', key: 'status' },
  { title: 'Capacity', key: 'capacity' },
  { title: 'Actions', key: 'actions' },
]

const tripHeaders = [
  { title: 'Vehicle', key: 'ambulance.vehicle_code' },
  { title: 'Origin', key: 'origin' },
  { title: 'Destination', key: 'destination' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions' },
]

const load = async () => {
  const [fleet, history] = await Promise.all([
    $api('/ambulances'),
    $api('/ambulance-trips'),
  ])
  ambulances.value = asList(fleet)
  trips.value = asList(history)
}

const openCreate = () => {
  formError.value = ''
  form.value = {
    vehicle_code: '',
    vehicle_type: 'van',
    status: 'available',
    capacity: 2,
    notes: '',
  }
  isVehicleDialogVisible.value = true
}

const saveVehicle = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/ambulances', { method: 'POST', body: form.value })
    isVehicleDialogVisible.value = false
    await load()
  })
}

const openDispatch = async item => {
  formError.value = ''
  hospitals.value = asList(await $api('/network/hospitals'))
  dispatching.value = item
  dispatchForm.value = {
    origin: '',
    destination: '',
    destination_hospital_id: null,
    notes: '',
  }
}

const dispatch = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/ambulances/${dispatching.value.id}/dispatch`, {
      method: 'POST',
      body: dispatchForm.value,
    })
    dispatching.value = null
    await load()
  })
}

const updateTrip = async (trip, status) => {
  await $api(`/ambulance-trips/${trip.id}/status`, {
    method: 'PATCH',
    body: { status },
  })
  await load()
}

await withPageLoad(load)
</script>

<template>
  <div>
    <HPage
      title="Ambulance fleet"
      subtitle="Vehicles, dispatch, and trip status"
    >
      <HButton
        v-if="ability.can('create', 'Ambulance')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Add vehicle
      </HButton>
    </HPage>

    <HCard title="Vehicles">
      <HTable
        :headers="headers"
        :items="ambulances"
        empty="No ambulances registered"
      >
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
              :to="{ name: 'ambulances-id', params: { id: item.id } }"
            >
              <HIcon name="eye" />
            </HButton>
            <HButton
              v-if="ability.can('dispatch', 'Ambulance') && item.status === 'available'"
              size="sm"
              @click="openDispatch(item)"
            >
              Dispatch
            </HButton>
          </div>
        </template>
      </HTable>
    </HCard>

    <HCard
      title="Trips"
      style="margin-top:18px"
    >
      <HTable
        :headers="tripHeaders"
        :items="trips"
        empty="No trips recorded"
      >
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <div class="h-actions">
            <HButton
              v-if="item.status === 'dispatched' && ability.can('dispatch', 'Ambulance')"
              size="sm"
              @click="updateTrip(item, 'en_route')"
            >
              En route
            </HButton>
            <HButton
              v-if="['dispatched', 'en_route'].includes(item.status) && ability.can('dispatch', 'Ambulance')"
              variant="ghost"
              size="sm"
              @click="updateTrip(item, 'arrived')"
            >
              Arrived
            </HButton>
            <HButton
              v-if="['dispatched', 'en_route', 'arrived'].includes(item.status) && ability.can('dispatch', 'Ambulance')"
              variant="ok"
              size="sm"
              @click="updateTrip(item, 'completed')"
            >
              Complete
            </HButton>
          </div>
        </template>
      </HTable>
    </HCard>

    <HModal
      v-model="isVehicleDialogVisible"
      title="Add ambulance"
      :error="formError"
      :persistent="saving"
    >
      <div class="h-stack">
        <HInput
          v-model="form.vehicle_code"
          label="Vehicle code"
        />
        <HInput
          v-model="form.vehicle_type"
          label="Vehicle type"
        />
        <HSelect
          v-model="form.status"
          :items="ambulanceStatuses"
          label="Status"
        />
        <HInput
          v-model="form.capacity"
          type="number"
          label="Capacity"
        />
      </div>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="isVehicleDialogVisible = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving"
          @click="saveVehicle"
        >
          Save
        </HButton>
      </template>
    </HModal>

    <HModal
      :model-value="Boolean(dispatching)"
      title="Dispatch ambulance"
      :error="formError"
      :persistent="saving"
      @update:model-value="val => { if (!val) dispatching = null }"
    >
      <div
        v-if="dispatching"
        class="h-stack"
      >
        <HInput
          v-model="dispatchForm.origin"
          label="Origin"
        />
        <HInput
          v-model="dispatchForm.destination"
          label="Destination"
        />
        <HSelect
          v-model="dispatchForm.destination_hospital_id"
          :items="hospitals"
          item-title="name"
          item-value="id"
          label="Destination hospital"
        />
        <HTextarea
          v-model="dispatchForm.notes"
          label="Notes"
        />
      </div>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="dispatching = null"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving"
          @click="dispatch"
        >
          Dispatch
        </HButton>
      </template>
    </HModal>
  </div>
</template>
