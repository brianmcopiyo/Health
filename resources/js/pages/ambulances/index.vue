<script setup>
import { ambulanceStatuses, labelize, statusColor } from '@/utils/status'
import { vehicleTypes } from '@/utils/clinicalOptions'

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
const patients = ref([])
const referrals = ref([])
const encounters = ref([])
const isVehicleDialogVisible = ref(false)
const dispatching = ref(null)
const completing = ref(null)
const handoverNotes = ref('')
const handoverTime = ref('')
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
  pickup_location: '',
  destination_hospital_id: null,
  patient_id: null,
  encounter_id: null,
  referral_id: null,
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
  { title: 'Patient', key: 'patient.first_name' },
  { title: 'Origin', key: 'origin' },
  { title: 'Destination', key: 'destination' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions' },
]

const encounterOptions = computed(() => encounters.value.map(item => ({
  title: `${labelize(item.type)} · ${item.chief_complaint || labelize(item.status)}`,
  value: item.id,
})))

const referralOptions = computed(() => referrals.value.map(item => ({
  title: `${item.patient?.full_name || item.patient_name} · ${item.to_hospital?.name}`,
  value: item.id,
})))

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
  patients.value = asList(await $api('/patients').catch(() => []))
  referrals.value = asList(await $api('/referrals', { query: { direction: 'outgoing' } }).catch(() => []))
    .filter(row => ['accepted', 'in_transit', 'pending'].includes(row.status))
  encounters.value = []
  dispatching.value = item
  dispatchForm.value = {
    origin: '',
    destination: '',
    pickup_location: '',
    destination_hospital_id: null,
    patient_id: null,
    encounter_id: null,
    referral_id: null,
    notes: '',
  }
}

const onDispatchPatient = async id => {
  dispatchForm.value.encounter_id = null
  if (!id) {
    encounters.value = []
    return
  }
  encounters.value = asList(await $api('/encounters', { query: { patient_id: id } }).catch(() => []))
}

const onDispatchReferral = id => {
  const referral = referrals.value.find(item => item.id === id)
  if (!referral)
    return
  dispatchForm.value.patient_id = referral.patient_id
  dispatchForm.value.encounter_id = referral.encounter_id
  dispatchForm.value.destination_hospital_id = referral.to_hospital_id
  dispatchForm.value.destination = referral.to_hospital?.name || dispatchForm.value.destination
  if (referral.patient_id)
    onDispatchPatient(referral.patient_id)
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
  if (status === 'completed') {
    formError.value = ''
    completing.value = trip
    handoverNotes.value = ''
    handoverTime.value = new Date().toTimeString().slice(0, 5)
    return
  }
  await $api(`/ambulance-trips/${trip.id}/status`, {
    method: 'PATCH',
    body: { status },
  })
  await load()
}

const completeTrip = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/ambulance-trips/${completing.value.id}/status`, {
      method: 'PATCH',
        body: {
          status: 'completed',
          handover_notes: [handoverTime.value && `Handover ${handoverTime.value}`, handoverNotes.value].filter(Boolean).join(' — '),
        },
    })
    completing.value = null
    await load()
  })
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
      <fieldset
        class="h-stack"
        :disabled="saving"
      >
        <HInput
          v-model="form.vehicle_code"
          label="Vehicle code"
          required
        />
        <HCombobox
          v-model="form.vehicle_type"
          :items="vehicleTypes"
          label="Vehicle type"
        />
        <HSelect
          v-model="form.status"
          :items="ambulanceStatuses"
          label="Status"
        />
        <HNumber
          v-model="form.capacity"
          label="Capacity"
          :min="1"
        />
      </fieldset>
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
      <fieldset
        v-if="dispatching"
        class="h-stack"
        :disabled="saving"
      >
        <HSelect
          v-model="dispatchForm.referral_id"
          :items="referralOptions"
          label="Linked referral"
          @update:model-value="onDispatchReferral"
        />
        <HSelect
          v-model="dispatchForm.patient_id"
          :items="patients"
          item-title="full_name"
          item-value="id"
          label="Patient"
          @update:model-value="onDispatchPatient"
        />
        <HSelect
          v-if="encounterOptions.length"
          v-model="dispatchForm.encounter_id"
          :items="encounterOptions"
          label="Encounter"
        />
        <HInput
          v-model="dispatchForm.origin"
          label="Origin"
          required
        />
        <HInput
          v-model="dispatchForm.pickup_location"
          label="Pickup location"
        />
        <HInput
          v-model="dispatchForm.destination"
          label="Destination"
          required
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
      </fieldset>
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

    <HModal
      :model-value="Boolean(completing)"
      title="Handover"
      :error="formError"
      :persistent="saving"
      @update:model-value="val => { if (!val) completing = null }"
    >
      <fieldset
        class="h-stack"
        :disabled="saving"
      >
        <HTimePicker
          v-model="handoverTime"
          label="Handover time"
        />
        <HTextarea
          v-model="handoverNotes"
          label="Handover notes"
        />
      </fieldset>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="completing = null"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving"
          @click="completeTrip"
        >
          Complete trip
        </HButton>
      </template>
    </HModal>
  </div>
</template>
