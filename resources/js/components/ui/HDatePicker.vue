<script setup>
import { usePopover } from '@/composables/usePopover'
import { errorText, useFieldId } from '@/utils/formOptions'

const props = defineProps({
  modelValue: { default: '' },
  label: String,
  hint: String,
  description: String,
  error: [String, Array],
  placeholder: { type: String, default: 'Select date' },
  required: Boolean,
  optional: Boolean,
  disabled: Boolean,
  min: String,
  max: String,
})

const emit = defineEmits(['update:modelValue'])
const id = useFieldId('hd')
const { open, triggerRef, panelRef, coords, toggle, close } = usePopover()
const cursor = ref(new Date())

const parse = value => {
  if (!value)
    return null
  const date = new Date(`${value}T00:00:00`)
  return Number.isNaN(date.getTime()) ? null : date
}

const selected = computed(() => parse(props.modelValue))

watch(() => props.modelValue, value => {
  const date = parse(value)
  if (date)
    cursor.value = new Date(date.getFullYear(), date.getMonth(), 1)
}, { immediate: true })

const year = computed(() => cursor.value.getFullYear())
const month = computed(() => cursor.value.getMonth())
const monthLabel = computed(() => cursor.value.toLocaleString(undefined, { month: 'long', year: 'numeric' }))

const cells = computed(() => {
  const first = new Date(year.value, month.value, 1)
  const start = first.getDay()
  const days = new Date(year.value, month.value + 1, 0).getDate()
  const items = []
  for (let i = 0; i < start; i += 1)
    items.push(null)
  for (let day = 1; day <= days; day += 1)
    items.push(new Date(year.value, month.value, day))

  return items
})

const format = date => {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')

  return `${y}-${m}-${d}`
}

const display = computed(() => {
  if (!selected.value)
    return ''

  return selected.value.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
})

const isSame = date => selected.value && date && selected.value.toDateString() === date.toDateString()
const isToday = date => date && date.toDateString() === new Date().toDateString()

const blocked = date => {
  if (!date)
    return true
  const stamp = format(date)
  if (props.min && stamp < props.min)
    return true
  if (props.max && stamp > props.max)
    return true

  return false
}

const choose = date => {
  if (blocked(date))
    return
  emit('update:modelValue', format(date))
  close()
}

const shift = delta => {
  cursor.value = new Date(year.value, month.value + delta, 1)
}

const clear = event => {
  event.stopPropagation()
  emit('update:modelValue', null)
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
  >
    <div
      :id="id"
      ref="triggerRef"
      class="h-control h-select-trigger"
      role="combobox"
      tabindex="0"
      :aria-disabled="disabled"
      :aria-invalid="Boolean(errorText(error))"
      :aria-expanded="open"
      @click="!disabled && toggle()"
      @keydown.enter.prevent="toggle"
      @keydown.space.prevent="toggle"
    >
      <HIcon name="calendar" />
      <span :class="{ 'is-placeholder': !display }">
        {{ display || placeholder }}
      </span>
      <button
        v-if="modelValue"
        class="h-control-btn"
        type="button"
        aria-label="Clear"
        @click="clear"
      >
        <HIcon name="x" />
      </button>
    </div>
    <Teleport to="body">
      <div
        v-if="open"
        ref="panelRef"
        class="h-popover h-calendar"
        :style="{ top: `${coords.top}px`, left: `${coords.left}px`, width: `${Math.max(coords.width, 280)}px` }"
      >
        <div class="h-cal-head">
          <button
            class="h-control-btn"
            type="button"
            aria-label="Previous month"
            @click="shift(-1)"
          >
            <HIcon name="chevronLeft" />
          </button>
          <strong>{{ monthLabel }}</strong>
          <button
            class="h-control-btn"
            type="button"
            aria-label="Next month"
            @click="shift(1)"
          >
            <HIcon name="chevronRight" />
          </button>
        </div>
        <div class="h-cal-grid h-cal-week">
          <span
            v-for="day in ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']"
            :key="day"
          >{{ day }}</span>
        </div>
        <div class="h-cal-grid">
          <button
            v-for="(date, index) in cells"
            :key="index"
            type="button"
            class="h-cal-day"
            :class="{ 'is-on': isSame(date), 'is-today': isToday(date) }"
            :disabled="!date || blocked(date)"
            @click="choose(date)"
          >
            {{ date ? date.getDate() : '' }}
          </button>
        </div>
      </div>
    </Teleport>
  </HField>
</template>
