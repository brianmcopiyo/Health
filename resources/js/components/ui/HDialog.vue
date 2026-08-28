<script setup>
defineProps({
  modelValue: Boolean,
  title: String,
  error: String,
  wide: Boolean,
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <HModal
    :model-value="modelValue"
    :title="title"
    :error="error"
    :size="wide ? 'lg' : 'md'"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <slot />
    <template
      v-if="$slots.actions"
      #actions
    >
      <slot name="actions" />
    </template>
  </HModal>
</template>
