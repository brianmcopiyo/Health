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
const invoiceOpen = ref(false)
const invoice = ref(null)
const saving = ref(false)
const formError = ref('')
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

const openInvoice = async id => {
  if (!ability.can('read', 'Invoice'))
    return
  invoice.value = await $api(`/invoices/${id}`)
  invoiceOpen.value = true
}

await withPageLoad(load)

const today = new Date().toISOString().slice(0, 10)
</script>

<template>
  <div>
    <HPage
      :title="chart ? `${chart.first_name} ${chart.last_name}` : 'Patient'"
      :subtitle="chart ? `${chart.mrn} · ${labelize(chart.status)}` : ''"
    >
      <HButton
        variant="ghost"
        :to="{ name: 'patients' }"
      >
        <HIcon name="back" />
        Patients
      </HButton>
      <HButton
        v-if="chart && ability.can('update', 'Patient')"
        @click="openEdit"
      >
        <HIcon name="edit" />
        Edit
      </HButton>
      <HActionMenu v-if="chart">
        <template #default="{ close }">
          <button
            v-if="ability.can('update', 'Patient')"
            type="button"
            class="h-action-item"
            @click="openUpload(); close()"
          >
            Upload document
          </button>
          <button
            v-if="ability.can('manage', 'Patient')"
            type="button"
            class="h-action-item"
            @click="exportRecord(); close()"
          >
            Export record
          </button>
        </template>
      </HActionMenu>
    </HPage>

    <div
      v-if="formError && !editOpen && !uploadOpen"
      class="h-alert"
    >
      {{ formError }}
    </div>

    <div
      v-if="!chart"
      class="h-alert"
    >
      This record could not be loaded.
    </div>

    <template v-else>
      <div class="h-detail">
        <HCard title="Identity">
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
          <div
            v-if="chart.active_bed"
            class="h-metric"
          >
            <span>Bed</span>
            <strong>
              <RouterLink
                v-if="chart.active_bed.facility?.id"
                class="h-inline-link"
                :to="{ name: 'facilities-id', params: { id: chart.active_bed.facility.id } }"
              >
                {{ chart.active_bed.facility.name }}
              </RouterLink>
              <template v-else>
                {{ chart.active_bed.facility?.name || 'Assigned' }}
              </template>
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
        title="Encounters"
        flush
      >
        <HTable
          :headers="[
            { title: 'Type', key: 'type' },
            { title: 'Complaint', key: 'chief_complaint' },
            { title: 'Clinician', key: 'clinician.name' },
            { title: 'Status', key: 'status' },
            { title: '', key: 'actions' },
          ]"
          :items="chart.encounters"
          empty="No encounters yet"
        >
          <template #cell-type="{ item }">
            {{ labelize(item.type) }}
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
          <template #cell-actions="{ item }">
            <HButton
              size="sm"
              variant="ghost"
              @click="openEncounter(item.id)"
            >
              Open
            </HButton>
          </template>
        </HTable>
      </HCard>

      <HCard
        title="Orders"
        flush
      >
        <HTable
          :headers="[
            { title: 'Item', key: 'item_name' },
            { title: 'Module', key: 'module_key' },
            { title: 'Status', key: 'status' },
          ]"
          :items="chart.orders"
          empty="No service orders"
        >
          <template #cell-module_key="{ item }">
            {{ labelize(item.module_key) }}
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
        </HTable>
      </HCard>

      <HCard
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
            {{ (item.items || []).map(row => row.medication?.name).join(', ') || '—' }}
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
        </HTable>
      </HCard>

      <HCard
        title="Invoices"
        flush
      >
        <HTable
          :headers="[
            { title: 'Number', key: 'number' },
            { title: 'Total', key: 'total' },
            { title: 'Status', key: 'status' },
            { title: '', key: 'actions' },
          ]"
          :items="chart.invoices"
          empty="No invoices"
        >
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
          <template #cell-actions="{ item }">
            <HButton
              v-if="ability.can('read', 'Invoice')"
              size="sm"
              variant="ghost"
              @click="openInvoice(item.id)"
            >
              View
            </HButton>
          </template>
        </HTable>
      </HCard>

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
            <RouterLink
              v-if="item.facility?.id"
              class="h-inline-link"
              :to="{ name: 'facilities-id', params: { id: item.facility.id } }"
            >
              {{ item.facility.name }}
            </RouterLink>
            <span v-else>{{ item.facility?.name || '—' }}</span>
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
            { title: 'File', key: 'original_name' },
            { title: 'Uploaded', key: 'uploaded_at' },
            { title: '', key: 'actions' },
          ]"
          :items="documents"
          empty="No documents on this record"
        >
          <template #cell-uploaded_at="{ item }">
            {{ item.uploaded_at ? new Date(item.uploaded_at).toLocaleString() : '—' }}
          </template>
          <template #cell-actions="{ item }">
            <HButton
              size="sm"
              variant="ghost"
              @click="downloadDocument(item)"
            >
              Download
            </HButton>
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
          required
        />
        <HInput
          v-model="form.last_name"
          label="Last name"
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
        />
        <HInput
          v-model="form.mrn"
          label="MRN"
        />
        <HInput
          v-model="form.national_id"
          label="National ID"
        />
        <HCombobox
          v-model="form.blood_group"
          :items="bloodGroups"
          label="Blood group"
          placeholder="Select or type"
        />
      </fieldset>
      <HInput
        v-model="form.address"
        label="Address"
        :disabled="saving"
      />
      <fieldset
        class="h-form-grid is-3"
        :disabled="saving"
      >
        <HInput
          v-model="form.next_of_kin_name"
          label="Next of kin"
        />
        <HInput
          v-model="form.next_of_kin_phone"
          label="Next of kin phone"
          type="tel"
          icon="phone"
        />
        <HCombobox
          v-model="form.next_of_kin_relation"
          :items="kinshipOptions"
          label="Relation"
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
        />
        <HInput
          v-model="row.reaction"
          label="Reaction"
        />
        <HInput
          v-model="row.severity"
          label="Severity"
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
        />
        <HInput
          v-model="row.status"
          label="Status"
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
          :disabled="saving || !uploadFile"
          @click="uploadDocument"
        >
          Upload
        </HButton>
      </template>
    </HModal>

    <HOffcanvas
      v-model="invoiceOpen"
      :title="invoice?.number || 'Invoice'"
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
          empty="No line items"
        />
      </div>
    </HOffcanvas>

    <EncounterChart
      v-model="chartOpen"
      :encounter-id="encounterId"
      @saved="load"
    />
  </div>
</template>
