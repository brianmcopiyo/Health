<script setup>
import { ambulanceStatuses, labelize, statusColor } from '@/utils/status'
import { vehicleTypes } from '@/utils/clinicalOptions'

definePage({
  meta: {
    action: 'read',
    subject: 'Ambulance',
  },
})

const route = useRoute()
const router = useRouter()
const ability = useAbility()
const ambulance = ref(null)
const staff = ref([])
const hospitals = ref([])
const patients = ref([])
const referrals = ref([])
const encounters = ref([])
const editOpen = ref(false)
const dispatchOpen = ref(false)
const completing = ref(null)
const removing = ref(false)
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

const encounterOptions = computed(() => encounters.value.map(item => ({
  title: `${labelize(item.type)} · ${item.chief_complaint || labelize(item.status)}`,
  value: item.id,
})))

const referralOptions = computed(() => referrals.value.map(item => ({
  title: `${item.patient?.full_name || item.patient_name} · ${item.to_hospital?.name}`,
  value: item.id,
})))

const load = async () => {
  ambulance.value = await $api(`/ambulances/${route.params.id}`)
}

const openEdit = async () => {
  formError.value = ''
  staff.value = asList(await $api('/users/directory').catch(() => []))
  form.value = {
    vehicle_code: ambulance.value.vehicle_code,
    vehicle_type: ambulance.value.vehicle_type,
    status: ambulance.value.status,
    capacity: ambulance.value.capacity,
    notes: ambulance.value.notes || '',
    staff: (ambulance.value.staff || []).map(row => ({
      user_id: row.user_id || row.user?.id,
      assignment_role: row.assignment_role,
    })),
  }
  editOpen.value = true
}

const addCrew = () => {
  form.value.staff.push({ user_id: null, assignment_role: 'driver' })
}

const saveVehicle = async () => {
  await wrapSave(saving, formError, async () => {
    ambulance.value = await $api(`/ambulances/${ambulance.value.id}`, {
      method: 'PUT',
      body: {
        ...form.value,
        staff: form.value.staff.filter(row => row.user_id && row.assignment_role),
      },
    })
    editOpen.value = false
    await load()
  })
}

