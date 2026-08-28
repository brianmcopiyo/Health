<script setup>
import EncounterChart from '@/components/hms/EncounterChart.vue'
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
const encounterId = ref(null)
const chartOpen = ref(false)

const load = async () => {
  chart.value = await $api(`/patients/${route.params.id}`)
}

const openEncounter = id => {
  encounterId.value = id
  chartOpen.value = true
}

await withPageLoad(load)
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
        Register
      </HButton>
    </HPage>

    <div
      v-if="!chart"
      class="h-alert"
    >
      This record could not be loaded.
    </div>

    <div
      v-else
      class="h-grid cols-2"
    >
      <HCard title="Identity">
        <div class="h-metric">
          <span>Phone</span>
          <strong>{{ chart.phone || '—' }}</strong>
        </div>
        <div class="h-metric">
          <span>Blood group</span>
          <strong>{{ chart.blood_group || '—' }}</strong>
        </div>
        <div class="h-metric">
          <span>Next of kin</span>
          <strong>{{ chart.next_of_kin_name || '—' }} {{ chart.next_of_kin_phone ? `· ${chart.next_of_kin_phone}` : '' }}</strong>
        </div>
        <p v-if="chart.allergies?.length">
          Allergies: {{ chart.allergies.map(item => item.allergen).join(', ') }}
        </p>
        <p v-if="chart.conditions?.length">
          History: {{ chart.conditions.map(item => item.name).join(', ') }}
        </p>
      </HCard>

      <HCard title="Current care">
        <div
          v-if="chart.active_bed"
          class="h-metric"
        >
          <span>Bed</span>
          <strong>{{ chart.active_bed.facility?.name }}</strong>
        </div>
        <HTable
          :headers="[
            { title: 'Encounter', key: 'type' },
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
    </div>

    <HCard
      v-if="chart"
      title="Clinical timeline"
      style="margin-top:18px"
    >
      <ol class="h-timeline">
        <li
          v-for="(item, index) in chart.timeline"
          :key="index"
        >
          <strong>{{ item.title }}</strong>
          <span>{{ item.detail }}</span>
          <em>{{ item.actor }} · {{ item.at ? new Date(item.at).toLocaleString() : '' }}</em>
        </li>
      </ol>
    </HCard>

    <EncounterChart
      v-model="chartOpen"
      :encounter-id="encounterId"
      @saved="load"
    />
  </div>
</template>
