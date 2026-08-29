<script setup>
import { reportCell } from '@/composables/useReports'
import { labelize, statusColor } from '@/utils/status'

const props = defineProps({
  payload: { type: Object, default: null },
  loading: Boolean,
})

const emit = defineEmits(['page'])

const kpis = computed(() => props.payload?.kpis || [])
const charts = computed(() => props.payload?.charts || [])
const comparisons = computed(() => props.payload?.comparisons || [])
const exceptions = computed(() => props.payload?.exceptions || [])
const activity = computed(() => props.payload?.activity || [])
const table = computed(() => props.payload?.table || { headers: [], items: [], meta: {} })
const showSkel = useDelayedVisible(() => props.loading)

const chartClass = chart => chart.type === 'trend' ? 'h-dash-lg' : 'h-dash-md'

const itemsFor = chart => (chart.items || []).filter(item => Number(item.value) > 0 || chart.type === 'trend')
</script>

<template>
  <div class="h-report-board">
    <HGrid
      cols="4"
      kind="stats"
    >
      <HStat
        v-for="kpi in (kpis.length ? kpis : [{ key: 'a' }, { key: 'b' }, { key: 'c' }, { key: 'd' }])"
        :key="kpi.key"
        :icon="kpi.icon || 'chart'"
        :title="kpi.title"
        :value="kpi.value"
        :hint="kpi.hint"
        :tone="kpi.tone || ''"
        :to="kpi.to"
        :loading="loading"
      />
    </HGrid>

    <div
      v-if="charts.length || loading"
      class="h-dash"
    >
      <HCard
        v-for="chart in (charts.length ? charts : [{ key: 'trend', type: 'trend', title: '' }, { key: 'bars', type: 'bars', title: '' }])"
        :key="chart.key"
        :title="chart.title"
        :class="chartClass(chart)"
      >
        <template v-if="loading">
          <div
            class="h-skeleton is-chart"
            :class="{ 'is-hold': !showSkel }"
          />
        </template>
        <HReportTrend
          v-else-if="chart.type === 'trend'"
          :items="chart.items || []"
        />
        <HBars
          v-else
          :items="itemsFor(chart)"
        />
      </HCard>
    </div>

    <HGrid
      v-if="comparisons.length || exceptions.length || activity.length || loading"
      :cols="(comparisons.length ? 1 : 0) + (exceptions.length || loading ? 1 : 0) + (activity.length ? 1 : 0) || 3"
    >
      <HCard
        v-if="comparisons.length"
        title="Versus prior period"
      >
        <div
          v-for="item in comparisons"
          :key="item.label"
          class="h-metric"
        >
          <span>{{ item.label }}</span>
          <strong>{{ item.current }} <small :class="Number(item.delta) > 0 ? 'is-up' : Number(item.delta) < 0 ? 'is-down' : ''">{{ Number(item.delta) > 0 ? '+' : '' }}{{ item.delta }}%</small></strong>
        </div>
      </HCard>
      <HCard
        v-if="exceptions.length || loading"
        title="Exceptions"
      >
        <template v-if="loading">
          <div
            v-for="n in 3"
            :key="n"
            class="h-metric"
            :class="{ 'is-hold': !showSkel }"
          >
            <span class="h-skeleton is-label" />
            <strong class="h-skeleton is-value" />
          </div>
        </template>
        <template v-else-if="exceptions.length">
          <div
            v-for="item in exceptions"
            :key="item.title"
            class="h-metric"
          >
            <RouterLink
              v-if="item.to"
              :to="typeof item.to === 'string' ? { name: item.to } : item.to"
            >
              {{ item.title }}
            </RouterLink>
            <span v-else>{{ item.title }}</span>
            <HBadge :tone="item.tone === 'danger' ? 'error' : item.tone === 'warn' ? 'warning' : item.tone === 'info' ? 'info' : 'secondary'">
              {{ item.value }}
            </HBadge>
          </div>
        </template>
        <HEmpty
          v-else
          message="No exceptions in this range"
        />
      </HCard>
      <HCard
        v-if="activity.length"
        title="Recent activity"
      >
        <div
          v-for="item in activity"
          :key="item.title + (item.meta || '')"
          class="h-dash-task"
        >
          <div>
            <RouterLink
              v-if="item.to"
              :to="item.to"
            >
              {{ item.title }}
            </RouterLink>
            <strong v-else>{{ item.title }}</strong>
            <small>{{ item.meta }}</small>
          </div>
          <HBadge
            v-if="item.status"
            :tone="statusColor(item.status)"
          >
            {{ labelize(item.status) }}
          </HBadge>
        </div>
      </HCard>
    </HGrid>

    <HCard
      :title="table.title || 'Records'"
      flush
    >
      <HTable
        :loading="loading"
        :headers="table.headers || []"
        :items="table.items || []"
        :empty="table.empty || 'No records in this range'"
      >
        <template
          v-for="header in (table.headers || [])"
          :key="header.key"
          #[`cell-${header.key}`]="{ item }"
        >
          <RouterLink
            v-if="header.key === table.link_key && item.to"
            :to="item.to"
          >
            {{ reportCell(item, header.key) }}
          </RouterLink>
          <HBadge
            v-else-if="header.key === 'status'"
            :tone="statusColor(item.status)"
          >
            {{ labelize(item.status) }}
          </HBadge>
          <template v-else>
            {{ reportCell(item, header.key) }}
          </template>
        </template>
      </HTable>
      <HPager
        :meta="table.meta || {}"
        @update:page="emit('page', $event)"
      />
    </HCard>
  </div>
</template>
