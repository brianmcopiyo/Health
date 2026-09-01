<script setup>
defineProps({
  axis: {
    type: String,
    default: 'both',
    validator: value => ['both', 'x', 'y'].includes(value),
  },
  onDark: Boolean,
})
</script>

<template>
  <div
    class="h-scrollbar"
    :class="{
      'is-x': axis === 'x',
      'is-y': axis === 'y',
      'is-on-dark': onDark,
    }"
  >
    <slot />
  </div>
</template>
