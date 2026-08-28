<script setup>
defineProps({
  modelValue: { default: '' },
  label: String,
  type: { type: String, default: 'text' },
  placeholder: String,
  error: [String, Array],
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <label class="h-field">
    <span v-if="label">{{ label }}</span>
    <input
      :value="modelValue ?? ''"
      :type="type"
      :placeholder="placeholder"
      @input="emit('update:modelValue', type === 'number' ? Number($event.target.value) : $event.target.value)"
    >
    <span
      v-if="error"
      class="error"
    >{{ Array.isArray(error) ? error[0] : error }}</span>
  </label>
</template>
