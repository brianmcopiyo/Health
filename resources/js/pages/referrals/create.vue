<script setup>
import { labelize } from '@/utils/status'

definePage({
  meta: {
    action: 'create',
    subject: 'Referral',
  },
})

const router = useRouter()
const types = ref([])
const services = ref([])
const patients = ref([])
const encounters = ref([])
const matches = ref([])
const searching = ref(false)
const saving = ref(false)
const formError = ref('')
const form = ref({
  patient_id: null,
  encounter_id: null,
  patient_name: '',
  patient_reference: '',
  reason: '',
  required_facility_type_id: null,
  required_service_id: null,
  required_capacity: 1,
  to_hospital_id: null,
  destination_facility_id: null,
})

const encounterOptions = computed(() => encounters.value.map(item => ({
  title: `${labelize(item.type)} · ${item.chief_complaint || labelize(item.status)}`,
  value: item.id,
})))

const selectedPatient = computed(() => patients.value.find(item => item.id === form.value.patient_id))

const searchHospitals = async () => {
  searching.value = true
  try {
    matches.value = asList(await $api('/referrals/eligible-hospitals', {
      query: {
        facility_type_id: form.value.required_facility_type_id,
        required_capacity: form.value.required_capacity,
      },
    }))
  }
  catch (error) {
    matches.value = []
    console.error(error)
  }
  finally {
    searching.value = false
  }
}

const onPatient = async id => {
  form.value.encounter_id = null
  const patient = patients.value.find(item => item.id === id)
  form.value.patient_name = patient?.full_name || ''
  form.value.patient_reference = patient?.mrn || ''
  if (!id) {
    encounters.value = []
    return
  }
  encounters.value = asList(await $api('/encounters', { query: { patient_id: id } }))
}

const onEncounter = id => {
  const encounter = encounters.value.find(item => item.id === id)
  if (encounter?.chief_complaint && !form.value.reason)
    form.value.reason = encounter.chief_complaint
}

const selectHospital = hospital => {
  form.value.to_hospital_id = hospital.id
  form.value.destination_facility_id = hospital.available_facilities[0]?.id ?? null
}

const submit = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/referrals', {
      method: 'POST',
      body: form.value,
    })
    router.push({ name: 'referrals' })
  })
}

await withPageLoad(async () => {
  types.value = asList(await $api('/facility-types'))
  services.value = asList(await $api('/clinical-services'))
  patients.value = asList(await $api('/patients'))
  form.value.required_facility_type_id = types.value.find(type => type.slug === 'ward')?.id ?? types.value[0]?.id ?? null
  await searchHospitals()
})
</script>

<template>
  <div>
    <HPage
      title="Create referral"
      subtitle="Start from a patient encounter, then match a hospital with the required capacity"
    >
      <HButton
        variant="ghost"
        :to="{ name: 'referrals' }"
      >
        <HIcon name="back" />
        Back
      </HButton>
    </HPage>

    <HCard>
      <div
        v-if="formError"
        class="h-alert"
        style="margin-bottom:14px"
      >
        {{ formError }}
      </div>
      <div class="h-grid cols-2">
        <HSelect
          v-model="form.patient_id"
          :items="patients"
          item-title="full_name"
          item-value="id"
          label="Patient"
          required
          @update:model-value="onPatient"
        />
        <HSelect
          v-model="form.encounter_id"
          :items="encounterOptions"
          label="Source encounter"
          @update:model-value="onEncounter"
        />
      </div>
      <p
        v-if="selectedPatient"
        style="color:var(--muted);font-size:13px"
      >
        {{ selectedPatient.mrn }} · {{ selectedPatient.phone || 'No phone' }}
      </p>
      <HTextarea
        v-model="form.reason"
        label="Clinical reason"
        required
        style="margin-top:12px"
      />
      <div
        class="h-grid cols-2"
        style="margin-top:12px"
      >
        <HSelect
          v-model="form.required_facility_type_id"
          :items="types"
          item-title="name"
          item-value="id"
          label="Required facility"
          @update:model-value="searchHospitals"
        />
        <HSelect
          v-model="form.required_service_id"
          :items="services"
          item-title="name"
          item-value="id"
          label="Required service"
        />
        <HNumber
          v-model="form.required_capacity"
          label="Required capacity"
          :min="1"
          @update:model-value="searchHospitals"
        />
      </div>

      <h3 style="margin:24px 0 12px;font-family:var(--display)">
        Eligible destination hospitals
      </h3>
      <div
        v-if="searching"
        class="h-spinner"
      />
      <div
        v-else-if="!matches.length"
        class="h-alert"
      >
        No hospitals currently have the required available capacity.
      </div>
      <div
        v-else
        class="h-grid cols-2"
      >
        <div
          v-for="hospital in matches"
          :key="hospital.id"
          class="h-pick"
          :class="{ 'is-on': form.to_hospital_id === hospital.id }"
          @click="selectHospital(hospital)"
        >
          <strong>{{ hospital.name }}</strong>
          <div style="color:var(--muted);margin:4px 0 10px">
            {{ hospital.city }} · {{ hospital.code }}
          </div>
          <div
            v-for="facility in hospital.available_facilities"
            :key="facility.id"
          >
            {{ facility.name }} · remaining {{ facility.remaining_capacity }}
          </div>
        </div>
      </div>

      <div
        class="h-actions"
        style="margin-top:20px;justify-content:flex-end"
      >
        <HButton
          variant="ghost"
          :to="{ name: 'referrals' }"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving || !form.to_hospital_id || !form.patient_id || !form.reason"
          @click="submit"
        >
          Create referral
        </HButton>
      </div>
    </HCard>
  </div>
</template>
