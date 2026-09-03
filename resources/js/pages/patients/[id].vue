<script setup>
import EncounterChart from '@/components/hms/EncounterChart.vue'
import { bloodGroups, kinshipOptions, sexOptions } from '@/utils/clinicalOptions'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Patient',
  },
})

const route = useRoute()
const ability = useAbility()
const chart = ref(null)
const documents = ref([])
const encounterId = ref(null)
const chartOpen = ref(false)
const editOpen = ref(false)
const uploadOpen = ref(false)
const statusOpen = ref(false)
const visitOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const statusForm = ref('active')
const visitForm = ref({ type: 'opd', chief_complaint: '' })
const uploadFile = ref(null)
const form = ref({
  first_name: '',
  last_name: '',
  sex: null,
  date_of_birth: null,
  phone: '',
  address: '',
  mrn: '',
  blood_group: '',
  national_id: '',
  next_of_kin_name: '',
  next_of_kin_phone: '',
  next_of_kin_relation: '',
  allergies: [],
  conditions: [],
})

const load = async () => {
  chart.value = await $api(`/patients/${route.params.id}`)
  if (ability.can('read', 'Patient'))
    documents.value = asList(await $api(`/patients/${route.params.id}/documents`).catch(() => []))
}

const fillForm = () => {
  const item = chart.value
  form.value = {
    first_name: item.first_name,
    last_name: item.last_name,
    sex: item.sex,
    date_of_birth: item.date_of_birth,
    phone: item.phone,
    address: item.address,
    mrn: item.mrn,
    blood_group: item.blood_group,
    national_id: item.national_id,
    next_of_kin_name: item.next_of_kin_name,
    next_of_kin_phone: item.next_of_kin_phone,
    next_of_kin_relation: item.next_of_kin_relation,
    allergies: (item.allergies || []).map(row => ({
      allergen: row.allergen,
      reaction: row.reaction || '',
      severity: row.severity || 'moderate',
    })),
    conditions: (item.conditions || []).map(row => ({
      name: row.name,
      status: row.status || 'active',
    })),
  }
}

const openEdit = () => {
  formError.value = ''
  fillForm()
  editOpen.value = true
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/patients/${chart.value.id}`, {
      method: 'PUT',
      body: {
        ...form.value,
        allergies: form.value.allergies.filter(row => row.allergen),
        conditions: form.value.conditions.filter(row => row.name),
      },
    })
    editOpen.value = false
    await load()
  })
}

const openEncounter = id => {
  encounterId.value = id
  chartOpen.value = true
}

const exportRecord = async () => {
  await wrapSave(saving, formError, async () => {
    await downloadAuthorized(`/patients/${chart.value.id}/export`, `${chart.value.mrn}.json`)
  })
}

const openUpload = () => {
  formError.value = ''
  uploadFile.value = null
  uploadOpen.value = true
}

const uploadDocument = async () => {
  await wrapSave(saving, formError, async () => {
    const body = new FormData()
    body.append('file', uploadFile.value)
    await $api(`/patients/${chart.value.id}/documents`, { method: 'POST', body })
    uploadOpen.value = false
    await load()
  })
}

const downloadDocument = async item => {
  await wrapSave(saving, formError, async () => {
    await downloadAuthorized(`/documents/${item.id}/download`, item.original_name || 'document')
  })
}

const openStatus = () => {
  formError.value = ''
  statusForm.value = chart.value.status
  statusOpen.value = true
}

const saveStatus = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/patients/${chart.value.id}`, { method: 'PUT', body: { status: statusForm.value } })
    statusOpen.value = false
    await load()
  })
}

