<script setup>
import EncounterChart from '@/components/hms/EncounterChart.vue'
import FacilityBoard from '@/components/hms/FacilityBoard.vue'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Emergency',
  },
})

const ability = useAbility()
const board = ref(null)
const patients = ref([])
const staff = ref([])
const visitOpen = ref(false)
const chartOpen = ref(false)
const encounterId = ref(null)
const saving = ref(false)
const formError = ref('')
const form = ref({
  patient_id: null,
  chief_complaint: '',
  clinician_id: null,
})

const encounters = computed(() => asList(board.value?.encounters))

const load = async () => {
  board.value = await $api('/modules/emergency')
  if (ability.can('read', 'Patient'))
    patients.value = asList(await $api('/patients'))
  staff.value = asList(await $api('/users/directory'))
}

const openVisit = () => {
  formError.value = ''
  form.value = { patient_id: null, chief_complaint: '', clinician_id: null }
  visitOpen.value = true
}

const startVisit = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/encounters', {
      method: 'POST',
      body: {
        type: 'emergency',
        ...form.value,
      },
    })
    visitOpen.value = false
    await load()
  })
}

const openChart = id => {
  encounterId.value = id
  chartOpen.value = true
}

await withPageLoad(load)

let timer
onMounted(() => {
  timer = setInterval(() => { withPageLoad(load, { silent: true }) }, 15000)
})
onBeforeUnmount(() => {
  if (timer)
    clearInterval(timer)
})
</script>

<template>
  <div>
    <FacilityBoard
      module-key="emergency"
      title="Emergency"
      subject="Emergency"
    />

    <HCard
      title="Emergency queue"
      style="margin-top:18px"
    >
      <template
        v-if="ability.can('create', 'Emergency')"
        #actions
      >
        <HButton
          size="sm"
          @click="openVisit"
        >
          <HIcon name="plus" />
          Register visit
        </HButton>
      </template>
      <HTable
        :headers="[
          { title: 'Patient', key: 'patient.first_name' },
          { title: 'Complaint', key: 'chief_complaint' },
          { title: 'Status', key: 'status' },
          { title: 'Actions', key: 'actions' },
        ]"
        :items="encounters"
        empty="No active emergency visits"
      >
        <template #cell-patient.first_name="{ item }">
          {{ item.patient?.first_name }} {{ item.patient?.last_name }}
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <div class="h-actions">
            <HButton
              v-if="ability.can('update', 'Emergency')"
              size="sm"
              @click="openChart(item.id)"
            >
              Open chart
            </HButton>
          </div>
        </template>
      </HTable>
    </HCard>

    <HModal
      v-model="visitOpen"
      title="Register emergency visit"
      :error="formError"
      :persistent="saving"
    >
      <div class="h-stack">
        <HSelect
          v-model="form.patient_id"
          :items="patients"
          item-title="full_name"
          item-value="id"
          label="Patient"
        />
        <HInput
          v-model="form.chief_complaint"
          label="Chief complaint"
        />
        <HSelect
          v-model="form.clinician_id"
          :items="staff"
          item-title="name"
          item-value="id"
          label="Clinician"
        />
      </div>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="visitOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving || !form.patient_id"
          @click="startVisit"
        >
          Register visit
        </HButton>
      </template>
    </HModal>

    <EncounterChart
      v-model="chartOpen"
      :encounter-id="encounterId"
      @saved="load"
    />
  </div>
</template>
