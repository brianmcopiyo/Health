<script setup>
defineProps({
  lines: { type: Number, default: 1 },
  width: String,
  height: String,
  circle: Boolean,
  kind: { type: String, default: 'line' },
})
</script>

<template>
  <div
    class="h-skeleton-stack"
    :class="`is-${kind}`"
  >
    <span
      v-for="n in lines"
      :key="n"
      class="h-skeleton"
      :class="[`is-${kind}`, { 'is-circle': circle }]"
      :style="{ width: width || undefined, height: height || undefined }"
    />
  </div>
</template>
