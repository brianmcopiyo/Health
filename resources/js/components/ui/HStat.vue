<script setup>
const props = defineProps({
  title: String,
  value: [String, Number],
  icon: { type: String, default: 'chart' },
  hint: String,
  tone: String,
  trend: [Number, String],
  to: [String, Object],
  loading: Boolean,
})

const showSkel = useDelayedVisible(() => props.loading)
</script>

<template>
  <component
    :is="to ? 'RouterLink' : 'section'"
    class="h-card h-stat"
    :class="[tone ? `is-${tone}` : null, { 'is-loading': loading, 'is-hold': loading && !showSkel }]"
    :to="to ? (typeof to === 'string' ? { name: to } : to) : undefined"
  >
    <div
      class="h-stat-icon"
      aria-hidden="true"
    >
      <HIcon
        :name="icon"
        :size="18"
      />
    </div>
    <div class="h-stat-body">
      <span class="h-stat-label">
        <span
          v-if="(loading || showSkel) && !title"
          class="h-skeleton is-label"
        />
        <template v-else>{{ title }}</template>
      </span>
      <strong class="h-stat-value">
        <span
          v-if="loading || showSkel"
          class="h-skeleton is-value"
        />
        <template v-else>{{ value }}</template>
      </strong>
      <small
        v-if="loading || showSkel || (props.hint && !props.loading)"
        class="h-stat-hint"
      >
        <span
          v-if="loading || showSkel"
          class="h-skeleton is-hint"
        />
        <template v-else>{{ props.hint }}</template>
      </small>
      <small
        v-if="!loading && !showSkel && trend !== null && trend !== undefined && trend !== ''"
        class="h-stat-trend"
        :class="Number(trend) > 0 ? 'is-up' : Number(trend) < 0 ? 'is-down' : ''"
      >{{ Number(trend) > 0 ? '+' : '' }}{{ trend }}% vs yesterday</small>
    </div>
  </component>
</template>
