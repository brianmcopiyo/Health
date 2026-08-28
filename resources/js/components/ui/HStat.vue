<script setup>
defineProps({
  title: String,
  value: [String, Number],
})
</script>

<template>
  <HCard>
    <div class="h-stat">
      <span>{{ title }}</span>
      <strong>{{ value }}</strong>
    </div>
  </HCard>
</template>