const archivePatient = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/patients/${chart.value.id}/archive`, { method: 'PATCH' })
    await load()
  })
}

const openVisit = () => {
  formError.value = ''
  visitForm.value = { type: 'opd', chief_complaint: '' }
  visitOpen.value = true
}

const startVisit = async () => {
  await wrapSave(saving, formError, async () => {
    const encounter = await $api('/encounters', {
      method: 'POST',
      body: { patient_id: chart.value.id, ...visitForm.value },
    })
    visitOpen.value = false
    await load()
    openEncounter(encounter.id)
  })
}

const tab = ref('overview')
const tabs = [
  { title: 'Overview', value: 'overview' },
  { title: 'Encounters', value: 'encounters' },
  { title: 'Orders', value: 'orders' },
  { title: 'Pharmacy', value: 'pharmacy' },
  { title: 'Billing', value: 'billing' },
  { title: 'History', value: 'history' },
]

const { pending, run } = usePageQuery(load)
watch(() => route.params.id, () => run())

const today = new Date().toISOString().slice(0, 10)
</script>

<template>
  <HRecord
    :title="chart ? `${chart.first_name} ${chart.last_name}` : 'Patient'"
    :subtitle="chart ? chart.mrn : ''"
    :status="chart?.status"
    :statuses="chart?.archived_at && chart?.status !== 'archived' ? ['archived'] : []"
    :back="{ name: 'patients' }"
    back-label="Patients"
    :tabs="tabs"
    :tab="tab"
    :loading="pending"
    :missing="!pending && !chart"
    @update:tab="tab = $event"
  >
    <div
      v-if="formError && !editOpen && !uploadOpen"
      class="h-alert"
    >
      {{ formError }}
    </div>

    <template v-if="chart">
      <div
        v-if="tab === 'overview'"
        class="h-detail"
      >
        <HCard title="Identity">
          <template
            v-if="ability.can('update', 'Patient') || ability.can('manage', 'Patient')"
            #actions
          >
            <HActionMenu
              :compact="false"
              label="More"
              :actions="[
                { label: 'Edit', icon: 'edit', if: ability.can('update', 'Patient'), onSelect: openEdit },
                { label: 'Update status', icon: 'wrench', if: ability.can('update', 'Patient'), onSelect: openStatus },
                { label: 'Export record', icon: 'download', if: ability.can('manage', 'Patient'), onSelect: exportRecord },
                { label: 'Archive', icon: 'ban', danger: true, if: ability.can('update', 'Patient') && !chart.archived_at, onSelect: archivePatient },
              ]"
            />
          </template>
          <div class="h-metric">
            <span>Sex</span>
            <strong>{{ labelize(chart.sex) || '—' }}</strong>
          </div>
          <div class="h-metric">
            <span>Date of birth</span>
            <strong>{{ chart.date_of_birth || '—' }}</strong>
          </div>
          <div class="h-metric">
            <span>Phone</span>
            <strong>{{ chart.phone || '—' }}</strong>
          </div>
          <div class="h-metric">
            <span>Blood group</span>
            <strong>{{ chart.blood_group || '—' }}</strong>
          </div>
          <div class="h-metric">
            <span>National ID</span>
            <strong>{{ chart.national_id || '—' }}</strong>
          </div>
          <div class="h-metric">
            <span>Address</span>
            <strong>{{ chart.address || '—' }}</strong>
          </div>
          <div class="h-metric">
            <span>Next of kin</span>
            <strong>{{ chart.next_of_kin_name || '—' }} {{ chart.next_of_kin_phone ? `· ${chart.next_of_kin_phone}` : '' }} {{ chart.next_of_kin_relation ? `· ${chart.next_of_kin_relation}` : '' }}</strong>
          </div>
        </HCard>

        <HCard title="Current care">
          <template
            v-if="ability.can('create', 'Opd') || ability.can('create', 'Reception') || ability.can('create', 'Emergency')"
            #actions
          >
            <HButton
              size="sm"
              @click="openVisit"
            >
              Open visit
            </HButton>
          </template>
          <div
            v-if="chart.active_bed"
            class="h-metric"
          >
            <span>Bed</span>
            <strong>
              <RouterLink
                v-if="chart.active_bed.facility?.id"
                class="h-inline-link"
                :to="{ name: 'beds-id', params: { id: chart.active_bed.facility.id } }"
              >
                {{ chart.active_bed.facility.name }}
              </RouterLink>
              <template v-else>
                {{ chart.active_bed.facility?.name || 'Assigned' }}
              </template>
            </strong>
          </div>
          <div
            v-if="chart.active_bed?.ward || chart.active_bed?.facility?.parent"
            class="h-metric"
          >
            <span>Ward</span>
            <strong>
              <RouterLink
                v-if="(chart.active_bed.ward || chart.active_bed.facility.parent).id"
                class="h-inline-link"
                :to="{ name: 'wards-id', params: { id: (chart.active_bed.ward || chart.active_bed.facility.parent).id } }"
              >
                {{ (chart.active_bed.ward || chart.active_bed.facility.parent).name }}
              </RouterLink>
            </strong>
          </div>
          <p
            v-else
            class="h-muted"
          >
            No active bed assignment.
          </p>
          <p
            v-if="chart.allergies?.length"
            class="h-alert warning"
          >
            Allergies: {{ chart.allergies.map(item => item.allergen).join(', ') }}
          </p>
          <p
            v-if="chart.conditions?.length"
            class="h-muted"
          >
            History: {{ chart.conditions.map(item => item.name).join(', ') }}
          </p>
        </HCard>
      </div>

      <HCard
        v-if="tab === 'encounters'"
        title="Encounters"
        flush
      >
        <HTable
          :headers="[
            { title: 'Encounter', key: 'type', fill: true },
            { title: 'Clinician', key: 'clinician.name' },
            { title: 'Status', key: 'status' },
            { title: 'Actions', key: 'actions' },
          ]"
          :items="chart.encounters"
          empty="No encounters yet"
        >
          <template #cell-type="{ item }">
            <HCell
              :to="{ name: 'encounters-id', params: { id: item.id } }"
              :secondary="item.chief_complaint"
            >
              {{ labelize(item.type) }}
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
                { label: 'Open chart', icon: 'stethoscope', onSelect: () => openEncounter(item.id) },
              ]"
            />
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="tab === 'orders'"
        title="Orders"
        flush
      >
        <HTable
          :headers="[
            { title: 'Item', key: 'item_name', fill: true },
            { title: 'Status', key: 'status' },
          ]"
          :items="chart.orders"
          empty="No service orders"
        >
          <template #cell-item_name="{ item }">
            <HCell
              :to="['laboratory', 'imaging', 'pharmacy', 'theatre', 'emergency'].includes(item.module_key) ? { name: item.module_key } : null"
              :secondary="labelize(item.module_key)"
            >
              {{ item.item_name }}
            </HCell>
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="tab === 'pharmacy'"
        title="Prescriptions"
        flush
      >
        <HTable
          :headers="[
            { title: 'Medicines', key: 'items' },
            { title: 'Status', key: 'status' },
          ]"
          :items="chart.prescriptions"
          empty="No prescriptions"
        >
          <template #cell-items="{ item }">
            <HCell :to="{ name: 'pharmacy' }">
              {{ (item.items || []).map(row => row.medication?.name).join(', ') || 'Prescription' }}
            </HCell>
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="tab === 'billing'"
        title="Invoices"
        flush
      >
        <HTable
          :headers="[
            { title: 'Number', key: 'number', fill: true },
            { title: 'Total', key: 'total' },
            { title: 'Status', key: 'status' },
            { title: 'Actions', key: 'actions' },
          ]"
          :items="chart.invoices"
          empty="No invoices"
        >
          <template #cell-number="{ item }">
            <HCell :to="{ name: 'billing-id', params: { id: item.id } }">
              {{ item.number }}
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
                { label: 'View', icon: 'eye', if: ability.can('read', 'Invoice'), to: { name: 'billing-id', params: { id: item.id } } },
              ]"
            />
          </template>
        </HTable>
      </HCard>

      <template v-if="tab === 'history'">
      <HCard
        title="Bed history"
        flush
      >
        <HTable
          :headers="[
            { title: 'Facility', key: 'facility.name' },
            { title: 'Status', key: 'status' },
          ]"
          :items="chart.bed_assignments"
          empty="No bed assignments"
        >
          <template #cell-facility.name="{ item }">
            <HCell
              :to="item.facility?.id ? { name: 'beds-id', params: { id: item.facility.id } } : null"
              :secondary="item.facility?.parent?.name || item.ward?.name"
            >
              {{ item.facility?.name || '—' }}
            </HCell>
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
        </HTable>
      </HCard>

      <HCard
        title="Documents"
        flush
      >
        <template
          v-if="ability.can('update', 'Patient')"
          #actions
        >
          <HButton
            size="sm"
            @click="openUpload"
          >
            <HIcon name="upload" />
            Upload
          </HButton>
        </template>
        <HTable
          :headers="[
            { title: 'File', key: 'original_name', fill: true },
            { title: 'Uploaded', key: 'uploaded_at' },
            { title: '', key: 'actions' },
          ]"
          :items="documents"
          empty="No documents on this record"
        >
          <template #cell-uploaded_at="{ item }">
            {{ item.uploaded_at ? new Date(item.uploaded_at).toLocaleString() : '—' }}
          </template>
          <template #cell-original_name="{ item }">
            <HCell>
              {{ item.original_name }}
            </HCell>
          </template>
          <template #cell-actions="{ item }">
            <HActionMenu
              :actions="[
                { label: 'Download', icon: 'download', onSelect: () => downloadDocument(item) },
              ]"
            />
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="chart.referrals?.length"
        title="Referrals"
        flush
      >
        <HTable
          :headers="[
            { title: 'Destination', key: 'to_hospital.name', fill: true },
            { title: 'Status', key: 'status' },
          ]"
          :items="chart.referrals"
          empty="No referrals"
        >
          <template #cell-to_hospital.name="{ item }">
            <HCell
              :to="{ name: 'referrals-id', params: { id: item.id } }"
              :secondary="item.from_hospital?.name"
            >
              {{ item.to_hospital?.name || 'Referral' }}
            </HCell>
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
        </HTable>
      </HCard>
      <HCard title="Clinical timeline">
        <ol
          v-if="chart.timeline?.length"
          class="h-timeline"
        >
          <li
            v-for="(item, index) in chart.timeline"
            :key="index"
          >
            <strong>{{ item.title }}</strong>
            <span>{{ item.detail }}</span>
            <em>{{ item.actor }} · {{ item.at ? new Date(item.at).toLocaleString() : '' }}</em>
          </li>
        </ol>
        <HEmpty
          v-else
          message="No timeline events yet"
        />
      </HCard>
      </template>
    </template>

    <HOffcanvas
      v-model="editOpen"
      title="Update patient"
      size="lg"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
        class="h-form-grid"
        :disabled="saving"
      >
        <HInput
          v-model="form.first_name"
          label="First name"
          placeholder="Enter first name"
          required
        />
        <HInput
          v-model="form.last_name"
          label="Last name"
          placeholder="Enter last name"
          required
        />
        <HRadioGroup
          v-model="form.sex"
          :items="sexOptions"
          label="Sex"
        />
        <HDatePicker
          v-model="form.date_of_birth"
          label="Date of birth"
          :max="today"
        />
        <HInput
          v-model="form.phone"
          label="Phone"
          type="tel"
          icon="phone"
          placeholder="e.g. 024 555 0100"
        />
        <HInput
          v-model="form.mrn"
          label="MRN"
          placeholder="e.g. RGH-0042"
        />
        <HInput
          v-model="form.national_id"
          label="National ID"
          placeholder="e.g. GHA-123-456-789"
        />
        <HCombobox
          v-model="form.blood_group"
          :items="bloodGroups"
          label="Blood group"
          placeholder="Type or select blood group"
        />
        <HInput
          span
          v-model="form.address"
          label="Address"
          placeholder="Street, city or area"
        />
      </fieldset>
      <fieldset
        class="h-form-grid is-3"
        :disabled="saving"
      >
        <HInput
          v-model="form.next_of_kin_name"
          label="Next of kin"
          placeholder="Full name"
        />
        <HInput
          v-model="form.next_of_kin_phone"
          label="Next of kin phone"
          type="tel"
          icon="phone"
          placeholder="e.g. 024 555 0100"
        />
        <HCombobox
          v-model="form.next_of_kin_relation"
          :items="kinshipOptions"
          label="Relation"
          placeholder="e.g. Spouse"
        />
      </fieldset>
      <h4>Allergies</h4>
      <fieldset
        v-for="(row, index) in form.allergies"
        :key="`a-${index}`"
        class="h-form-grid is-3"
        :disabled="saving"
      >
        <HInput
          v-model="row.allergen"
          label="Allergen"
          placeholder="e.g. Penicillin"
        />
        <HInput
          v-model="row.reaction"
          label="Reaction"
          placeholder="e.g. Rash"
        />
        <HInput
          v-model="row.severity"
          label="Severity"
          placeholder="e.g. Moderate"
        />
      </fieldset>
      <HButton
        variant="ghost"
        size="sm"
        @click="form.allergies.push({ allergen: '', reaction: '', severity: 'moderate' })"
      >
        Add allergy
      </HButton>
      <h4>Conditions</h4>
      <fieldset
        v-for="(row, index) in form.conditions"
        :key="`c-${index}`"
        class="h-form-grid"
        :disabled="saving"
      >
        <HInput
          v-model="row.name"
          label="Condition"
          placeholder="e.g. Hypertension"
        />
        <HInput
          v-model="row.status"
          label="Status"
          placeholder="e.g. Active"
        />
      </fieldset>
      <HButton
        variant="ghost"
        size="sm"
        @click="form.conditions.push({ name: '', status: 'active' })"
      >
        Add condition
      </HButton>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="editOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :loading="saving"
          :disabled="saving"
          @click="save"
        >
          Save
        </HButton>
      </template>
    </HOffcanvas>

    <HModal
      v-model="uploadOpen"
      title="Upload document"
      :error="formError"
      :persistent="saving"
    >
      <HFile
        v-model="uploadFile"
        label="File"
        placeholder="Drop a document or browse"
        required
      />
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="uploadOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :loading="saving"
          :disabled="saving || !uploadFile"
          @click="uploadDocument"
        >
          Upload
        </HButton>
      </template>
    </HModal>

    <HModal
      v-model="statusOpen"
      title="Update status"
      :error="formError"
      :persistent="saving"
    >
      <HSelect
        v-model="statusForm"
        :items="['active', 'admitted', 'discharged', 'transferred', 'deceased']"
        label="Status"
      />
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="statusOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :loading="saving"
          :disabled="saving"
          @click="saveStatus"
        >
          Save
        </HButton>
      </template>
    </HModal>

    <HModal
      v-model="visitOpen"
      title="Open visit"
      :error="formError"
      :persistent="saving"
    >
      <HSelect
        v-model="visitForm.type"
        :items="['opd', 'emergency', 'admission', 'follow_up']"
        label="Visit type"
      />
      <HInput
        v-model="visitForm.chief_complaint"
        label="Chief complaint"
        placeholder="Why is the patient here?"
      />
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
          :disabled="saving"
          @click="startVisit"
        >
          Start
        </HButton>
      </template>
    </HModal>

    <EncounterChart
      v-model="chartOpen"
      :encounter-id="encounterId"
      @saved="load"
    />
  </HRecord>
</template>
