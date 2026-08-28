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
const statusOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const statusForm = ref({
  status: 'available',
  current_utilization: 0,
  resource_notes: '',
})

const fillStatus = record => {
  statusForm.value = {
    status: record.status,
    current_utilization: record.current_utilization,
    resource_notes: record.resource_notes,
  }
}

await withPageLoad(async () => {
  facility.value = await $api(`/facilities/${route.params.id}`)
  fillStatus(facility.value)
})

const openStatus = () => {
  formError.value = ''
  fillStatus(facility.value)
  statusOpen.value = true
}

const saveStatus = async () => {
  await wrapSave(saving, formError, async () => {
    facility.value = await $api(`/facilities/${facility.value.id}/status`, {
      method: 'PATCH',
      body: statusForm.value,
    })
    statusOpen.value = false
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
      <HButton
        v-if="facility && ability.can('update', 'Facility')"
        @click="openStatus"
      >
        Update status
      </HButton>
    </HPage>

    <div
      v-if="!facility"
      class="h-alert"
    >
      This facility could not be loaded.
    </div>

    <HCard v-else>
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

    <HModal
      v-model="statusOpen"
      title="Update status"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
        class="h-stack"
        :disabled="saving"
      >
        <HSelect
          v-model="statusForm.status"
          :items="facilityStatuses"
          label="Status"
        />
        <HNumber
          v-model="statusForm.current_utilization"
          label="Current utilization"
          :min="0"
        />
        <HTextarea
          v-model="statusForm.resource_notes"
          label="Resource availability"
        />
      </fieldset>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="statusOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving"
          @click="saveStatus"
        >
          Save
        </HButton>
      </template>
    </HModal>
  </div>
</template>
