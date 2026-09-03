<script setup>
import { ambulanceStatuses, labelize, statusColor, tripStatuses } from '@/utils/status'
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
const meta = ref(asPageMeta())
const list = useListQuery(['status', 'vehicle_type', 'trip_status'])
const { page, q, filterValues } = list
const tripFilters = computed({
  get: () => ({ status: list.values.trip_status }),
  set: next => { list.values.trip_status = next.status },
})
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
const staff = ref([])
const editing = ref(null)
const removing = ref(null)
const form = ref({
  vehicle_code: '',
  vehicle_type: 'van',
  status: 'available',
  capacity: 2,
  notes: '',
  staff: [],
})
const dispatchForm = ref({
  origin: '',
  destination: '',
  pickup_location: '',
  destination_hospital_id: null,
  patient_id: null,
  encounter_id: null,
  referral_id: null,
  driver_user_id: null,
  notes: '',
})

const headers = [
  { title: 'Vehicle', key: 'vehicle_code', fill: true },
  { title: 'Status', key: 'status' },
  { title: 'Capacity', key: 'capacity' },
  { title: 'Actions', key: 'actions' },
]

const tripHeaders = [
  { title: 'Vehicle', key: 'ambulance.vehicle_code', fill: true },
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
  const query = list.apiQuery()
  const tripStatus = query.trip_status
  delete query.trip_status
  const [fleet, history] = await Promise.all([
    $api('/ambulances', { query }),
    $api('/ambulance-trips', { query: { status: tripStatus || undefined } }),
  ])
  ambulances.value = asList(fleet)
  meta.value = asPageMeta(fleet)
  trips.value = asList(history)
}

const emptyVehicle = () => ({
  vehicle_code: '',
  vehicle_type: 'van',
  status: 'available',
  capacity: 2,
  notes: '',
  staff: [],
})

const openCreate = async () => {
  formError.value = ''
  editing.value = null
  staff.value = asList(await $api('/users/directory').catch(() => []))
  form.value = emptyVehicle()
  isVehicleDialogVisible.value = true
}

const openEdit = async item => {
  formError.value = ''
  editing.value = item
  staff.value = asList(await $api('/users/directory').catch(() => []))
  form.value = {
    vehicle_code: item.vehicle_code,
    vehicle_type: item.vehicle_type,
    status: item.status,
    capacity: item.capacity,
    notes: item.notes || '',
    staff: (item.staff || []).map(row => ({
      user_id: row.user_id || row.user?.id,
      assignment_role: row.assignment_role,
    })),
  }
  isVehicleDialogVisible.value = true
}

const addCrew = () => {
  form.value.staff.push({ user_id: null, assignment_role: 'driver' })
}

const saveVehicle = async () => {
  await wrapSave(saving, formError, async () => {
    const body = {
      ...form.value,
      staff: form.value.staff.filter(row => row.user_id && row.assignment_role),
    }
    if (editing.value)
      await $api(`/ambulances/${editing.value.id}`, { method: 'PUT', body })
    else
      await $api('/ambulances', { method: 'POST', body })

    isVehicleDialogVisible.value = false
    await load()
  })
}

