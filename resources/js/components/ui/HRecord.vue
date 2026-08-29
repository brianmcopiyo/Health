<script setup>
import { statusColor, labelize } from '@/utils/status'

const props = defineProps({
  title: String,
  subtitle: String,
  status: String,
  statuses: { type: Array, default: () => [] },
  back: [String, Object],
  backLabel: { type: String, default: 'Back' },
  tabs: { type: Array, default: () => [] },
  tab: String,
  loading: Boolean,
  missing: Boolean,
  missingMessage: { type: String, default: 'This record could not be loaded.' },
})

const emit = defineEmits(['update:tab'])
const showSkel = useDelayedVisible(() => props.loading)
const fieldRows = [1, 2, 3, 4]
const skelHeaders = [
  { title: 'Name', key: 'a' },
  { title: 'Status', key: 'b' },
  { title: 'When', key: 'c' },
]

const statusItems = computed(() => {
  const items = []
  if (props.status)
    items.push({ value: props.status, label: labelize(props.status) })
  for (const item of props.statuses) {
    if (!item)
      continue
    if (typeof item === 'string') {
      items.push({ value: item, label: labelize(item) })
      continue
    }
    items.push({
      value: item.value,
      label: item.label || labelize(item.value),
    })
  }
  return items
})
</script>

<template>
  <div class="h-record">
    <div class="h-record-head">
      <HButton
        v-if="back"
        class="h-record-back"
        variant="ghost"
        :to="back"
      >
        <HIcon name="back" />
        {{ backLabel }}
      </HButton>
      <div class="hms-page-copy">
        <h1>{{ title || 'Record' }}</h1>
        <p v-if="subtitle">
          {{ subtitle }}
        </p>
      </div>
    </div>

    <div
      v-if="(loading || showSkel) && !missing"
      class="h-record-loading"
      :class="{ 'is-hold': !showSkel }"
    >
      <div class="h-record-status">
        <span class="h-skeleton is-badge" />
      </div>
      <nav
        v-if="tabs.length"
        class="h-record-tabs"
      >
        <span
          v-for="item in tabs"
          :key="item.value"
          class="h-skeleton is-tab"
        />
      </nav>
      <div class="h-detail">
        <HCard>
          <div
            v-for="row in fieldRows"
            :key="row"
            class="h-metric"
          >
            <span class="h-skeleton is-label" />
            <strong class="h-skeleton is-value" />
          </div>
        </HCard>
        <HCard flush>
          <div class="h-table-wrap">
            <table class="h-table">
              <thead>
                <tr>
                  <th
                    v-for="header in skelHeaders"
                    :key="header.key"
                  >
                    {{ header.title }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="n in 5"
                  :key="n"
                  class="h-skel-row"
                >
                  <td
                    v-for="header in skelHeaders"
                    :key="header.key"
                  >
                    <span class="h-skeleton is-cell" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </HCard>
      </div>
    </div>

    <div
      v-else-if="missing"
      class="h-alert"
    >
      {{ missingMessage }}
    </div>

    <template v-else>
      <div
        v-if="statusItems.length"
        class="h-record-status"
      >
        <HBadge
          v-for="item in statusItems"
          :key="item.value"
          :tone="statusColor(item.value)"
        >
          {{ item.label }}
        </HBadge>
      </div>
      <nav
        v-if="tabs.length"
        class="h-record-tabs"
      >
        <button
          v-for="item in tabs"
          :key="item.value"
          type="button"
          :class="{ 'is-on': tab === item.value }"
          @click="emit('update:tab', item.value)"
        >
          {{ item.title }}
        </button>
      </nav>
      <HTransition name="h-tab">
        <div
          :key="tab || 'main'"
          class="h-record-pane"
        >
          <slot />
        </div>
      </HTransition>
    </template>
  </div>
</template>
