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
      class="h-loader"
      aria-hidden="true"
    >
      <span />
      <span />
      <span />
    </div>
    <span class="h-sr">{{ label }}</span>
  </div>
</template>
