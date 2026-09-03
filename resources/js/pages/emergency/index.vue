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
    patients.value = asList(await $api('/patients', { query: compactListQuery() }))
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

const { pending, run } = usePageQuery(load)

let timer
onMounted(() => {
  timer = setInterval(() => { run({ silent: true }) }, 15000)
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
      flush
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
        :loading="pending"
        :headers="[
          { title: 'Patient', key: 'patient.first_name', fill: true },
          { title: 'Status', key: 'status' },
          { title: 'Actions', key: 'actions' },
        ]"
        :items="encounters"
        empty="No active emergency visits"
      >
        <template #cell-patient.first_name="{ item }">
          <HCell
            :to="item.patient?.id ? { name: 'patients-id', params: { id: item.patient.id } } : null"
            :secondary="item.chief_complaint"
          >
            {{ item.patient?.full_name || `${item.patient?.first_name || ''} ${item.patient?.last_name || ''}`.trim() || '—' }}
          </HCell>
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <HActionMenu
            :actions="[
              { label: 'Open chart', icon: 'stethoscope', if: ability.can('update', 'Emergency'), onSelect: () => openChart(item.id) },
            ]"
          />
        </template>
      </HTable>
    </HCard>

    <HModal
      v-model="visitOpen"
      title="Register emergency visit"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
        class="h-form-grid"
        :disabled="saving"
      >
        <HSelect
          v-model="form.patient_id"
          :items="patients"
          item-title="full_name"
          item-value="id"
          label="Patient"
          required
        />
        <HSelect
          v-model="form.clinician_id"
          :items="staff"
          item-title="name"
          item-value="id"
          label="Clinician"
        />
        <HInput
          span
          v-model="form.chief_complaint"
          label="Chief complaint"
          placeholder="Why is the patient here?"
        />
      </fieldset>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="visitOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :loading="saving"
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
