<script setup>
import { facilityRecordTo } from '@/utils/helpers'
import { labelize } from '@/utils/status'

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
const tab = ref('overview')
const tabs = [
  { title: 'Overview', value: 'overview' },
  { title: 'Related', value: 'related' },
]

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

const { pending } = usePageQuery(load)
</script>

<template>
  <HRecord
    :title="request?.title || 'Assistance'"
    :subtitle="request ? `${request.from_hospital?.name} → ${request.to_hospital?.name}` : ''"
    :status="request?.status"
    :back="{ name: 'assistance' }"
    back-label="Assistance"
    :tabs="tabs"
    :tab="tab"
    :loading="pending"
    :missing="!pending && !request"
    @update:tab="tab = $event"
  >
    <template v-if="request">
      <div
        v-if="tab === 'overview'"
        class="h-detail"
      >
        <HCard title="Request">
          <template #actions>
            <HButton
              size="sm"
              @click="openManage"
            >
              Manage
            </HButton>
          </template>
          <div class="h-metric">
            <span>Type</span>
            <strong>{{ labelize(request.type) }} · {{ request.quantity || 1 }}</strong>
          </div>
          <div class="h-metric">
            <span>Resource</span>
            <strong>
              <RouterLink
                v-if="request.facility?.id"
                class="h-inline-link"
                :to="facilityRecordTo(request.facility)"
              >
                {{ request.facility.name }}
              </RouterLink>
              <template v-else>
                {{ request.facility_type?.name || '—' }}
              </template>
            </strong>
          </div>
          <p>{{ request.description || 'No details provided.' }}</p>
          <p
            v-if="request.response_notes"
            class="h-muted"
          >
            Response: {{ request.response_notes }}
          </p>
        </HCard>

      </div>
      <div
        v-if="tab === 'related'"
        class="h-detail"
      >
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
            <strong>
              <RouterLink
                v-if="request.encounter?.id"
                class="h-inline-link"
                :to="{ name: 'encounters-id', params: { id: request.encounter.id } }"
              >
                {{ labelize(request.encounter.type) }} · {{ request.encounter.chief_complaint || labelize(request.encounter.status) }}
              </RouterLink>
              <template v-else>—</template>
            </strong>
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
        placeholder="How you are responding"
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
  </HRecord>
</template>
