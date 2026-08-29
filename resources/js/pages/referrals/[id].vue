<script setup>
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Referral',
  },
})

const route = useRoute()
const ability = useAbility()
const userData = useCookie('userData')
const referral = ref(null)
const saving = ref(false)
const formError = ref('')
const responseNotes = ref('')
const destinationFacilityId = ref(null)
const facilities = ref([])
const manageOpen = ref(false)

const isDestination = computed(() => userData.value?.hospitalId === referral.value?.to_hospital_id)
const isOrigin = computed(() => userData.value?.hospitalId === referral.value?.from_hospital_id)

const load = async () => {
  referral.value = await $api(`/referrals/${route.params.id}`)
}

const openManage = async () => {
  formError.value = ''
  responseNotes.value = referral.value?.response_notes || ''
  destinationFacilityId.value = referral.value?.destination_facility_id || null
  facilities.value = []
  if (isDestination.value && ['pending', 'more_info'].includes(referral.value.status)) {
    const typeId = referral.value.required_facility_type_id || referral.value.required_facility_type?.id
    const payload = await $api('/facilities', { query: { facility_type_id: typeId, per_page: 50 } }).catch(() => [])
    facilities.value = asList(payload)
  }
  manageOpen.value = true
}

const updateStatus = async nextStatus => {
  await wrapSave(saving, formError, async () => {
    await $api(`/referrals/${referral.value.id}/status`, {
      method: 'PATCH',
      body: {
        status: nextStatus,
        response_notes: responseNotes.value,
        destination_facility_id: destinationFacilityId.value,
      },
    })
    manageOpen.value = false
    await load()
  })
}

await withPageLoad(load)
</script>

<template>
  <div>
    <HPage
      :title="referral?.patient?.full_name || referral?.patient_name || 'Referral'"
      :subtitle="referral ? `${referral.from_hospital?.name} → ${referral.to_hospital?.name}` : ''"
    >
      <HButton
        variant="ghost"
        :to="{ name: 'referrals' }"
      >
        <HIcon name="back" />
        Referrals
      </HButton>
      <HButton
        v-if="referral"
        @click="openManage"
      >
        Manage
      </HButton>
    </HPage>

    <div
      v-if="!referral"
      class="h-alert"
    >
      This referral could not be loaded.
    </div>

    <template v-else>
      <div class="h-detail">
        <HCard title="Transfer">
          <div class="h-metric">
            <span>Status</span>
            <strong>
              <HBadge :tone="statusColor(referral.status)">
                {{ labelize(referral.status) }}
              </HBadge>
            </strong>
          </div>
          <div class="h-metric">
            <span>Need</span>
            <strong>{{ referral.required_facility_type?.name || '—' }} · {{ referral.required_capacity }}</strong>
          </div>
          <div class="h-metric">
            <span>Destination unit</span>
            <strong>{{ referral.destination_facility?.name || 'Not assigned' }}</strong>
          </div>
          <p>{{ referral.reason }}</p>
          <p
            v-if="referral.response_notes"
            class="h-muted"
          >
            Response: {{ referral.response_notes }}
          </p>
        </HCard>

        <HCard title="Related">
          <div class="h-metric">
            <span>Patient</span>
            <strong>
              <RouterLink
                v-if="referral.patient?.id"
                class="h-inline-link"
                :to="{ name: 'patients-id', params: { id: referral.patient.id } }"
              >
                {{ referral.patient.full_name || referral.patient_name }}
              </RouterLink>
              <template v-else>
                {{ referral.patient_name || '—' }}
              </template>
            </strong>
          </div>
          <div class="h-metric">
            <span>Encounter</span>
            <strong>{{ referral.encounter ? `${labelize(referral.encounter.type)} · ${referral.encounter.chief_complaint || labelize(referral.encounter.status)}` : '—' }}</strong>
          </div>
          <div class="h-metric">
            <span>Ambulance</span>
            <strong>{{ referral.ambulance_trip?.ambulance?.vehicle_code || referral.ambulance_trip?.id || 'Not dispatched' }}</strong>
          </div>
        </HCard>
      </div>
    </template>

    <HModal
      v-model="manageOpen"
      title="Manage referral"
      :error="formError"
      :persistent="saving"
    >
      <div
        v-if="referral"
        class="h-stack"
      >
        <p>{{ referral.reason }}</p>
        <HSelect
          v-if="isDestination && ['pending', 'more_info'].includes(referral.status) && facilities.length"
          v-model="destinationFacilityId"
          :items="facilities"
          item-title="name"
          item-value="id"
          label="Receiving unit"
        />
        <HTextarea
          v-model="responseNotes"
          label="Notes"
        />
      </div>
      <template #actions>
        <HButton
          v-if="isOrigin && ['pending', 'accepted'].includes(referral?.status) && ability.can('update', 'Referral')"
          variant="ghost"
          :disabled="saving"
          @click="updateStatus('cancelled')"
        >
          Cancel
        </HButton>
        <HButton
          v-if="isDestination && referral?.status === 'pending' && ability.can('respond', 'Referral')"
          variant="ghost"
          :disabled="saving"
          @click="updateStatus('more_info')"
        >
          Request more information
        </HButton>
        <HButton
          v-if="isDestination && ['pending', 'more_info'].includes(referral?.status) && ability.can('respond', 'Referral')"
          variant="danger"
          :disabled="saving"
          @click="updateStatus('declined')"
        >
          Decline
        </HButton>
        <HButton
          v-if="isDestination && ['pending', 'more_info'].includes(referral?.status) && ability.can('respond', 'Referral')"
          variant="ok"
          :disabled="saving"
          @click="updateStatus('accepted')"
        >
          Accept
        </HButton>
        <HButton
          v-if="referral?.status === 'accepted' && (ability.can('update', 'Referral') || ability.can('respond', 'Referral'))"
          :disabled="saving"
          @click="updateStatus('in_transit')"
        >
          Mark in transit
        </HButton>
        <HButton
          v-if="['accepted', 'in_transit'].includes(referral?.status) && (ability.can('update', 'Referral') || ability.can('respond', 'Referral'))"
          variant="ok"
          :disabled="saving"
          @click="updateStatus('completed')"
        >
          Complete
        </HButton>
      </template>
    </HModal>
  </div>
</template>
