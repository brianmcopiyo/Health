<script setup>
import { errorText, useFieldId } from '@/utils/formOptions'

const props = defineProps({
  modelValue: { type: [String, null], default: '' },
  label: String,
  hint: String,
  description: String,
  error: [String, Array],
  placeholder: String,
  required: Boolean,
  optional: Boolean,
  disabled: Boolean,
  loading: Boolean,
  rows: { type: [Number, String], default: 3 },
  maxlength: [Number, String],
})

const emit = defineEmits(['update:modelValue'])
const id = useFieldId('ht')
const message = computed(() => errorText(props.error))
const describedBy = computed(() => {
  const ids = []
  if (props.description || props.hint)
    ids.push(`${id}-hint`)
  if (message.value)
    ids.push(`${id}-error`)

  return ids.join(' ') || undefined
})
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
      class="h-control is-textarea"
      :class="{ 'is-loading': loading }"
    >
      <textarea
        :id="id"
        :value="modelValue ?? ''"
        :rows="rows"
        :placeholder="placeholder"
        :disabled="disabled || loading"
        :required="required"
        :maxlength="maxlength"
        :aria-invalid="Boolean(message)"
        :aria-describedby="describedBy"
        @input="emit('update:modelValue', $event.target.value)"
      />
    </div>
  </HField>
</template>
