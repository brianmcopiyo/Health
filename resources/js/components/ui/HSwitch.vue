<script setup>
import { useFieldId } from '@/utils/formOptions'

defineProps({
  modelValue: { type: Boolean, default: false },
  label: String,
  hint: String,
  description: String,
  error: [String, Array],
  disabled: Boolean,
})

const emit = defineEmits(['update:modelValue'])
const id = useFieldId('hw')
</script>

<template>
  <HField
    :hint="hint"
    :description="description"
    :error="error"
    :disabled="disabled"
  >
    <label
      class="h-switch"
      :class="{ 'is-on': modelValue, 'is-disabled': disabled }"
    >
      <input
        :id="id"
        class="h-sr"
        type="checkbox"
        role="switch"
        :checked="modelValue"
        :disabled="disabled"
        :aria-checked="modelValue"
        @change="emit('update:modelValue', $event.target.checked)"
      >
      <span class="h-switch-track">
        <span class="h-switch-thumb" />
      </span>
      <span v-if="label">{{ label }}</span>
    </label>
  </HField>
</template>
