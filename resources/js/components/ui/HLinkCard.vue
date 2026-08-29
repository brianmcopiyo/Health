<script setup>
defineProps({
  title: { type: String, required: true },
  text: String,
  icon: { type: String, default: 'dashboard' },
  to: { type: [String, Object], required: true },
})
</script>

<template>
  <RouterLink
    :to="typeof to === 'string' ? { name: to } : to"
    class="h-card h-link-card"
  >
    <div class="h-icon-bubble">
      <HIcon :name="icon" />
    </div>
    <div class="h-link-copy">
      <strong>{{ title }}</strong>
      <p
        v-if="text"
        class="h-muted"
      >
        {{ text }}
      </p>
    </div>
  </RouterLink>
</template>
