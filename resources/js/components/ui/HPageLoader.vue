<script setup>
defineProps({
  label: { type: String, default: 'Loading' },
})
</script>

<template>
  <HLoading
    class="is-page"
    :label="label"
  />
</template>
