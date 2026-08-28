<script setup>
import { diagnosisKindOptions, doseFrequencies } from '@/utils/clinicalOptions'
import { labelize, statusColor } from '@/utils/status'

const props = defineProps({
  modelValue: Boolean,
  encounterId: [Number, String],
})
const emit = defineEmits(['update:modelValue', 'saved'])

const ability = useAbility()
const chart = ref(null)
const saving = ref(false)
const formError = ref('')
const services = ref([])
const medications = ref([])
const vitalForm = ref({})
const noteForm = ref({ type: 'progress', body: '' })
const diagnosisForm = ref({ name: '', kind: 'primary' })
const orderForm = ref({ module_key: 'laboratory', service_id: null, item_name: '' })
const rxForm = ref({ medication_id: null, dose: '', frequency: 'twice daily', duration: '', quantity: 1 })

const isOpen = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const canTreat = computed(() => ability.can('update', 'Opd') || ability.can('update', 'Emergency') || ability.can('update', 'Ward'))

const loadChart = async () => {
  if (!props.encounterId)
    return
  chart.value = await $api(`/encounters/${props.encounterId}`)
  services.value = asList(await $api('/clinical-services'))
  medications.value = asList(await $api('/medications'))
}

watch(() => [props.modelValue, props.encounterId], async ([open]) => {
  if (open && props.encounterId) {
    formError.value = ''
    await loadChart()
  }
})

const post = async (path, body) => {
  await wrapSave(saving, formError, async () => {
    await $api(path, { method: 'POST', body })
    await loadChart()
    emit('saved')
  })
}

const saveVitals = () => post(`/encounters/${props.encounterId}/vitals`, vitalForm.value)
const saveNote = () => post(`/encounters/${props.encounterId}/notes`, noteForm.value)
const saveDiagnosis = () => post(`/encounters/${props.encounterId}/diagnoses`, diagnosisForm.value)

const saveOrder = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/service-orders', {
      method: 'POST',
      body: {
        ...orderForm.value,
        encounter_id: props.encounterId,
        patient_id: chart.value?.patient?.id,
      },
    })
    orderForm.value = { module_key: 'laboratory', service_id: null, item_name: '' }
    await loadChart()
    emit('saved')
  })
}

const saveRx = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/prescriptions', {
      method: 'POST',
      body: {
        encounter_id: props.encounterId,
        items: [rxForm.value],
      },
    })
    rxForm.value = { medication_id: null, dose: '', frequency: 'twice daily', duration: '', quantity: 1 }
    await loadChart()
    emit('saved')
  })
}

const admit = () => post(`/encounters/${props.encounterId}/admit`, {})
const startConsult = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/encounters/${props.encounterId}`, { method: 'PATCH', body: { status: 'in_progress' } })
    await loadChart()
    emit('saved')
  })
}
const complete = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/encounters/${props.encounterId}`, { method: 'PATCH', body: { status: 'completed' } })
    await loadChart()
    emit('saved')
  })
}
</script>

