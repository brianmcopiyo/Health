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
    <HPage
      :title="facility?.name || 'Facility'"
      :subtitle="facility ? `${facility.type?.name || ''} · ${facility.code}` : ''"
    >
      <HButton
        variant="ghost"
        :to="{ name: 'facilities' }"
      >
        <HIcon name="back" />
        Back
      </HButton>
    </HPage>

    <div
      v-if="!facility"
      class="h-alert"
    >
      This facility could not be loaded.
    </div>

    <div
      v-else
      class="h-grid cols-2"
    >
      <HCard>
        <HBadge :tone="statusColor(facility.status)">
          {{ labelize(facility.status) }}
        </HBadge>
        <div class="h-metric">
          <span>Capacity</span>
          <strong>{{ facility.capacity }}</strong>
        </div>
        <div class="h-metric">
          <span>In use</span>
          <strong>{{ facility.current_utilization }}</strong>
        </div>
        <div class="h-metric">
          <span>Remaining</span>
          <strong>{{ facility.remaining_capacity }}</strong>
        </div>
        <p>{{ facility.resource_notes || 'No resource notes yet.' }}</p>
      </HCard>

      <HCard
        v-if="ability.can('update', 'Facility')"
        title="Update status"
      >
        <div class="h-stack">
          <HSelect
            v-model="statusForm.status"
            :items="facilityStatuses"
            label="Status"
          />
          <HInput
            v-model="statusForm.current_utilization"
            type="number"
            label="Current utilization"
          />
          <HTextarea
            v-model="statusForm.resource_notes"
            label="Resource availability"
          />
          <HButton
            class="is-block"
            @click="saveStatus"
          >
            Save
          </HButton>
        </div>
      </HCard>
    </div>
  </div>
</template>
