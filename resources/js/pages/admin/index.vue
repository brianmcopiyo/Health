<script setup>
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {},
})

const ability = useAbility()
const userData = useCookie('userData')
const dash = ref(null)
const refreshing = ref(false)

const shown = key => Boolean(dash.value?.panels?.includes(key))

const adminCards = computed(() => {
  const items = [
    { title: 'Users', to: 'admin-users', icon: 'users', subject: 'User', text: 'Assign hospital access and roles' },
    { title: 'Roles', to: 'admin-roles', icon: 'shield', subject: 'Role', text: 'Configure permissions and workspaces' },
    { title: 'Departments', to: 'admin-departments', icon: 'building', subject: 'Department', text: 'Map departments to modules' },
    { title: 'Facilities', to: 'facilities', icon: 'hospital', subject: 'Facility', text: 'Capacity and unit configuration' },
    { title: 'Reports', to: 'reports', icon: 'chart', subject: 'Report', text: 'Operational utilization and activity' },
    { title: 'Hospitals', to: 'admin-hospitals', icon: 'community', subject: 'Hospital', text: 'Network hospital registry' },
  ]

  return items.filter(card => ability.can('read', card.subject) || ability.can('manage', card.subject))
})

const subtitle = computed(() => {
  const hospital = dash.value?.hospital?.name || userData.value?.hospitalName || 'Network operations'
  const role = userData.value?.jobTitle || labelize(dash.value?.role)

  return role ? `${hospital} · ${role}` : hospital
})

const money = value => Number(value || 0).toLocaleString()

