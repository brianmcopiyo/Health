<script setup>
defineProps({
  delay: { type: [Number, String], default: 0 },
})
</script>

<template>
  <div
    class="h-reveal"
    :style="{ '--h-reveal-delay': `${delay}ms` }"
  >
    <slot />
  </div>
</template>
