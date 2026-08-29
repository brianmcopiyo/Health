<script setup>
defineProps({
  title: String,
  value: [String, Number],
  icon: { type: String, default: 'chart' },
  hint: String,
  tone: String,
})
</script>

<template>
  <section
    class="h-card h-stat"
    :class="tone ? `is-${tone}` : null"
  >
    <div
      class="h-stat-icon"
      aria-hidden="true"
    >
      <HIcon
        :name="icon"
        :size="18"
      />
    </div>
    <div class="h-stat-body">
      <span class="h-stat-label">{{ title }}</span>
      <strong class="h-stat-value">{{ value }}</strong>
      <small
        v-if="hint"
        class="h-stat-hint"
      >{{ hint }}</small>
    </div>
  </section>
</template>
