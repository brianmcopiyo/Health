<script setup>
import EncounterChart from '@/components/hms/EncounterChart.vue'
import { facilityRecordTo } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {},
})

const route = useRoute()
const ability = useAbility()
const record = ref(null)
const tab = ref('overview')
const chartOpen = ref(false)

const tabs = [
  { title: 'Overview', value: 'overview' },
  { title: 'Team', value: 'team' },
  { title: 'Clinical', value: 'clinical' },
  { title: 'Orders', value: 'orders' },
  { title: 'Pharmacy', value: 'pharmacy' },
  { title: 'Billing', value: 'billing' },
]

const load = async () => {
  record.value = await $api(`/encounters/${route.params.id}`)
}

watch(() => route.params.id, () => withPageLoad(load))
await withPageLoad(load)
</script>

<template>
  <HRecord
    :title="record ? `${labelize(record.type)} encounter` : 'Encounter'"
    :subtitle="record?.chief_complaint || ''"
    :status="record?.status"
    :back="{ name: 'patients' }"
    back-label="Patients"
    :tabs="tabs"
    :tab="tab"
    :missing="!record"
    @update:tab="tab = $event"
  >
    <template
      v-if="record"
      #actions
    >
      <HButton @click="chartOpen = true">
        Open chart
      </HButton>
    </template>

    <div
      v-if="record && tab === 'overview'"
      class="h-detail"
    >
      <HCard title="Visit">
        <div class="h-metric">
          <span>Patient</span>
          <strong>
            <RouterLink
              v-if="record.patient?.id"
              class="h-inline-link"
              :to="{ name: 'patients-id', params: { id: record.patient.id } }"
            >
              {{ record.patient.full_name || `${record.patient.first_name} ${record.patient.last_name}` }}
            </RouterLink>
          </strong>
        </div>
        <div class="h-metric">
          <span>Clinician</span>
          <strong>
            <RouterLink
              v-if="record.clinician?.id && ability.can('read', 'User')"
              class="h-inline-link"
              :to="{ name: 'admin-users-id', params: { id: record.clinician.id } }"
            >
              {{ record.clinician.name }}
            </RouterLink>
            <span v-else>{{ record.clinician?.name || '—' }}</span>
          </strong>
        </div>
        <div class="h-metric">
          <span>Department</span>
          <strong>
            <RouterLink
              v-if="record.department?.id"
              class="h-inline-link"
              :to="{ name: 'admin-departments-id', params: { id: record.department.id } }"
            >
              {{ record.department.name }}
            </RouterLink>
            <span v-else>—</span>
          </strong>
        </div>
        <div class="h-metric">
          <span>Unit</span>
          <strong>
            <RouterLink
              v-if="record.facility?.id"
              class="h-inline-link"
              :to="facilityRecordTo(record.facility)"
            >
              {{ record.facility.name }}
            </RouterLink>
            <span v-else>—</span>
          </strong>
        </div>
        <p
          v-if="record.notes"
          class="h-muted"
        >
          {{ record.notes }}
        </p>
      </HCard>
    </div>

    <HCard
      v-if="record && tab === 'team'"
      title="Care team"
      flush
    >
      <HTable
        :headers="[
          { title: 'Clinician', key: 'user.name' },
          { title: 'Role', key: 'care_role' },
        ]"
        :items="record.care_team || []"
        empty="No care team recorded"
      >
        <template #cell-user.name="{ item }">
          <RouterLink
            v-if="item.user?.id"
            class="h-inline-link"
            :to="{ name: 'admin-users-id', params: { id: item.user.id } }"
          >
            {{ item.user.name }}
          </RouterLink>
          <span v-else>—</span>
        </template>
      </HTable>
    </HCard>

    <template v-if="record && tab === 'clinical'">
      <HCard
        title="Diagnoses"
        flush
      >
        <HTable
          :headers="[
            { title: 'Diagnosis', key: 'name' },
            { title: 'Code', key: 'code' },
          ]"
          :items="record.diagnoses || []"
          empty="No diagnoses"
        />
      </HCard>
      <HCard
        title="Notes"
        flush
      >
        <HTable
          :headers="[
            { title: 'Note', key: 'body' },
            { title: 'Author', key: 'author.name' },
          ]"
          :items="record.clinical_notes || []"
          empty="No clinical notes"
        />
      </HCard>
      <HCard
        title="Vitals"
        flush
      >
        <HTable
          :headers="[
            { title: 'Pulse', key: 'pulse' },
            { title: 'Temp', key: 'temperature' },
            { title: 'BP', key: 'systolic' },
          ]"
          :items="record.vitals || []"
          empty="No vitals recorded"
        />
      </HCard>
    </template>

    <HCard
      v-if="record && tab === 'orders'"
      title="Orders"
      flush
    >
      <HTable
        :headers="[
          { title: 'Item', key: 'item_name' },
          { title: 'Module', key: 'module_key' },
          { title: 'Status', key: 'status' },
        ]"
        :items="record.orders || []"
        empty="No orders"
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
      v-if="record && tab === 'pharmacy'"
      title="Prescriptions"
      flush
    >
      <HTable
        :headers="[
          { title: 'Medicines', key: 'items' },
          { title: 'Status', key: 'status' },
        ]"
        :items="record.prescriptions || []"
        empty="No prescriptions"
      >
        <template #cell-items="{ item }">
          {{ (item.items || []).map(row => row.medication?.name).join(', ') || 'Prescription' }}
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
      </HTable>
    </HCard>

    <HCard
      v-if="record && tab === 'billing'"
      title="Invoices"
      flush
    >
      <HTable
        :headers="[
          { title: 'Number', key: 'number' },
          { title: 'Total', key: 'total' },
          { title: 'Status', key: 'status' },
        ]"
        :items="record.invoices || []"
        empty="No invoices"
      >
        <template #cell-number="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'billing-id', params: { id: item.id } }"
          >
            {{ item.number }}
          </RouterLink>
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
      </HTable>
    </HCard>

    <EncounterChart
      v-model="chartOpen"
      :encounter-id="record?.id"
      @saved="load"
    />
  </HRecord>
</template>
