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
const clinicians = ref([])
const beds = ref([])
const vitalForm = ref({})
const noteForm = ref({ type: 'progress', body: '' })
const diagnosisForm = ref({ name: '', kind: 'primary' })
const orderForm = ref({ module_key: 'laboratory', service_id: null, item_name: '' })
const rxForm = ref({ medication_id: null, dose: '', frequency: 'twice daily', duration: '', quantity: 1 })
const planForm = ref({ title: '', body: '' })
const admitOpen = ref(false)
const dischargeOpen = ref(false)
const editOpen = ref(false)
const invoiceOpen = ref(false)
const invoice = ref(null)
const admitForm = ref({ facility_id: null, notes: '' })
const dischargeNotes = ref('')
const editForm = ref({ chief_complaint: '', clinician_id: null })

const isOpen = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const canTreat = computed(() => ability.can('update', 'Opd') || ability.can('update', 'Emergency') || ability.can('update', 'Ward'))
const openStatuses = computed(() => chart.value && !['completed', 'cancelled'].includes(chart.value.status))
const canSeeInvoice = computed(() => ability.can('read', 'Invoice') || canTreat.value)
const orderSubject = {
  laboratory: 'Laboratory',
  imaging: 'Imaging',
  theatre: 'Theatre',
  pharmacy: 'Pharmacy',
}

const canCancelOrder = order => {
  const subject = orderSubject[order.module_key]
  return subject && ability.can('update', subject) && !['completed', 'cancelled'].includes(order.status)
}

const loadChart = async () => {
  if (!props.encounterId)
    return
  chart.value = await $api(`/encounters/${props.encounterId}`)
  services.value = asList(await $api('/clinical-services').catch(() => []))
  medications.value = asList(await $api('/medications').catch(() => []))
}

watch(() => [props.modelValue, props.encounterId], async ([open]) => {
  if (open && props.encounterId) {
    formError.value = ''
    await loadChart()
    return
  }
  admitOpen.value = false
  dischargeOpen.value = false
  editOpen.value = false
  invoiceOpen.value = false
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
const savePlan = async () => {
  await post(`/encounters/${props.encounterId}/care-plans`, planForm.value)
  planForm.value = { title: '', body: '' }
}

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

const cancelOrder = async order => {
  await wrapSave(saving, formError, async () => {
    await $api(`/service-orders/${order.id}`, { method: 'PATCH', body: { status: 'cancelled' } })
    await loadChart()
    emit('saved')
  })
}

const cancelRx = async item => {
  await wrapSave(saving, formError, async () => {
    await $api(`/prescriptions/${item.id}/status`, { method: 'PATCH', body: { status: 'cancelled' } })
    await loadChart()
    emit('saved')
  })
}

const startConsult = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/encounters/${props.encounterId}`, { method: 'PATCH', body: { status: 'in_progress' } })
    await loadChart()
    emit('saved')
  })
}

const openAdmit = async () => {
  formError.value = ''
  admitForm.value = { facility_id: null, notes: '' }
  try {
    const payload = await $api('/facilities', { query: { per_page: 80 } })
    beds.value = asList(payload).filter(item => (item.remaining_capacity ?? 0) > 0)
  }
  catch {
    beds.value = []
  }
  admitOpen.value = true
}

const admit = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/encounters/${props.encounterId}/admit`, {
      method: 'POST',
      body: {
        facility_id: admitForm.value.facility_id || undefined,
        notes: admitForm.value.notes || undefined,
      },
    })
    admitOpen.value = false
    await loadChart()
    emit('saved')
  })
}

const openDischarge = () => {
  formError.value = ''
  dischargeNotes.value = ''
  dischargeOpen.value = true
}

const discharge = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/encounters/${props.encounterId}/discharge`, {
      method: 'POST',
      body: { notes: dischargeNotes.value || undefined },
    })
    dischargeOpen.value = false
    await loadChart()
    emit('saved')
  })
}

const cancelEncounter = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/encounters/${props.encounterId}`, { method: 'PATCH', body: { status: 'cancelled' } })
    await loadChart()
    emit('saved')
  })
}

