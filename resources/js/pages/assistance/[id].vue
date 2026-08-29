<script setup>
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'AssistanceRequest',
  },
})

const route = useRoute()
const ability = useAbility()
const userData = useCookie('userData')
const request = ref(null)
const saving = ref(false)
const formError = ref('')
const responseNotes = ref('')
const manageOpen = ref(false)

const isDestination = computed(() => userData.value?.hospitalId === request.value?.to_hospital_id)
const isOrigin = computed(() => userData.value?.hospitalId === request.value?.from_hospital_id)

const load = async () => {
  request.value = await $api(`/assistance-requests/${route.params.id}`)
}

const openManage = () => {
  formError.value = ''
  responseNotes.value = request.value?.response_notes || ''
  manageOpen.value = true
}

const updateStatus = async status => {
  await wrapSave(saving, formError, async () => {
    await $api(`/assistance-requests/${request.value.id}/status`, {
      method: 'PATCH',
      body: {
        status,
        response_notes: responseNotes.value,
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
      :title="request?.title || 'Assistance'"
      :subtitle="request ? `${request.from_hospital?.name} → ${request.to_hospital?.name}` : ''"
    >
      <HButton
        variant="ghost"
        :to="{ name: 'assistance' }"
      >
        <HIcon name="back" />
        Assistance
      </HButton>
      <HButton
        v-if="request"
        @click="openManage"
      >
        Manage
      </HButton>
    </HPage>

    <div
      v-if="!request"
      class="h-alert"
    >
      This request could not be loaded.
    </div>

    <template v-else>
      <div class="h-detail">
        <HCard title="Request">
          <div class="h-metric">
            <span>Status</span>
            <strong>
              <HBadge :tone="statusColor(request.status)">
                {{ labelize(request.status) }}
              </HBadge>
            </strong>
          </div>
          <div class="h-metric">
            <span>Type</span>
            <strong>{{ labelize(request.type) }} · {{ request.quantity || 1 }}</strong>
          </div>
          <div class="h-metric">
            <span>Resource</span>
            <strong>{{ request.facility_type?.name || request.facility?.name || '—' }}</strong>
          </div>
          <p>{{ request.description || 'No details provided.' }}</p>
          <p
            v-if="request.response_notes"
            class="h-muted"
          >
            Response: {{ request.response_notes }}
          </p>
        </HCard>

        <HCard title="Related">
          <div class="h-metric">
            <span>Patient</span>
            <strong>
              <RouterLink
                v-if="request.patient?.id"
                class="h-inline-link"
                :to="{ name: 'patients-id', params: { id: request.patient.id } }"
              >
                {{ request.patient.full_name }}
              </RouterLink>
              <template v-else>
                —
              </template>
            </strong>
          </div>
          <div class="h-metric">
            <span>Encounter</span>
            <strong>{{ request.encounter ? `${labelize(request.encounter.type)} · ${request.encounter.chief_complaint || labelize(request.encounter.status)}` : '—' }}</strong>
          </div>
        </HCard>
      </div>
    </template>

    <HModal
      v-model="manageOpen"
      title="Respond"
      :error="formError"
      :persistent="saving"
    >
      <HTextarea
        v-model="responseNotes"
        label="Response notes"
      />
      <template #actions>
        <HButton
          v-if="isOrigin && request?.status === 'pending' && ability.can('update', 'AssistanceRequest')"
          variant="ghost"
          :disabled="saving"
          @click="updateStatus('cancelled')"
        >
          Cancel
        </HButton>
        <HButton
          v-if="isDestination && request?.status === 'pending' && ability.can('respond', 'AssistanceRequest')"
          variant="danger"
          :disabled="saving"
          @click="updateStatus('declined')"
        >
          Decline
        </HButton>
        <HButton
          v-if="isDestination && request?.status === 'pending' && ability.can('respond', 'AssistanceRequest')"
          variant="ok"
          :disabled="saving"
          @click="updateStatus('accepted')"
        >
          Accept
        </HButton>
        <HButton
          v-if="isDestination && request?.status === 'accepted' && ability.can('respond', 'AssistanceRequest')"
          :disabled="saving"
          @click="updateStatus('fulfilled')"
        >
          Mark fulfilled
        </HButton>
      </template>
    </HModal>
  </div>
</template>
