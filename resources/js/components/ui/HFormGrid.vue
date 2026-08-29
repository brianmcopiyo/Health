<script setup>
defineProps({
  cols: { type: [Number, String], default: 2 },
  tag: { type: String, default: 'div' },
  disabled: Boolean,
})
</script>

<template>
  <component
    :is="tag"
    class="h-form-grid"
    :class="{ 'is-1': Number(cols) === 1, 'is-3': Number(cols) === 3 }"
    :disabled="tag === 'fieldset' ? disabled : undefined"
  >
    <slot />
  </component>
</template>
