<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: String,
})
</script>

<template>
  <div class="hms-page-title">
    <div class="hms-page-copy">
      <h1>{{ title }}</h1>
      <p v-if="subtitle">
        {{ subtitle }}
      </p>
    </div>
    <div
      v-if="$slots.default"
      class="hms-page-actions"
    >
      <slot />
    </div>
  </div>
</template>
