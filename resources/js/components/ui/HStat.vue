<script setup>
defineProps({
  title: String,
  value: [String, Number],
  icon: { type: String, default: 'chart' },
  hint: String,
  tone: String,
  trend: [Number, String],
  to: [String, Object],
})
</script>

<template>
  <component
    :is="to ? 'RouterLink' : 'section'"
    class="h-card h-stat"
    :class="tone ? `is-${tone}` : null"
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
      <span class="h-stat-label">{{ title }}</span>
      <strong class="h-stat-value">{{ value }}</strong>
      <small
        v-if="hint"
        class="h-stat-hint"
      >{{ hint }}</small>
      <small
        v-if="trend !== null && trend !== undefined && trend !== ''"
        class="h-stat-trend"
        :class="Number(trend) > 0 ? 'is-up' : Number(trend) < 0 ? 'is-down' : ''"
      >{{ Number(trend) > 0 ? '+' : '' }}{{ trend }}% vs yesterday</small>
    </div>
  </component>
</template>