const openEdit = async () => {
  formError.value = ''
  editForm.value = {
    chief_complaint: chart.value?.chief_complaint || '',
    clinician_id: chart.value?.clinician?.id || chart.value?.clinician_id || null,
  }
  clinicians.value = asList(await $api('/users/directory').catch(() => []))
  editOpen.value = true
}

const saveEdit = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/encounters/${props.encounterId}`, {
      method: 'PATCH',
      body: {
        chief_complaint: editForm.value.chief_complaint,
        clinician_id: editForm.value.clinician_id,
      },
    })
    editOpen.value = false
    await loadChart()
    emit('saved')
  })
}

const openInvoice = async () => {
  formError.value = ''
  await wrapSave(saving, formError, async () => {
    invoice.value = await $api(`/encounters/${props.encounterId}/invoice`)
    invoiceOpen.value = true
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
      <p class="h-muted">
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
          v-if="canTreat && openStatuses && chart.type !== 'admission'"
          size="sm"
          variant="ghost"
          @click="openAdmit"
        >
          Admit
        </HButton>
        <HButton
          v-if="canTreat && openStatuses"
          size="sm"
          @click="openDischarge"
        >
          Discharge
        </HButton>
        <HButton
          v-if="chart.patient"
          size="sm"
          variant="ghost"
          :to="{ name: 'patients-id', params: { id: chart.patient.id } }"
        >
          Open record
        </HButton>
        <HActionMenu v-if="canTreat || canSeeInvoice">
          <template #default="{ close }">
            <button
              v-if="canTreat && openStatuses"
              type="button"
              class="h-action-item"
              @click="openEdit(); close()"
            >
              Edit visit
            </button>
            <button
              v-if="canSeeInvoice"
              type="button"
              class="h-action-item"
              @click="openInvoice(); close()"
            >
              Charge sheet
            </button>
            <button
              v-if="canTreat && openStatuses"
              type="button"
              class="h-action-item is-danger"
              @click="cancelEncounter(); close()"
            >
              Cancel encounter
            </button>
          </template>
        </HActionMenu>
      </div>

      <h4>Vitals</h4>
      <p
        v-for="item in chart.vitals"
        :key="item.id"
        class="h-muted"
      >
        {{ item.temperature || '—' }}° · {{ item.pulse || '—' }} bpm · {{ item.systolic }}/{{ item.diastolic }} · SpO2 {{ item.spo2 || '—' }}%
      </p>
      <div
        v-if="canTreat && openStatuses"
        class="h-form-grid is-3"
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
        v-if="canTreat && openStatuses"
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
        v-if="canTreat && openStatuses"
        class="h-form-grid"
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
        v-if="canTreat && openStatuses"
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
      >
        <strong>{{ labelize(item.type) }}</strong>
        · {{ item.body }}
      </p>
      <HTextarea
        v-if="canTreat && openStatuses"
        v-model="noteForm.body"
        label="Clinical note"
      />
      <HButton
        v-if="canTreat && openStatuses"
        size="sm"
        :disabled="saving || !noteForm.body"
        @click="saveNote"
      >
        Save note
      </HButton>

      <h4>Care plans</h4>
      <p
        v-for="item in chart.care_plans"
        :key="item.id"
        class="h-muted"
      >
        <strong>{{ item.title }}</strong>
        <span v-if="item.body"> · {{ item.body }}</span>
      </p>
      <p
        v-if="!chart.care_plans?.length"
        class="h-muted"
      >
        No care plans recorded.
      </p>
      <div
        v-if="canTreat && openStatuses"
        class="h-stack"
      >
        <HInput
          v-model="planForm.title"
          label="Plan title"
        />
        <HTextarea
          v-model="planForm.body"
          label="Plan details"
        />
        <HButton
          size="sm"
          :disabled="saving || !planForm.title"
          @click="savePlan"
        >
          Add care plan
        </HButton>
      </div>

      <h4>Orders</h4>
      <p
        v-for="item in chart.orders"
        :key="item.id"
      >
        {{ item.item_name }}
        <HBadge :tone="statusColor(item.status)">
          {{ labelize(item.status) }}
        </HBadge>
        <span v-if="item.result"> · {{ item.result }}</span>
        <HButton
          v-if="canCancelOrder(item)"
          size="sm"
          variant="ghost"
          :disabled="saving"
          @click="cancelOrder(item)"
        >
          Cancel
        </HButton>
      </p>
      <div
        v-if="canTreat && openStatuses"
        class="h-form-grid"
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
        v-if="canTreat && openStatuses"
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
      >
        {{ (item.items || []).map(row => row.medication?.name).join(', ') }}
        <HBadge :tone="statusColor(item.status)">
          {{ labelize(item.status) }}
        </HBadge>
        <HButton
          v-if="canTreat && !['dispensed', 'cancelled'].includes(item.status)"
          size="sm"
          variant="ghost"
          :disabled="saving"
          @click="cancelRx(item)"
        >
          Cancel
        </HButton>
      </p>
      <div
        v-if="canTreat && openStatuses"
        class="h-form-grid"
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
        v-if="canTreat && openStatuses"
        size="sm"
        :disabled="saving || !rxForm.medication_id || !rxForm.dose"
        @click="saveRx"
      >
        Prescribe
      </HButton>
    </div>
  </HOffcanvas>

  <HModal
    v-model="admitOpen"
    title="Admit patient"
    :error="formError"
    :persistent="saving"
  >
    <fieldset
      class="h-stack"
      :disabled="saving"
    >
      <HSelect
        v-if="beds.length"
        v-model="admitForm.facility_id"
        :items="beds"
        item-title="name"
        item-value="id"
        label="Bed or unit"
      />
      <HTextarea
        v-model="admitForm.notes"
        label="Admission notes"
      />
    </fieldset>
    <template #actions>
      <HButton
        variant="ghost"
        :disabled="saving"
        @click="admitOpen = false"
      >
        Cancel
      </HButton>
      <HButton
        :disabled="saving"
        @click="admit"
      >
        Admit
      </HButton>
    </template>
  </HModal>

  <HModal
    v-model="dischargeOpen"
    title="Discharge"
    :error="formError"
    :persistent="saving"
  >
    <HTextarea
      v-model="dischargeNotes"
      label="Discharge notes"
    />
    <template #actions>
      <HButton
        variant="ghost"
        :disabled="saving"
        @click="dischargeOpen = false"
      >
        Cancel
      </HButton>
      <HButton
        :disabled="saving"
        @click="discharge"
      >
        Discharge
      </HButton>
    </template>
  </HModal>

  <HOffcanvas
    v-model="editOpen"
    title="Edit visit"
    :error="formError"
    :persistent="saving"
  >
    <fieldset
      class="h-stack"
      :disabled="saving"
    >
      <HInput
        v-model="editForm.chief_complaint"
        label="Chief complaint"
      />
      <HSelect
        v-model="editForm.clinician_id"
        :items="clinicians"
        item-title="name"
        item-value="id"
        label="Clinician"
      />
    </fieldset>
    <template #actions>
      <HButton
        variant="ghost"
        :disabled="saving"
        @click="editOpen = false"
      >
        Cancel
      </HButton>
      <HButton
        :disabled="saving"
        @click="saveEdit"
      >
        Save
      </HButton>
    </template>
  </HOffcanvas>

  <HOffcanvas
    v-model="invoiceOpen"
    :title="invoice?.number || 'Charge sheet'"
    size="lg"
  >
    <div
      v-if="invoice"
      class="h-stack"
    >
      <HBadge :tone="statusColor(invoice.status)">
        {{ labelize(invoice.status) }}
      </HBadge>
      <p class="h-muted">
        Total {{ invoice.total }}
      </p>
      <HTable
        :headers="[
          { title: 'Description', key: 'description' },
          { title: 'Qty', key: 'quantity' },
          { title: 'Amount', key: 'unit_amount' },
        ]"
        :items="invoice.items"
        empty="No charges posted yet"
      />
    </div>
  </HOffcanvas>
</template>
