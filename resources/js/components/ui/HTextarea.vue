<script setup>
defineProps({
  modelValue: { type: String, default: '' },
  label: String,
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <label class="h-field">
    <span v-if="label">{{ label }}</span>
    <textarea
      :value="modelValue"
      @input="emit('update:modelValue', $event.target.value)"
    />
  </label>
</template>