<template>
  <HOffcanvas
    v-model="isOpen"
    :title="chart?.patient ? `${chart.patient.first_name} ${chart.patient.last_name}` : 'Encounter'"
    size="lg"
    :error="formError"
    :persistent="saving"
  >
    <template #subtitle>
      {{ chart ? `${labelize(chart.type)} · ${labelize(chart.status)}` : '' }}
    </template>
    <div
      v-if="chart"
      class="h-stack"
    >
      <p style="margin:0;color:var(--muted)">
        {{ chart.chief_complaint }}
        <span v-if="chart.clinician"> · {{ chart.clinician.name }}</span>
        <span v-if="chart.department"> · {{ chart.department.name }}</span>
      </p>
      <div
        v-if="chart.patient?.allergies?.length"
        class="h-alert warning"
      >
        Allergies: {{ chart.patient.allergies.map(item => item.allergen).join(', ') }}
      </div>

      <div class="h-actions">
        <HButton
          v-if="canTreat && chart.status === 'waiting'"
          size="sm"
          @click="startConsult"
        >
          Start
        </HButton>
        <HButton
          v-if="canTreat && chart.status !== 'completed' && chart.type !== 'admission'"
          size="sm"
          variant="ghost"
          @click="admit"
        >
          Admit
        </HButton>
        <HButton
          v-if="canTreat && chart.status !== 'completed'"
          size="sm"
          variant="ghost"
          @click="complete"
        >
          Complete
        </HButton>
        <HButton
          v-if="chart.patient"
          size="sm"
          variant="ghost"
          :to="{ name: 'patients-id', params: { id: chart.patient.id } }"
        >
          Open record
        </HButton>
      </div>

      <h4>Vitals</h4>
      <p
        v-for="item in chart.vitals"
        :key="item.id"
        style="margin:0;color:var(--muted)"
      >
        {{ item.temperature || '—' }}° · {{ item.pulse || '—' }} bpm · {{ item.systolic }}/{{ item.diastolic }} · SpO2 {{ item.spo2 || '—' }}%
      </p>
      <div
        v-if="canTreat"
        class="h-grid cols-3"
      >
        <HNumber
          v-model="vitalForm.temperature"
          label="Temp"
          :step="0.1"
          :min="30"
          :max="45"
        />
        <HNumber
          v-model="vitalForm.pulse"
          label="Pulse"
          :min="0"
        />
        <HNumber
          v-model="vitalForm.spo2"
          label="SpO2"
          :min="0"
          :max="100"
        />
      </div>
      <HButton
        v-if="canTreat"
        size="sm"
        :disabled="saving"
        @click="saveVitals"
      >
        Record vitals
      </HButton>

      <h4>Diagnoses</h4>
      <HBadge
        v-for="item in chart.diagnoses"
        :key="item.id"
      >
        {{ item.name }}
      </HBadge>
      <div
        v-if="canTreat"
        class="h-grid cols-2"
      >
        <HInput
          v-model="diagnosisForm.name"
          label="Diagnosis"
        />
        <HRadioGroup
          v-model="diagnosisForm.kind"
          :items="diagnosisKindOptions"
          label="Type"
        />
      </div>
      <HButton
        v-if="canTreat"
        size="sm"
        :disabled="saving || !diagnosisForm.name"
        @click="saveDiagnosis"
      >
        Add diagnosis
      </HButton>

      <h4>Notes</h4>
      <p
        v-for="item in chart.clinical_notes"
        :key="item.id"
        style="margin:0"
      >
        <strong>{{ labelize(item.type) }}</strong>
        · {{ item.body }}
      </p>
      <HTextarea
        v-if="canTreat"
        v-model="noteForm.body"
        label="Clinical note"
      />
      <HButton
        v-if="canTreat"
        size="sm"
        :disabled="saving || !noteForm.body"
        @click="saveNote"
      >
        Save note
      </HButton>

      <h4>Orders</h4>
      <p
        v-for="item in chart.orders"
        :key="item.id"
        style="margin:0"
      >
        {{ item.item_name }}
        <HBadge :tone="statusColor(item.status)">
          {{ labelize(item.status) }}
        </HBadge>
        <span v-if="item.result"> · {{ item.result }}</span>
      </p>
      <div
        v-if="canTreat"
        class="h-grid cols-2"
      >
        <HSelect
          v-model="orderForm.module_key"
          :items="[
            { title: 'Laboratory', value: 'laboratory' },
            { title: 'Imaging', value: 'imaging' },
            { title: 'Theatre', value: 'theatre' },
          ]"
          item-title="title"
          item-value="value"
          label="Service"
        />
        <HSelect
          v-model="orderForm.service_id"
          :items="services.filter(item => !orderForm.module_key || item.category === orderForm.module_key || item.category === 'procedure')"
          item-title="name"
          item-value="id"
          label="Catalogue"
        />
      </div>
      <HButton
        v-if="canTreat"
        size="sm"
        :disabled="saving || !orderForm.service_id"
        @click="saveOrder"
      >
        Place order
      </HButton>

      <h4>Prescriptions</h4>
      <p
        v-for="item in chart.prescriptions"
        :key="item.id"
        style="margin:0"
      >
        {{ (item.items || []).map(row => row.medication?.name).join(', ') }}
        <HBadge :tone="statusColor(item.status)">
          {{ labelize(item.status) }}
        </HBadge>
      </p>
      <div
        v-if="canTreat"
        class="h-grid cols-2"
      >
        <HSelect
          v-model="rxForm.medication_id"
          :items="medications"
          item-title="name"
          item-value="id"
          label="Medication"
        />
        <HInput
          v-model="rxForm.dose"
          label="Dose"
          placeholder="e.g. 75 mg"
        />
        <HCombobox
          v-model="rxForm.frequency"
          :items="doseFrequencies"
          label="Frequency"
        />
        <HNumber
          v-model="rxForm.quantity"
          label="Qty"
          :min="1"
        />
      </div>
      <HButton
        v-if="canTreat"
        size="sm"
        :disabled="saving || !rxForm.medication_id || !rxForm.dose"
        @click="saveRx"
      >
        Prescribe
      </HButton>
    </div>
  </HOffcanvas>
</template>
