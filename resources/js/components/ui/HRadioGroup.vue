<script setup>
import { errorText, normalizeOptions, sameValue, useFieldId } from '@/utils/formOptions'

const props = defineProps({
  modelValue: { default: null },
  items: { type: Array, default: () => [] },
  itemTitle: { type: String, default: 'title' },
  itemValue: { type: String, default: 'value' },
  label: String,
  hint: String,
  description: String,
  error: [String, Array],
  required: Boolean,
  optional: Boolean,
  disabled: Boolean,
  inline: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue'])
const id = useFieldId('hr')
const options = computed(() => normalizeOptions(props.items, props.itemTitle, props.itemValue))
</script>

<template>
  <HField
    :label="label"
    :hint="hint"
    :description="description"
    :error="error"
    :required="required"
    :optional="optional"
    :disabled="disabled"
  >
    <div
      class="h-radio-group"
      :class="{ 'is-stack': !inline }"
      role="radiogroup"
      :aria-labelledby="label ? `${id}-label` : undefined"
      :aria-invalid="Boolean(errorText(error))"
    >
      <label
        v-for="option in options"
        :key="String(option.value)"
        class="h-radio"
        :class="{ 'is-on': sameValue(modelValue, option.value), 'is-disabled': disabled }"
      >
        <input
          class="h-sr"
          type="radio"
          :name="id"
          :value="String(option.value)"
          :checked="sameValue(modelValue, option.value)"
          :disabled="disabled"
          @change="emit('update:modelValue', option.value)"
        >
        <span class="h-radio-mark" />
        {{ option.title }}
      </label>
    </div>
  </HField>
</template>
