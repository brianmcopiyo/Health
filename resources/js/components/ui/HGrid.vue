<script setup>
defineProps({
  cols: { type: [Number, String], default: 3 },
  kind: { type: String, default: 'cards' },
})
</script>

<template>
  <div
    class="h-grid"
    :class="[kind === 'stats' ? null : `cols-${cols}`, `is-${kind}`]"
  >
    <slot />
  </div>
</template>
