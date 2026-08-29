<script setup>
defineProps({
  title: String,
  message: { type: String, default: 'Nothing to show yet' },
})
</script>

<template>
  <div class="h-empty">
    <p
      v-if="title"
      class="h-empty-title"
    >
      {{ title }}
    </p>
    <p>{{ message }}</p>
  </div>
</template>
