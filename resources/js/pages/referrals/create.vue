<script setup>
definePage({
  meta: {
    action: 'create',
    subject: 'Referral',
  },
})

const router = useRouter()
const types = ref([])
const matches = ref([])
const searching = ref(false)
const form = ref({
  patient_name: '',
  patient_reference: '',
  reason: '',
  required_facility_type_id: null,
  required_capacity: 1,
  to_hospital_id: null,
  destination_facility_id: null,
})

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

const selectHospital = hospital => {
  form.value.to_hospital_id = hospital.id
  form.value.destination_facility_id = hospital.available_facilities[0]?.id ?? null
}

const submit = async () => {
  await $api('/referrals', {
    method: 'POST',
    body: form.value,
  })
  router.push({ name: 'referrals' })
}

await withPageLoad(async () => {
  types.value = asList(await $api('/facility-types'))
  form.value.required_facility_type_id = types.value.find(type => type.slug === 'ward')?.id ?? types.value[0]?.id ?? null
  await searchHospitals()
})
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Create referral</VCardTitle>
    </VCardItem>
    <VCardText>
      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            v-model="form.patient_name"
            label="Patient name"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            v-model="form.patient_reference"
            label="Patient reference"
          />
        </VCol>
        <VCol cols="12">
          <AppTextarea
            v-model="form.reason"
            label="Clinical reason"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="form.required_facility_type_id"
            :items="types"
            item-title="name"
            item-value="id"
            label="Required facility"
            @update:model-value="searchHospitals"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            v-model.number="form.required_capacity"
            type="number"
            label="Required capacity"
            @update:model-value="searchHospitals"
          />
        </VCol>
      </VRow>

      <h5 class="text-h5 mt-6 mb-4">
        Eligible destination hospitals
      </h5>
      <VProgressLinear
        v-if="searching"
        indeterminate
      />
      <VAlert
        v-else-if="!matches.length"
        type="warning"
        variant="tonal"
      >
        No hospitals currently have the required available capacity.
      </VAlert>
      <VRow>
        <VCol
          v-for="hospital in matches"
          :key="hospital.id"
          cols="12"
          md="6"
        >
          <VCard
            :color="form.to_hospital_id === hospital.id ? 'primary' : undefined"
            :variant="form.to_hospital_id === hospital.id ? 'tonal' : 'outlined'"
            class="cursor-pointer"
            @click="selectHospital(hospital)"
          >
            <VCardItem>
              <VCardTitle>{{ hospital.name }}</VCardTitle>
              <VCardSubtitle>{{ hospital.city }} · {{ hospital.code }}</VCardSubtitle>
            </VCardItem>
            <VCardText>
              <div
                v-for="facility in hospital.available_facilities"
                :key="facility.id"
              >
                {{ facility.name }} · remaining {{ facility.remaining_capacity }}
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <div class="d-flex justify-end gap-4 mt-6">
        <VBtn
          variant="tonal"
          :to="{ name: 'referrals' }"
        >
          Cancel
        </VBtn>
        <VBtn
          :disabled="!form.to_hospital_id || !form.patient_name || !form.reason"
          @click="submit"
        >
          Create referral
        </VBtn>
      </div>
    </VCardText>
  </VCard>
</template>
