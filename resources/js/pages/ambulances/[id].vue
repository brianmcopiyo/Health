<script setup>
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Ambulance',
  },
})

const route = useRoute()
const ambulance = ref(null)

await withPageLoad(async () => {
  ambulance.value = await $api(`/ambulances/${route.params.id}`)
})
</script>

<template>
  <div>
    <VBtn
      variant="text"
      class="mb-6"
      :to="{ name: 'ambulances' }"
      prepend-icon="tabler-arrow-left"
    >
      Back
    </VBtn>

    <VAlert
      v-if="!ambulance"
      type="warning"
      variant="tonal"
    >
      This ambulance could not be loaded.
    </VAlert>

    <template v-else>
    <VCard class="mb-6">
      <VCardItem>
        <VCardTitle>{{ ambulance.vehicle_code }}</VCardTitle>
        <VCardSubtitle>{{ ambulance.vehicle_type }}</VCardSubtitle>
      </VCardItem>
      <VCardText>
        <VChip
          :color="statusColor(ambulance.status)"
          class="text-capitalize mb-4"
        >
          {{ labelize(ambulance.status) }}
        </VChip>
        <div>Capacity: {{ ambulance.capacity }}</div>
        <div class="mt-4">
          <strong>Crew</strong>
          <div
            v-for="member in ambulance.staff"
            :key="member.id"
          >
            {{ member.user?.name }} · {{ member.assignment_role }}
          </div>
          <div v-if="!ambulance.staff?.length">
            No crew assigned.
          </div>
        </div>
      </VCardText>
    </VCard>

    <VCard>
      <VCardItem>
        <VCardTitle>Trip history</VCardTitle>
      </VCardItem>
      <VTable>
        <thead>
          <tr>
            <th>Origin</th>
            <th>Destination</th>
            <th>Status</th>
            <th>Dispatched</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="trip in ambulance.trips"
            :key="trip.id"
          >
            <td>{{ trip.origin }}</td>
            <td>{{ trip.destination }}</td>
            <td class="text-capitalize">
              {{ labelize(trip.status) }}
            </td>
            <td>{{ trip.dispatched_at }}</td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
    </template>
  </div>
</template>