const openDispatch = async () => {
  formError.value = ''
  hospitals.value = asList(await $api('/network/hospitals'))
  patients.value = asList(await $api('/patients', { query: compactListQuery() }).catch(() => []))
  referrals.value = asList(await $api('/referrals', { query: { direction: 'outgoing', per_page: 50 } }).catch(() => []))
    .filter(row => ['accepted', 'in_transit', 'pending'].includes(row.status))
  staff.value = asList(await $api('/users/directory').catch(() => []))
  encounters.value = []
  dispatchForm.value = {
    origin: '',
    destination: '',
    pickup_location: '',
    destination_hospital_id: null,
    patient_id: null,
    encounter_id: null,
    referral_id: null,
    driver_user_id: ambulance.value.staff?.[0]?.user_id || ambulance.value.staff?.[0]?.user?.id || null,
    notes: '',
  }
  dispatchOpen.value = true
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
    await $api(`/ambulances/${ambulance.value.id}/dispatch`, {
      method: 'POST',
      body: dispatchForm.value,
    })
    dispatchOpen.value = false
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

const removeVehicle = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/ambulances/${ambulance.value.id}`, { method: 'DELETE' })
    await router.push({ name: 'ambulances' })
  })
}

await withPageLoad(load)
</script>

<template>
  <div>
    <HPage
      :title="ambulance?.vehicle_code || 'Ambulance'"
      :subtitle="ambulance?.vehicle_type || ''"
    >
      <HButton
        variant="ghost"
        :to="{ name: 'ambulances' }"
      >
        <HIcon name="back" />
        Fleet
      </HButton>
      <HButton
        v-if="ambulance && ability.can('update', 'Ambulance')"
        @click="openEdit"
      >
        <HIcon name="edit" />
        Edit
      </HButton>
      <HButton
        v-if="ambulance && ability.can('dispatch', 'Ambulance') && ambulance.status === 'available'"
        @click="openDispatch"
      >
        Dispatch
      </HButton>
      <HActionMenu v-if="ambulance && ability.can('manage', 'Ambulance')">
        <template #default="{ close }">
          <button
            type="button"
            class="h-action-item is-danger"
            @click="formError = ''; removing = true; close()"
          >
            Remove
          </button>
        </template>
      </HActionMenu>
    </HPage>

    <div
      v-if="formError && !editOpen && !dispatchOpen && !completing && !removing"
      class="h-alert"
    >
      {{ formError }}
    </div>

    <div
      v-if="!ambulance"
      class="h-alert"
    >
      This ambulance could not be loaded.
    </div>

    <div
      v-else
      class="h-detail"
    >
      <HCard title="Vehicle">
        <div class="h-stack">
          <HBadge :tone="statusColor(ambulance.status)">
            {{ labelize(ambulance.status) }}
          </HBadge>
          <div class="h-metric">
            <span>Capacity</span>
            <strong>{{ ambulance.capacity }}</strong>
          </div>
          <p
            v-if="ambulance.notes"
            class="h-muted"
          >
            {{ ambulance.notes }}
          </p>
          <h3 class="h-section-title">
            Crew
          </h3>
          <div
            v-for="member in ambulance.staff"
            :key="member.id"
          >
            {{ member.user?.name }} · {{ member.assignment_role }}
          </div>
          <div
            v-if="!ambulance.staff?.length"
            class="h-muted"
          >
            No crew assigned.
          </div>
        </div>
      </HCard>

      <HCard
        title="Trip history"
        flush
      >
        <HTable
          :headers="[
            { title: 'Patient', key: 'patient.first_name' },
            { title: 'Origin', key: 'origin' },
            { title: 'Destination', key: 'destination' },
            { title: 'Status', key: 'status' },
            { title: '', key: 'actions' },
          ]"
          :items="asList(ambulance.trips)"
          empty="No trips for this vehicle"
        >
          <template #cell-patient.first_name="{ item }">
            <RouterLink
              v-if="item.patient?.id"
              class="h-inline-link"
              :to="{ name: 'patients-id', params: { id: item.patient.id } }"
            >
              {{ item.patient.first_name }} {{ item.patient.last_name }}
            </RouterLink>
            <span v-else>—</span>
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
              <HButton
                v-if="['dispatched', 'en_route', 'arrived'].includes(item.status) && ability.can('dispatch', 'Ambulance')"
                variant="ghost"
                size="sm"
                @click="updateTrip(item, 'cancelled')"
              >
                Cancel
              </HButton>
            </div>
          </template>
        </HTable>
      </HCard>
    </div>

    <HOffcanvas
      v-model="editOpen"
      title="Update ambulance"
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
        <HTextarea
          v-model="form.notes"
          label="Notes"
        />
        <h4>Crew</h4>
        <fieldset
          v-for="(member, index) in form.staff"
          :key="index"
          class="h-form-grid"
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
          @click="editOpen = false"
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
    </HOffcanvas>

    <HModal
      v-model="dispatchOpen"
      title="Dispatch ambulance"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
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
        <HSelect
          v-model="dispatchForm.driver_user_id"
          :items="staff"
          item-title="name"
          item-value="id"
          label="Driver"
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
          @click="dispatchOpen = false"
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

    <HModal
      v-model="removing"
      title="Remove ambulance"
      :error="formError"
      :persistent="saving"
    >
      <p>Remove {{ ambulance?.vehicle_code }} from the fleet?</p>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="removing = false"
        >
          Keep
        </HButton>
        <HButton
          variant="danger"
          :disabled="saving"
          @click="removeVehicle"
        >
          Remove
        </HButton>
      </template>
    </HModal>
  </div>
</template>