const when = value => {
  if (!value)
    return '—'

  return new Date(value).toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const load = async () => {
  dash.value = markRaw(await $api('/dashboard'))
}

const { pending, run } = usePageQuery(load)
const skelHeaders = [
  { title: 'Patient', key: 'name' },
  { title: 'Status', key: 'status' },
  { title: 'When', key: 'at' },
]

const refresh = async () => {
  refreshing.value = true
  try {
    await run({ silent: true })
  }
  finally {
    refreshing.value = false
  }
}
</script>

<template>
  <div class="h-dash-page">
    <HPage
      title="Dashboard"
      :subtitle="subtitle"
    >
      <HButton
        variant="ghost"
        :disabled="refreshing"
        @click="refresh"
      >
        <HIcon
          name="refresh"
          :size="16"
        />
        Refresh
      </HButton>
    </HPage>

    <template v-if="pending && !dash">
      <HGrid
        cols="4"
        kind="stats"
      >
        <HStat
          v-for="n in 4"
          :key="n"
          :loading="true"
        />
      </HGrid>
      <div class="h-dash">
        <HCard
          flush
          class="h-dash-lg"
        >
          <HTable
            :loading="true"
            :headers="skelHeaders"
            :items="[]"
          />
        </HCard>
        <HCard
          flush
          class="h-dash-lg"
        >
          <HTable
            :loading="true"
            :headers="skelHeaders"
            :items="[]"
          />
        </HCard>
      </div>
    </template>

    <template v-else-if="dash">
    <section
      v-if="shown('alerts') && dash.alerts.length"
      class="h-dash-alerts"
    >
      <RouterLink
        v-for="alert in dash.alerts"
        :key="alert.title"
        class="h-dash-alert"
        :class="`is-${alert.tone || 'warning'}`"
        :to="typeof alert.to === 'string' ? { name: alert.to } : alert.to"
      >
        <strong>{{ alert.title }}</strong>
        <span>{{ alert.detail }}</span>
      </RouterLink>
    </section>

    <HGrid
      v-if="shown('kpis') && dash.kpis.length"
      cols="4"
      kind="stats"
    >
      <HStat
        v-for="stat in dash.kpis"
        :key="stat.key"
        :title="stat.title"
        :value="stat.value"
        :icon="stat.icon"
        :hint="stat.hint"
        :tone="stat.tone"
        :trend="stat.trend"
        :to="stat.to"
      />
    </HGrid>

    <div class="h-dash">
      <HCard
        v-if="shown('encounters_chart')"
        title="Encounter activity"
        class="h-dash-lg"
      >
        <HBars
          :items="dash.charts.encounters"
          empty="No encounters in the last seven days"
        />
      </HCard>

      <HCard
        v-if="shown('occupancy') && dash.occupancy"
        title="Ward and bed occupancy"
        class="h-dash-lg"
      >
        <template #actions>
          <HButton
            variant="ghost"
            size="sm"
            to="beds"
          >
            Beds
          </HButton>
        </template>
        <div class="h-dash-metrics">
          <p class="h-metric">
            <span>Occupied</span>
            <strong>{{ dash.occupancy.used }} / {{ dash.occupancy.capacity }}</strong>
          </p>
          <p class="h-metric">
            <span>Open beds</span>
            <strong>{{ dash.occupancy.remaining }}</strong>
          </p>
          <p class="h-metric">
            <span>Utilization</span>
            <strong>{{ dash.occupancy.percent }}%</strong>
          </p>
        </div>
        <div
          class="h-dash-meter"
          :class="{ 'is-warn': dash.occupancy.percent >= 80, 'is-danger': dash.occupancy.percent >= 90 }"
        >
          <i :style="{ width: `${dash.occupancy.percent}%` }" />
        </div>
        <HBars
          :items="dash.charts.occupancy"
          empty="No wards are configured"
        />
      </HCard>

      <HCard
        v-if="shown('tasks')"
        title="Needs attention"
        class="h-dash-md"
      >
        <div
          v-if="dash.tasks.length"
          class="h-dash-tasks"
        >
          <RouterLink
            v-for="task in dash.tasks"
            :key="task.title"
            class="h-dash-task"
            :to="typeof task.to === 'string' ? { name: task.to } : task.to"
          >
            <span>{{ task.title }}</span>
            <HBadge :tone="statusColor(task.tone === 'error' ? 'declined' : task.tone === 'info' ? 'in_progress' : 'pending')">
              {{ task.count }}
            </HBadge>
          </RouterLink>
        </div>
        <HEmpty
          v-else
          message="Nothing needs attention right now"
        />
      </HCard>

      <HCard
        v-if="shown('departments')"
        title="Department activity"
        class="h-dash-md"
      >
        <HBars
          :items="dash.departments"
          empty="No open encounters are assigned to a department"
        />
      </HCard>

      <HCard
        v-if="shown('ambulances') && dash.ambulances"
        title="Ambulance board"
        class="h-dash-md"
      >
        <template #actions>
          <HButton
            variant="ghost"
            size="sm"
            to="ambulances"
          >
            Fleet
          </HButton>
        </template>
        <div class="h-dash-metrics">
          <p class="h-metric">
            <span>Available</span>
            <strong>{{ dash.ambulances.available }}</strong>
          </p>
          <p class="h-metric">
            <span>On trip</span>
            <strong>{{ dash.ambulances.on_trip }}</strong>
          </p>
        </div>
        <HTable
          :headers="[
            { title: 'Vehicle', key: 'vehicle' },
            { title: 'Patient', key: 'name' },
            { title: 'Status', key: 'status' },
          ]"
          :items="dash.ambulances.trips"
          empty="No active trips"
        >
          <template #cell-vehicle="{ item }">
            <RouterLink
              v-if="item.to"
              class="h-inline-link"
              :to="item.to"
            >
              {{ item.vehicle || 'Trip' }}
            </RouterLink>
            <span v-else>{{ item.vehicle || '—' }}</span>
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="shown('billing') && dash.billing"
        title="Billing"
        class="h-dash-md"
      >
        <template #actions>
          <HButton
            variant="ghost"
            size="sm"
            to="billing"
          >
            Invoices
          </HButton>
        </template>
        <div class="h-dash-metrics">
          <p class="h-metric">
            <span>Outstanding</span>
            <strong>{{ money(dash.billing.outstanding) }}</strong>
          </p>
          <p class="h-metric">
            <span>Collected today</span>
            <strong>{{ money(dash.billing.collected) }}</strong>
          </p>
          <p class="h-metric">
            <span>Open bills</span>
            <strong>{{ dash.billing.draft + dash.billing.issued }}</strong>
          </p>
        </div>
      </HCard>

      <HCard
        v-if="shown('mine')"
        title="Assigned to me"
        flush
        class="h-dash-lg"
      >
        <HTable
          :headers="[
            { title: 'Patient', key: 'name' },
            { title: 'Type', key: 'type' },
            { title: 'Status', key: 'status' },
            { title: 'When', key: 'at' },
          ]"
          :items="dash.mine"
          empty="No open encounters are assigned to you"
        >
          <template #cell-name="{ item }">
            <RouterLink
              v-if="item.to"
              class="h-inline-link"
              :to="item.to"
            >
              {{ item.name }}
            </RouterLink>
            <span v-else>{{ item.name }}</span>
            <small class="h-muted"> {{ item.mrn }}</small>
          </template>
          <template #cell-type="{ item }">
            {{ labelize(item.type) }}
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
          <template #cell-at="{ item }">
            {{ when(item.at) }}
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="shown('encounters')"
        title="Recent encounters"
        flush
        class="h-dash-lg"
      >
        <template #actions>
          <HButton
            variant="ghost"
            size="sm"
            :to="dash.workspace && dash.workspace !== 'admin' ? dash.workspace : 'reception'"
          >
            Open board
          </HButton>
        </template>
        <HTable
          :headers="[
            { title: 'Patient', key: 'name' },
            { title: 'Type', key: 'type' },
            { title: 'Status', key: 'status' },
            { title: 'When', key: 'at' },
          ]"
          :items="dash.encounters"
          empty="No encounters recorded yet"
        >
          <template #cell-name="{ item }">
            <RouterLink
              v-if="item.to"
              class="h-inline-link"
              :to="item.to"
            >
              {{ item.name }}
            </RouterLink>
            <span v-else>{{ item.name }}</span>
            <small class="h-muted"> {{ item.mrn }}</small>
          </template>
          <template #cell-type="{ item }">
            {{ labelize(item.type) }}
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
          <template #cell-at="{ item }">
            {{ when(item.at) }}
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="shown('patients')"
        title="Recent patients"
        flush
        class="h-dash-lg"
      >
        <template #actions>
          <HButton
            variant="ghost"
            size="sm"
            to="patients"
          >
            Registry
          </HButton>
        </template>
        <HTable
          :headers="[
            { title: 'Patient', key: 'name' },
            { title: 'MRN', key: 'mrn' },
            { title: 'Status', key: 'status' },
            { title: 'Updated', key: 'at' },
          ]"
          :items="dash.patients"
          empty="No patients in this hospital yet"
        >
          <template #cell-name="{ item }">
            <RouterLink
              class="h-inline-link"
              :to="item.to"
            >
              {{ item.name }}
            </RouterLink>
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
          <template #cell-at="{ item }">
            {{ when(item.at) }}
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="shown('admissions')"
        title="Admissions"
        flush
        class="h-dash-md"
      >
        <HTable
          :headers="[
            { title: 'Patient', key: 'name' },
            { title: 'Status', key: 'status' },
            { title: 'When', key: 'at' },
          ]"
          :items="dash.admissions"
          empty="No recent admissions"
        >
          <template #cell-name="{ item }">
            <RouterLink
              v-if="item.to"
              class="h-inline-link"
              :to="item.to"
            >
              {{ item.name }}
            </RouterLink>
            <span v-else>{{ item.name }}</span>
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
          <template #cell-at="{ item }">
            {{ when(item.at) }}
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="shown('admissions')"
        title="Discharges"
        flush
        class="h-dash-md"
      >
        <HTable
          :headers="[
            { title: 'Patient', key: 'name' },
            { title: 'Status', key: 'status' },
            { title: 'When', key: 'at' },
          ]"
          :items="dash.discharges"
          empty="No recent discharges"
        >
          <template #cell-name="{ item }">
            <RouterLink
              v-if="item.to"
              class="h-inline-link"
              :to="item.to"
            >
              {{ item.name }}
            </RouterLink>
            <span v-else>{{ item.name }}</span>
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
          <template #cell-at="{ item }">
            {{ when(item.at) }}
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="shown('admissions')"
        title="Transfers"
        flush
        class="h-dash-md"
      >
        <HTable
          :headers="[
            { title: 'Patient', key: 'name' },
            { title: 'Type', key: 'type' },
            { title: 'Status', key: 'status' },
          ]"
          :items="dash.transfers"
          empty="No transfers in progress"
        >
          <template #cell-name="{ item }">
            <RouterLink
              v-if="item.to"
              class="h-inline-link"
              :to="item.to"
            >
              {{ item.name }}
            </RouterLink>
            <span v-else>{{ item.name }}</span>
          </template>
          <template #cell-type="{ item }">
            {{ labelize(item.type) }}
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="shown('emergency')"
        title="Emergency activity"
        flush
        class="h-dash-lg"
      >
        <template #actions>
          <HButton
            variant="ghost"
            size="sm"
            to="emergency"
          >
            Board
          </HButton>
        </template>
        <HTable
          :headers="[
            { title: 'Patient', key: 'name' },
            { title: 'Status', key: 'status' },
            { title: 'When', key: 'at' },
          ]"
          :items="dash.emergency"
          empty="No active emergency visits"
        >
          <template #cell-name="{ item }">
            <RouterLink
              v-if="item.to"
              class="h-inline-link"
              :to="item.to"
            >
              {{ item.name }}
            </RouterLink>
            <span v-else>{{ item.name }}</span>
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
          <template #cell-at="{ item }">
            {{ when(item.at) }}
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="shown('laboratory')"
        title="Laboratory queue"
        flush
        class="h-dash-lg"
      >
        <template #actions>
          <HButton
            variant="ghost"
            size="sm"
            to="laboratory"
          >
            Laboratory
          </HButton>
        </template>
        <HTable
          :headers="[
            { title: 'Patient', key: 'name' },
            { title: 'Test', key: 'item' },
            { title: 'Status', key: 'status' },
          ]"
          :items="dash.laboratory"
          empty="No laboratory orders are waiting"
        >
          <template #cell-name="{ item }">
            <RouterLink
              v-if="item.to"
              class="h-inline-link"
              :to="item.to"
            >
              {{ item.name }}
            </RouterLink>
            <span v-else>{{ item.name }}</span>
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="shown('pharmacy')"
        title="Pharmacy queue"
        flush
        class="h-dash-lg"
      >
        <template #actions>
          <HButton
            variant="ghost"
            size="sm"
            to="pharmacy"
          >
            Pharmacy
          </HButton>
        </template>
        <HTable
          :headers="[
            { title: 'Patient', key: 'name' },
            { title: 'Status', key: 'status' },
            { title: 'When', key: 'at' },
          ]"
          :items="dash.pharmacy"
          empty="No prescriptions are waiting"
        >
          <template #cell-name="{ item }">
            <RouterLink
              v-if="item.to"
              class="h-inline-link"
              :to="item.to"
            >
              {{ item.name }}
            </RouterLink>
            <span v-else>{{ item.name }}</span>
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
          <template #cell-at="{ item }">
            {{ when(item.at) }}
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="shown('imaging')"
        title="Imaging queue"
        flush
        class="h-dash-lg"
      >
        <template #actions>
          <HButton
            variant="ghost"
            size="sm"
            to="imaging"
          >
            Imaging
          </HButton>
        </template>
        <HTable
          :headers="[
            { title: 'Patient', key: 'name' },
            { title: 'Study', key: 'item' },
            { title: 'Status', key: 'status' },
          ]"
          :items="dash.imaging"
          empty="No imaging studies are waiting"
        >
          <template #cell-name="{ item }">
            <RouterLink
              v-if="item.to"
              class="h-inline-link"
              :to="item.to"
            >
              {{ item.name }}
            </RouterLink>
            <span v-else>{{ item.name }}</span>
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="shown('theatre')"
        title="Theatre board"
        flush
        class="h-dash-lg"
      >
        <template #actions>
          <HButton
            variant="ghost"
            size="sm"
            to="theatre"
          >
            Theatre
          </HButton>
        </template>
        <HTable
          :headers="[
            { title: 'Patient', key: 'name' },
            { title: 'Case', key: 'item' },
            { title: 'Status', key: 'status' },
          ]"
          :items="dash.theatre"
          empty="No theatre cases are pending"
        >
          <template #cell-name="{ item }">
            <RouterLink
              v-if="item.to"
              class="h-inline-link"
              :to="item.to"
            >
              {{ item.name }}
            </RouterLink>
            <span v-else>{{ item.name }}</span>
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="shown('referrals')"
        title="Referral activity"
        flush
        class="h-dash-lg"
      >
        <template #actions>
          <HButton
            variant="ghost"
            size="sm"
            to="referrals"
          >
            Referrals
          </HButton>
        </template>
        <HTable
          :headers="[
            { title: 'Patient', key: 'name' },
            { title: 'From', key: 'from' },
            { title: 'To', key: 'to_hospital' },
            { title: 'Status', key: 'status' },
          ]"
          :items="dash.referrals"
          empty="No pending or in-transit referrals"
        >
          <template #cell-name="{ item }">
            <RouterLink
              class="h-inline-link"
              :to="item.to"
            >
              {{ item.name }}
            </RouterLink>
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="shown('assistance')"
        title="Assistance requests"
        flush
        class="h-dash-lg"
      >
        <template #actions>
          <HButton
            variant="ghost"
            size="sm"
            to="assistance"
          >
            Requests
          </HButton>
        </template>
        <HTable
          :headers="[
            { title: 'Request', key: 'title' },
            { title: 'Type', key: 'type' },
            { title: 'Status', key: 'status' },
          ]"
          :items="dash.assistance"
          empty="No open assistance requests"
        >
          <template #cell-title="{ item }">
            <RouterLink
              class="h-inline-link"
              :to="item.to"
            >
              {{ item.title }}
            </RouterLink>
          </template>
          <template #cell-type="{ item }">
            {{ labelize(item.type) }}
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="shown('billing') && dash.billing"
        title="Open invoices"
        flush
        class="h-dash-lg"
      >
        <HTable
          :headers="[
            { title: 'Invoice', key: 'number' },
            { title: 'Patient', key: 'name' },
            { title: 'Status', key: 'status' },
            { title: 'Total', key: 'total' },
          ]"
          :items="dash.billing.invoices"
          empty="No draft or issued invoices"
        >
          <template #cell-number="{ item }">
            <RouterLink
              class="h-inline-link"
              :to="item.to"
            >
              {{ item.number }}
            </RouterLink>
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
          <template #cell-total="{ item }">
            {{ money(item.total) }}
          </template>
        </HTable>
      </HCard>

      <HCard
        v-if="shown('activity')"
        title="Recent system activity"
        flush
        class="h-dash-full"
      >
        <HTable
          :headers="[
            { title: 'Action', key: 'action' },
            { title: 'Record', key: 'entity' },
            { title: 'Hospital', key: 'hospital' },
            { title: 'When', key: 'at' },
          ]"
          :items="dash.activity"
          empty="No recent system activity"
        >
          <template #cell-action="{ item }">
            {{ labelize(item.action) }}
          </template>
          <template #cell-at="{ item }">
            {{ when(item.at) }}
          </template>
        </HTable>
      </HCard>
    </div>

    <HGrid
      v-if="adminCards.length"
      cols="3"
      kind="links"
    >
      <HLinkCard
        v-for="card in adminCards"
        :key="card.to"
        :title="card.title"
        :text="card.text"
        :icon="card.icon"
        :to="card.to"
      />
    </HGrid>
    </template>
  </div>
</template>
