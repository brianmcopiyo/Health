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
  { title: 'Actions', key: 'actions', sortable: false },
]

const tripHeaders = [
  { title: 'Vehicle', key: 'ambulance.vehicle_code' },
  { title: 'Origin', key: 'origin' },
  { title: 'Destination', key: 'destination' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions', sortable: false },
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
  await $api('/ambulances', { method: 'POST', body: form.value })
  isVehicleDialogVisible.value = false
  await load()
}

const openDispatch = async item => {
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
  await $api(`/ambulances/${dispatching.value.id}/dispatch`, {
    method: 'POST',
    body: dispatchForm.value,
  })
  dispatching.value = null
  await load()
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
    <VCard class="mb-6">
      <VCardItem>
        <VCardTitle>Ambulance fleet</VCardTitle>
        <template #append>
          <VBtn
            v-if="ability.can('create', 'Ambulance')"
            prepend-icon="tabler-plus"
            @click="openCreate"
          >
            Add vehicle
          </VBtn>
        </template>
      </VCardItem>
      <VDataTable
        :headers="headers"
        :items="ambulances"
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
          <IconBtn :to="{ name: 'ambulances-id', params: { id: item.id } }">
            <VIcon icon="tabler-eye" />
          </IconBtn>
          <VBtn
            v-if="ability.can('dispatch', 'Ambulance') && item.status === 'available'"
            size="small"
            class="ms-2"
            @click="openDispatch(item)"
          >
            Dispatch
          </VBtn>
        </template>
      </VDataTable>
    </VCard>

    <VCard>
      <VCardItem>
        <VCardTitle>Trips</VCardTitle>
      </VCardItem>
      <VDataTable
        :headers="tripHeaders"
        :items="trips"
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
          <VBtn
            v-if="item.status === 'dispatched' && ability.can('dispatch', 'Ambulance')"
            size="small"
            class="me-2"
            @click="updateTrip(item, 'en_route')"
          >
            En route
          </VBtn>
          <VBtn
            v-if="['dispatched', 'en_route'].includes(item.status) && ability.can('dispatch', 'Ambulance')"
            size="small"
            class="me-2"
            @click="updateTrip(item, 'arrived')"
          >
            Arrived
          </VBtn>
          <VBtn
            v-if="['dispatched', 'en_route', 'arrived'].includes(item.status) && ability.can('dispatch', 'Ambulance')"
            size="small"
            color="success"
            @click="updateTrip(item, 'completed')"
          >
            Complete
          </VBtn>
        </template>
      </VDataTable>
    </VCard>
  </div>

  <VDialog
    v-model="isVehicleDialogVisible"
    max-width="560"
  >
    <VCard title="Add ambulance">
      <VCardText>
        <AppTextField
          v-model="form.vehicle_code"
          label="Vehicle code"
          class="mb-4"
        />
        <AppTextField
          v-model="form.vehicle_type"
          label="Vehicle type"
          class="mb-4"
        />
        <AppSelect
          v-model="form.status"
          :items="ambulanceStatuses"
          label="Status"
          class="mb-4"
        />
        <AppTextField
          v-model.number="form.capacity"
          type="number"
          label="Capacity"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          @click="isVehicleDialogVisible = false"
        >
          Cancel
        </VBtn>
        <VBtn @click="saveVehicle">
          Save
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <VDialog
    :model-value="Boolean(dispatching)"
    max-width="560"
    @update:model-value="val => { if (!val) dispatching = null }"
  >
    <VCard
      v-if="dispatching"
      title="Dispatch ambulance"
    >
      <VCardText>
        <AppTextField
          v-model="dispatchForm.origin"
          label="Origin"
          class="mb-4"
        />
        <AppTextField
          v-model="dispatchForm.destination"
          label="Destination"
          class="mb-4"
        />
        <AppSelect
          v-model="dispatchForm.destination_hospital_id"
          :items="hospitals"
          item-title="name"
          item-value="id"
          label="Destination hospital"
          clearable
          class="mb-4"
        />
        <AppTextarea
          v-model="dispatchForm.notes"
          label="Notes"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          @click="dispatching = null"
        >
          Cancel
        </VBtn>
        <VBtn @click="dispatch">
          Dispatch
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
