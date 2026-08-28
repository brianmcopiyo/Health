<script setup>
defineProps({
  tone: { type: String, default: 'secondary' },
})
</script>

<template>
  <span
    class="h-badge"
    :class="tone"
  >
    <slot />
  </span>
</template>
