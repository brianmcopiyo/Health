<script setup>
import { errorText, useFieldId } from '@/utils/formOptions'

const props = defineProps({
  modelValue: { default: false },
  value: { default: undefined },
  label: String,
  hint: String,
  description: String,
  error: [String, Array],
  disabled: Boolean,
  required: Boolean,
})

const emit = defineEmits(['update:modelValue'])
const id = useFieldId('hb')
const isGroup = computed(() => Array.isArray(props.modelValue))
const checked = computed(() => {
  if (isGroup.value)
    return props.modelValue.some(item => String(item) === String(props.value))

  return Boolean(props.modelValue)
})

const toggle = () => {
  if (props.disabled)
    return
  if (isGroup.value) {
    const next = checked.value
      ? props.modelValue.filter(item => String(item) !== String(props.value))
      : [...props.modelValue, props.value]
    emit('update:modelValue', next)
    return
  }
  emit('update:modelValue', !checked.value)
}
</script>

<template>
  <label
    class="h-check"
    :class="{ 'is-disabled': disabled, 'is-invalid': errorText(error) }"
  >
    <input
      :id="id"
      type="checkbox"
      class="h-sr"
      :checked="checked"
      :disabled="disabled"
      :required="required"
      @change="toggle"
    >
    <span
      class="h-check-box"
      :class="{ 'is-on': checked }"
      aria-hidden="true"
    >
      <HIcon
        v-if="checked"
        name="check"
        :size="12"
      />
    </span>
    <span class="h-check-copy">
      <strong v-if="label">{{ label }}</strong>
      <slot />
      <small v-if="description || hint">{{ description || hint }}</small>
      <small
        v-if="errorText(error)"
        class="error"
      >{{ errorText(error) }}</small>
    </span>
  </label>
</template>
