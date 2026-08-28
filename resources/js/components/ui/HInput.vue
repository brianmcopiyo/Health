<script setup>
import { errorText, useFieldId } from '@/utils/formOptions'

const props = defineProps({
  modelValue: { default: '' },
  label: String,
  hint: String,
  description: String,
  error: [String, Array],
  type: { type: String, default: 'text' },
  placeholder: String,
  required: Boolean,
  optional: Boolean,
  disabled: Boolean,
  loading: Boolean,
  clearable: Boolean,
  icon: String,
  autocomplete: String,
  maxlength: [Number, String],
  name: String,
})

const emit = defineEmits(['update:modelValue'])
const id = useFieldId('hi')
const showPassword = ref(false)
const message = computed(() => errorText(props.error))

const inputType = computed(() => {
  if (props.type === 'password')
    return showPassword.value ? 'text' : 'password'

  return props.type === 'number' ? 'text' : props.type
})

const describedBy = computed(() => {
  const ids = []
  if (props.description || props.hint)
    ids.push(`${id}-hint`)
  if (message.value)
    ids.push(`${id}-error`)

  return ids.join(' ') || undefined
})

const display = computed(() => props.modelValue ?? '')

const onInput = event => {
  const value = event.target.value
  if (props.type === 'number') {
    emit('update:modelValue', value === '' ? null : Number(value))
    return
  }
  emit('update:modelValue', value)
}

const clear = () => emit('update:modelValue', props.type === 'number' ? null : '')
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
  >
    <div
      class="h-control"
      :class="{ 'is-loading': loading }"
    >
      <HIcon
        v-if="icon"
        :name="icon"
        :size="16"
      />
      <input
        :id="id"
        :value="display"
        :type="inputType"
        :placeholder="placeholder"
        :disabled="disabled || loading"
        :required="required"
        :autocomplete="autocomplete"
        :maxlength="maxlength"
        :name="name"
        :inputmode="type === 'number' ? 'decimal' : undefined"
        :aria-invalid="Boolean(message)"
        :aria-describedby="describedBy"
        @input="onInput"
      >
      <button
        v-if="type === 'password'"
        class="h-control-btn"
        type="button"
        :aria-label="showPassword ? 'Hide password' : 'Show password'"
        @click="showPassword = !showPassword"
      >
        <HIcon :name="showPassword ? 'eyeOff' : 'eye'" />
      </button>
      <button
        v-else-if="clearable && display !== '' && display !== null && !disabled"
        class="h-control-btn"
        type="button"
        aria-label="Clear"
        @click="clear"
      >
        <HIcon name="x" />
      </button>
      <span
        v-if="loading"
        class="h-spin"
        aria-hidden="true"
      />
    </div>
  </HField>
</template>
