<script setup>
import { usePopover } from '@/composables/usePopover'
import { errorText, fieldPlaceholder, useFieldId } from '@/utils/formOptions'

const props = defineProps({
  modelValue: { default: '' },
  label: String,
  hint: String,
  description: String,
  error: [String, Array],
  placeholder: String,
  required: Boolean,
  optional: Boolean,
  disabled: Boolean,
  minuteStep: { type: Number, default: 5 },
  span: Boolean,
})

const emit = defineEmits(['update:modelValue'])
const id = useFieldId('hm')
const { open, triggerRef, coords, bindPanel, toggle, close } = usePopover({
  matchWidth: false,
  minWidth: 220,
})

const parts = computed(() => {
  const match = String(props.modelValue || '').match(/^(\d{1,2}):(\d{2})$/)
  if (!match)
    return { hour: 8, minute: 0 }

  return { hour: Number(match[1]), minute: Number(match[2]) }
})

const hours = Array.from({ length: 24 }, (_, i) => i)
const minutes = computed(() => Array.from({ length: Math.floor(60 / props.minuteStep) }, (_, i) => i * props.minuteStep))

const format = (hour, minute) => `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`

const choose = (hour, minute) => {
  emit('update:modelValue', format(hour, minute))
}

const display = computed(() => props.modelValue || '')
const resolvedPlaceholder = computed(() => fieldPlaceholder(props.placeholder, props.label, 'time'))
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
    <button
      :id="id"
      ref="triggerRef"
      class="h-control h-select-trigger"
      type="button"
      :disabled="disabled"
      :aria-invalid="Boolean(errorText(error))"
      :aria-expanded="open"
      @click="toggle"
    >
      <HIcon name="clock" />
      <span :class="{ 'is-placeholder': !display }">
        {{ display || resolvedPlaceholder }}
      </span>
    </button>
    <HPopover
      :show="open"
      :coords="coords"
      :bind-panel="bindPanel"
      panel-class="h-time"
    >
      <div class="h-time-cols">
        <div>
          <p>Hour</p>
          <button
            v-for="hour in hours"
            :key="hour"
            type="button"
            class="h-list-item"
            :class="{ 'is-on': parts.hour === hour }"
            @click="choose(hour, parts.minute)"
          >
            {{ String(hour).padStart(2, '0') }}
          </button>
        </div>
        <div>
          <p>Minute</p>
          <button
            v-for="minute in minutes"
            :key="minute"
            type="button"
            class="h-list-item"
            :class="{ 'is-on': parts.minute === minute }"
            @click="choose(parts.hour, minute); close()"
          >
            {{ String(minute).padStart(2, '0') }}
          </button>
        </div>
      </div>
    </HPopover>
  </HField>
</template>
