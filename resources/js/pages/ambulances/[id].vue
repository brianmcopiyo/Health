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
    <HPage
      :title="ambulance?.vehicle_code || 'Ambulance'"
      :subtitle="ambulance?.vehicle_type || ''"
    >
      <HButton
        variant="ghost"
        :to="{ name: 'ambulances' }"
      >
        <HIcon name="back" />
        Back
      </HButton>
    </HPage>

    <div
      v-if="!ambulance"
      class="h-alert"
    >
      This ambulance could not be loaded.
    </div>

    <template v-else>
      <HCard>
        <HBadge :tone="statusColor(ambulance.status)">
          {{ labelize(ambulance.status) }}
        </HBadge>
        <p>Capacity: {{ ambulance.capacity }}</p>
        <h3 style="font-family:var(--display);margin:18px 0 8px">
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
          style="color:var(--muted)"
        >
          No crew assigned.
        </div>
      </HCard>

      <HCard
        title="Trip history"
        style="margin-top:18px"
      >
        <HTable
          :headers="[
            { title: 'Origin', key: 'origin' },
            { title: 'Destination', key: 'destination' },
            { title: 'Status', key: 'status' },
            { title: 'Dispatched', key: 'dispatched_at' },
          ]"
          :items="asList(ambulance.trips)"
          empty="No trips for this vehicle"
        >
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
        </HTable>
      </HCard>
    </template>
  </div>
</template>
