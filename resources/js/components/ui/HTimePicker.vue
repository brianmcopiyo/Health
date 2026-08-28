<script setup>
import { usePopover } from '@/composables/usePopover'
import { errorText, useFieldId } from '@/utils/formOptions'

const props = defineProps({
  modelValue: { default: '' },
  label: String,
  hint: String,
  description: String,
  error: [String, Array],
  placeholder: { type: String, default: 'Select time' },
  required: Boolean,
  optional: Boolean,
  disabled: Boolean,
  minuteStep: { type: Number, default: 5 },
})

const emit = defineEmits(['update:modelValue'])
const id = useFieldId('hm')
const { open, triggerRef, panelRef, coords, toggle, close } = usePopover()

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
        {{ display || placeholder }}
      </span>
    </button>
    <Teleport to="body">
      <div
        v-if="open"
        ref="panelRef"
        class="h-popover h-time"
        :style="{ top: `${coords.top}px`, left: `${coords.left}px`, width: `${Math.max(coords.width, 220)}px` }"
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
      </div>
    </Teleport>
  </HField>
</template>
