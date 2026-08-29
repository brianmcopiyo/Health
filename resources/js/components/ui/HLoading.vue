<script setup>
defineProps({
  label: { type: String, default: 'Loading' },
})
</script>

<template>
  <div
    class="h-loading"
    role="status"
  >
    <div
      class="h-spinner"
      aria-hidden="true"
    />
    <span class="h-sr">{{ label }}</span>
  </div>
</template>
