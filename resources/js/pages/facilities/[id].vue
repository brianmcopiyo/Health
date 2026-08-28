<script setup>
import { facilityStatuses, labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Facility',
  },
})

const route = useRoute()
const ability = useAbility()
const facility = ref(null)
const statusForm = ref({
  status: 'available',
  current_utilization: 0,
  resource_notes: '',
})

await withPageLoad(async () => {
  facility.value = await $api(`/facilities/${route.params.id}`)
  statusForm.value = {
    status: facility.value.status,
    current_utilization: facility.value.current_utilization,
    resource_notes: facility.value.resource_notes,
  }
})

const saveStatus = async () => {
  facility.value = await $api(`/facilities/${facility.value.id}/status`, {
    method: 'PATCH',
    body: statusForm.value,
  })
}
</script>

<template>
  <div>
    <div class="d-flex align-center mb-6">
      <VBtn
        variant="text"
        :to="{ name: 'facilities' }"
        prepend-icon="tabler-arrow-left"
      >
        Back
      </VBtn>
    </div>

    <VAlert
      v-if="!facility"
      type="warning"
      variant="tonal"
    >
      This facility could not be loaded.
    </VAlert>

    <VRow v-else>
      <VCol
        cols="12"
        md="8"
      >
        <VCard>
          <VCardItem>
            <VCardTitle>{{ facility.name }}</VCardTitle>
            <VCardSubtitle>{{ facility.type?.name }} · {{ facility.code }}</VCardSubtitle>
          </VCardItem>
          <VCardText>
            <VChip
              :color="statusColor(facility.status)"
              class="text-capitalize mb-4"
            >
              {{ labelize(facility.status) }}
            </VChip>
            <div class="mb-2">
              Capacity: <strong>{{ facility.capacity }}</strong>
            </div>
            <div class="mb-2">
              In use: <strong>{{ facility.current_utilization }}</strong>
            </div>
            <div class="mb-2">
              Remaining: <strong>{{ facility.remaining_capacity }}</strong>
            </div>
            <div class="text-body-1">
              {{ facility.resource_notes || 'No resource notes yet.' }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="4"
      >
        <VCard v-if="ability.can('update', 'Facility')">
          <VCardItem>
            <VCardTitle>Update status</VCardTitle>
          </VCardItem>
          <VCardText>
            <AppSelect
              v-model="statusForm.status"
              :items="facilityStatuses"
              label="Status"
              class="mb-4"
            />
            <AppTextField
              v-model.number="statusForm.current_utilization"
              type="number"
              label="Current utilization"
              class="mb-4"
            />
            <AppTextarea
              v-model="statusForm.resource_notes"
              label="Resource availability"
              class="mb-4"
            />
            <VBtn
              block
              @click="saveStatus"
            >
              Save
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
