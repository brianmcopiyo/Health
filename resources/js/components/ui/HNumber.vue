<script setup>
import { errorText, fieldPlaceholder, useFieldId } from '@/utils/formOptions'

const props = defineProps({
  modelValue: { default: null },
  label: String,
  hint: String,
  description: String,
  error: [String, Array],
  placeholder: String,
  required: Boolean,
  optional: Boolean,
  disabled: Boolean,
  loading: Boolean,
  min: { type: [Number, String], default: null },
  max: { type: [Number, String], default: null },
  step: { type: [Number, String], default: 1 },
  span: Boolean,
})

const emit = defineEmits(['update:modelValue'])
const id = useFieldId('hn')
const message = computed(() => errorText(props.error))
const stepNum = computed(() => Number(props.step) || 1)

const parse = value => {
  if (value === '' || value === null || value === undefined)
    return null
  const next = Number(value)
  return Number.isNaN(next) ? null : next
}

const clamp = value => {
  if (value === null)
    return null
  let next = value
  if (props.min !== null && props.min !== undefined && next < Number(props.min))
    next = Number(props.min)
  if (props.max !== null && props.max !== undefined && next > Number(props.max))
    next = Number(props.max)

  return next
}

const display = computed(() => (props.modelValue === null || props.modelValue === undefined ? '' : props.modelValue))
const resolvedPlaceholder = computed(() => fieldPlaceholder(props.placeholder, props.label, 'number'))

const setValue = value => emit('update:modelValue', clamp(parse(value)))

const nudge = direction => {
  if (props.disabled || props.loading)
    return
  const current = parse(props.modelValue) ?? 0
  const next = current + direction * stepNum.value
  const decimals = (String(stepNum.value).split('.')[1] || '').length
  setValue(Number(next.toFixed(decimals)))
}
</script>

<template>
  <HField
    :label="label"
    :hint="hint"
    :description="description"
    :error="error"
    :required="required"
    :optional="optional"
    :html-for="id"
    :disabled="disabled"
    :span="span"
  >
    <div
      class="h-control is-number"
      :class="{ 'is-loading': loading }"
    >
      <button
        class="h-control-btn"
        type="button"
        :disabled="disabled || loading"
        aria-label="Decrease"
        @click="nudge(-1)"
      >
        <HIcon name="minus" />
      </button>
      <input
        :id="id"
        :value="display"
        type="text"
        inputmode="decimal"
        :placeholder="resolvedPlaceholder"
        :disabled="disabled || loading"
        :required="required"
        :aria-invalid="Boolean(message)"
        @input="setValue($event.target.value)"
        @blur="setValue(modelValue)"
      >
      <button
        class="h-control-btn"
        type="button"
        :disabled="disabled || loading"
        aria-label="Increase"
        @click="nudge(1)"
      >
        <HIcon name="plus" />
      </button>
    </div>
  </HField>
</template>
