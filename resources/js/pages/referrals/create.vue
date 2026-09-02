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
const showSearch = useDelayedVisible(searching)
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

const { pending } = usePageQuery(async () => {
  types.value = asList(await $api('/facility-types'))
  services.value = asList(await $api('/clinical-services'))
  patients.value = asList(await $api('/patients', { query: compactListQuery() }))
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

    <div class="h-form-card is-wide">
      <HCard v-if="pending">
        <HForm
          wide
          :loading="true"
          :fields="8"
        />
      </HCard>
      <HForm
        v-else
        wide
        @submit="submit"
      >
        <HCard>
          <div
            v-if="formError"
            class="h-alert"
          >
            {{ formError }}
          </div>
          <HFormGrid>
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
            <p
              v-if="selectedPatient"
              class="h-muted is-span"
            >
              {{ selectedPatient.mrn }} · {{ selectedPatient.phone || 'No phone' }}
            </p>
            <HTextarea
              span
              v-model="form.reason"
              label="Clinical reason"
              placeholder="Why the patient needs transfer"
              required
            />
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
              placeholder="e.g. 1"
              :min="1"
              @update:model-value="searchHospitals"
            />
          </HFormGrid>
        </HCard>
        <HSection title="Eligible destination hospitals">
          <HLoading v-if="showSearch" />
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
              <p class="h-muted">
                {{ hospital.city }}
              </p>
              <div
                v-for="facility in hospital.available_facilities"
                :key="facility.id"
              >
                {{ facility.name }} · remaining {{ facility.remaining_capacity }}
              </div>
            </div>
          </div>
        </HSection>
        <template #actions>
          <HButton
            variant="ghost"
            :to="{ name: 'referrals' }"
          >
            Cancel
          </HButton>
          <HButton
            type="submit"
            :loading="saving"
            :disabled="saving || !form.to_hospital_id || !form.patient_id || !form.reason"
          >
            Create referral
          </HButton>
        </template>
      </HForm>
    </div>
  </div>
</template>
