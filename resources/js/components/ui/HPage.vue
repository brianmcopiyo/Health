<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: String,
})
</script>

<template>
  <div class="hms-page-title">
    <div>
      <h1>{{ title }}</h1>
      <p v-if="subtitle">
        {{ subtitle }}
      </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <slot />
    </div>
  </div>
</template>
