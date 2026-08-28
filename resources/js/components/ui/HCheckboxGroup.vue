<script setup>
import { normalizeOptions, sameValue } from '@/utils/formOptions'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  items: { type: Array, default: () => [] },
  itemTitle: { type: String, default: 'title' },
  itemValue: { type: String, default: 'value' },
  label: String,
  hint: String,
  description: String,
  error: [String, Array],
  disabled: Boolean,
  required: Boolean,
  selectAll: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue'])
const options = computed(() => normalizeOptions(props.items, props.itemTitle, props.itemValue))
const selected = computed(() => props.modelValue || [])
const allOn = computed(() => options.value.length > 0 && options.value.every(item => selected.value.some(value => sameValue(value, item.value))))

const setSelected = values => emit('update:modelValue', values)

const toggleAll = () => {
  if (allOn.value)
    setSelected(selected.value.filter(value => !options.value.some(item => sameValue(item.value, value))))
  else
    setSelected([...new Set([...selected.value, ...options.value.map(item => item.value)])])
}
</script>

<template>
  <HField
    :label="label"
    :hint="hint"
    :description="description"
    :error="error"
    :required="required"
    :disabled="disabled"
  >
    <div class="h-check-group">
      <button
        v-if="selectAll && options.length > 1"
        class="h-list-action"
        type="button"
        :disabled="disabled"
        @click="toggleAll"
      >
        {{ allOn ? 'Clear group' : 'Select all' }}
      </button>
      <HCheckbox
        v-for="option in options"
        :key="String(option.value)"
        :model-value="selected"
        :value="option.value"
        :label="option.title"
        :disabled="disabled"
        @update:model-value="setSelected"
      />
    </div>
  </HField>
</template>