const removeVehicle = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/ambulances/${removing.value.id}`, { method: 'DELETE' })
    removing.value = null
    await load()
  })
}

const openDispatch = async item => {
  formError.value = ''
  hospitals.value = asList(await $api('/network/hospitals'))
  patients.value = asList(await $api('/patients', { query: compactListQuery() }).catch(() => []))
  referrals.value = asList(await $api('/referrals', { query: { direction: 'outgoing', per_page: 50 } }).catch(() => []))
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
    driver_user_id: item.staff?.[0]?.user_id || item.staff?.[0]?.user?.id || null,
    notes: '',
  }
  staff.value = asList(await $api('/users/directory').catch(() => []))
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
  await wrapSave(saving, formError, async () => {
    await $api(`/ambulance-trips/${trip.id}/status`, {
      method: 'PATCH',
      body: { status },
    })
    await load()
  })
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

list.sync(load)
const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Ambulance fleet"
      subtitle="Vehicles, dispatch, and trip status"
    >
      <HExportActions
        dataset="ambulances"
        :query="list.apiQuery()"
        :disabled="pending"
      />
      <HButton
        v-if="ability.can('create', 'Ambulance')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Add vehicle
      </HButton>
    </HPage>

    <HCard
      title="Vehicles"
      flush
    >
      <HListToolbar
        v-model:search="q"
        v-model:values="filterValues"
        search-placeholder="Search vehicles"
        search-button
        :result-count="list.resultCount(meta)"
        :filters="[
          { key: 'status', type: 'select', label: 'Status', placeholder: 'All statuses', optional: true, empty: null, items: ambulanceStatuses.map(value => ({ title: labelize(value), value })) },
          { key: 'vehicle_type', type: 'select', label: 'Type', placeholder: 'All types', optional: true, empty: null, more: true, items: vehicleTypes },
        ]"
        @search="list.onSearch(load)"
        @change="list.onChange(load)"
      />
      <HTable
        :loading="pending"
        :headers="headers"
        :items="ambulances"
        empty="No ambulances registered"
      >
        <template #cell-vehicle_code="{ item }">
          <HCell
            :to="{ name: 'ambulances-id', params: { id: item.id } }"
            :secondary="joinContext(labelize(item.vehicle_type), item.hospital?.name)"
          >
            {{ item.vehicle_code }}
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
              { label: 'View', icon: 'eye', to: { name: 'ambulances-id', params: { id: item.id } } },
              { label: 'Dispatch', icon: 'send', if: ability.can('dispatch', 'Ambulance') && item.status === 'available', onSelect: () => openDispatch(item) },
              { label: 'Edit', icon: 'edit', if: ability.can('update', 'Ambulance'), onSelect: () => openEdit(item) },
              { label: 'Remove', icon: 'trash', danger: true, if: ability.can('manage', 'Ambulance'), onSelect: () => { formError = ''; removing = item } },
            ]"
          />
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => list.onPage(value, load)"
      />
    </HCard>

    <HCard
      title="Trips"
      flush
    >
      <HListToolbar
        v-model:values="tripFilters"
        :result-count="list.resultCount({ total: trips.length })"
        :filters="[
          { key: 'status', type: 'select', label: 'Status', placeholder: 'All statuses', optional: true, empty: null, items: tripStatuses.map(value => ({ title: labelize(value), value })) },
        ]"
        @change="list.onChange(load)"
      />
      <HTable
        :loading="pending"
        :headers="tripHeaders"
        :items="trips"
        empty="No trips recorded"
      >
        <template #cell-ambulance.vehicle_code="{ item }">
          <HCell
            :secondary="joinContext(
              item.patient?.full_name || `${item.patient?.first_name || ''} ${item.patient?.last_name || ''}`.trim(),
              item.origin,
              item.destination,
            )"
          >
            {{ item.ambulance?.vehicle_code || '—' }}
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
              { label: 'En route', icon: 'send', if: item.status === 'dispatched' && ability.can('dispatch', 'Ambulance'), onSelect: () => updateTrip(item, 'en_route') },
              { label: 'Arrived', icon: 'hospital', if: ['dispatched', 'en_route'].includes(item.status) && ability.can('dispatch', 'Ambulance'), onSelect: () => updateTrip(item, 'arrived') },
              { label: 'Complete', icon: 'check', if: ['dispatched', 'en_route', 'arrived'].includes(item.status) && ability.can('dispatch', 'Ambulance'), onSelect: () => updateTrip(item, 'completed') },
              { label: 'Cancel', icon: 'ban', danger: true, if: ['dispatched', 'en_route', 'arrived'].includes(item.status) && ability.can('dispatch', 'Ambulance'), onSelect: () => updateTrip(item, 'cancelled') },
            ]"
          />
        </template>
      </HTable>
    </HCard>

    <HModal
      v-model="isVehicleDialogVisible"
      :title="editing ? 'Update ambulance' : 'Add ambulance'"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
        class="h-form-grid"
        :disabled="saving"
      >
        <HInput
          v-model="form.vehicle_code"
          label="Vehicle code"
          placeholder="e.g. AMB-04"
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
          placeholder="e.g. 2"
          :min="1"
        />
        <HTextarea
          span
          v-model="form.notes"
          label="Notes"
          placeholder="Equipment or crew notes"
        />
        <h4>Crew</h4>
        <fieldset
          v-for="(member, index) in form.staff"
          :key="index"
          class="h-form-grid is-span"
          :disabled="saving"
        >
          <HSelect
            v-model="member.user_id"
            :items="staff"
            item-title="name"
            item-value="id"
            label="Staff"
          />
          <HInput
            v-model="member.assignment_role"
            label="Role"
            placeholder="e.g. Driver"
          />
        </fieldset>
        <HButton
          variant="ghost"
          size="sm"
          @click="addCrew"
        >
          Add crew member
        </HButton>
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
          :loading="saving"
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
        class="h-form-grid"
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
          placeholder="e.g. Riverside A&E"
          required
        />
        <HInput
          v-model="dispatchForm.pickup_location"
          label="Pickup location"
          placeholder="e.g. Gate 2"
        />
        <HInput
          v-model="dispatchForm.destination"
          label="Destination"
          placeholder="e.g. Ridge Hospital"
          required
        />
        <HSelect
          v-model="dispatchForm.destination_hospital_id"
          :items="hospitals"
          item-title="name"
          item-value="id"
          label="Destination hospital"
        />
        <HSelect
          v-model="dispatchForm.driver_user_id"
          :items="staff"
          item-title="name"
          item-value="id"
          label="Driver"
        />
        <HTextarea
          span
          v-model="dispatchForm.notes"
          label="Notes"
          placeholder="Dispatch instructions"
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
          :loading="saving"
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
          placeholder="Condition on arrival"
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
          :loading="saving"
          :disabled="saving"
          @click="completeTrip"
        >
          Complete trip
        </HButton>
      </template>
    </HModal>

    <HModal
      :model-value="Boolean(removing)"
      title="Remove ambulance"
      :error="formError"
      :persistent="saving"
      @update:model-value="val => { if (!val) removing = null }"
    >
      <p>Remove {{ removing?.vehicle_code }} from the fleet?</p>
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
          @click="removeVehicle"
        >
          Remove
        </HButton>
      </template>
    </HModal>
  </div>
</template>
