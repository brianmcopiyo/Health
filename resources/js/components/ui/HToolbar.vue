<script setup>
defineProps({
  stacked: Boolean,
})
</script>

<template>
  <div
    class="h-toolbar"
    :class="{ 'is-stacked': stacked }"
  >
    <div class="h-toolbar-filters">
      <slot />
    </div>
    <div
      v-if="$slots.actions"
      class="h-toolbar-actions"
    >
      <slot name="actions" />
    </div>
  </div>
</template>
