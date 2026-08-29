<script setup>
const props = defineProps({
  items: { type: Array, default: () => [] },
  empty: { type: String, default: 'No activity in this period' },
})

const peak = computed(() => Math.max(1, ...props.items.map(item => Number(item.max ?? item.value) || 0)))

const width = item => {
  const max = Number(item.max) > 0 ? Number(item.max) : peak.value

  return `${Math.round((Number(item.value) / max) * 100)}%`
}
</script>

<template>
  <div
    v-if="items.length"
    class="h-bars"
  >
    <div
      v-for="item in items"
      :key="item.label"
      class="h-bars-row"
    >
      <span>{{ item.label }}</span>
      <div
        class="h-bars-track"
        aria-hidden="true"
      >
        <i :style="{ width: width(item) }" />
      </div>
      <strong>{{ item.value }}</strong>
    </div>
  </div>
  <HEmpty
    v-else
    :message="empty"
  />
</template>
