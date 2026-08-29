<script setup>
const props = defineProps({
  items: { type: Array, default: () => [] },
  empty: { type: String, default: 'No activity in this period' },
})

const peak = computed(() => Math.max(1, ...props.items.map(item => Number(item.value) || 0)))

const points = computed(() => {
  if (!props.items.length)
    return ''

  return props.items.map((item, index) => {
    const x = props.items.length === 1 ? 50 : (index / (props.items.length - 1)) * 100
    const y = 92 - ((Number(item.value) || 0) / peak.value) * 80
    return `${x},${y}`
  }).join(' ')
})

const area = computed(() => {
  if (!props.items.length)
    return ''
  const first = props.items.length === 1 ? '50' : '0'
  const last = props.items.length === 1 ? '50' : '100'
  return `${first},92 ${points.value} ${last},92`
})

const ticks = computed(() => {
  if (props.items.length <= 6)
    return props.items.map((item, index) => ({ ...item, index }))

  return [0, Math.floor((props.items.length - 1) / 2), props.items.length - 1].map(index => ({
    ...props.items[index],
    index,
  }))
})
</script>

<template>
  <div
    v-if="items.length"
    class="h-trend"
  >
    <svg
      viewBox="0 0 100 100"
      preserveAspectRatio="none"
      class="h-trend-plot"
      aria-hidden="true"
    >
      <polygon
        :points="area"
        class="h-trend-fill"
      />
      <polyline
        :points="points"
        class="h-trend-line"
      />
    </svg>
    <div class="h-trend-axis">
      <span
        v-for="tick in ticks"
        :key="tick.date || tick.label"
      >{{ tick.label }}</span>
    </div>
  </div>
  <HEmpty
    v-else
    :message="empty"
  />
</template>
