<script setup>
defineProps({
  label: { type: String, default: 'Loading' },
})

const show = useDelayedVisible(true)
</script>

<template>
  <HLoading
    class="is-page"
    :class="{ 'is-hold': !show }"
    :label="label"
  />
</template>
