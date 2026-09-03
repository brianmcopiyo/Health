<script setup>
import { useListHighlight, usePopover } from '@/composables/usePopover'
import { errorText, fieldPlaceholder, normalizeOptions, sameValue, useFieldId } from '@/utils/formOptions'

const props = defineProps({
  modelValue: { default: '' },
  label: String,
  hint: String,
  description: String,
  error: [String, Array],
  items: { type: Array, default: () => [] },
  itemTitle: { type: String, default: 'title' },
  itemValue: { type: String, default: 'value' },
  placeholder: String,
  required: Boolean,
  optional: Boolean,
  disabled: Boolean,
  loading: Boolean,
  icon: String,
  allowCustom: { type: Boolean, default: true },
  span: Boolean,
})

const emit = defineEmits(['update:modelValue'])
const id = useFieldId('hc')
const query = ref('')
const { open, triggerRef, coords, bindPanel, setOpen, close } = usePopover({
  matchWidth: true,
  minWidth: 198,
})
const options = computed(() => normalizeOptions(props.items, props.itemTitle, props.itemValue))
const filtered = computed(() => {
  const term = String(query.value || '').trim().toLowerCase()
  if (!term)
    return options.value

  return options.value.filter(item => item.title.toLowerCase().includes(term))
})
const { move, current, isActive, activate } = useListHighlight(filtered, open, () => props.modelValue)
const resolvedPlaceholder = computed(() => fieldPlaceholder(props.placeholder, props.label, 'combo'))
const message = computed(() => errorText(props.error))
const selected = computed(() => options.value.find(item => sameValue(item.value, props.modelValue) || sameValue(item.title, props.modelValue)))

watch(() => props.modelValue, value => {
  const match = options.value.find(item => sameValue(item.value, value) || sameValue(item.title, value))
  query.value = match ? match.title : (value ?? '')
}, { immediate: true })

const commit = value => {
  emit('update:modelValue', value)
  close()
}

const choose = option => {
  if (!option)
    return
  query.value = option.title
  commit(option.value)
}

const onInput = event => {
  query.value = event.target.value
  setOpen(true)
  if (props.allowCustom)
    emit('update:modelValue', event.target.value)
}

const onBlur = () => {
  const match = options.value.find(item => item.title.toLowerCase() === String(query.value || '').trim().toLowerCase())
  if (match)
    commit(match.value)
  else if (!props.allowCustom)
    query.value = selected.value?.title || ''
}

const onKey = event => {
  if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
    event.preventDefault()
    if (!open.value)
      setOpen(true)
    else
      move(event.key === 'ArrowDown' ? 1 : -1)
  }
  else if (event.key === 'Enter') {
    event.preventDefault()
    if (current())
      choose(current())
    else if (props.allowCustom)
      commit(query.value)
    close()
  }
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
      ref="triggerRef"
      class="h-control"
      :class="{ 'is-loading': loading }"
    >
      <HIcon
        v-if="icon"
        :name="icon"
      />
      <input
        :id="id"
        :value="query"
        type="text"
        role="combobox"
        :placeholder="resolvedPlaceholder"
        :disabled="disabled || loading"
        :required="required"
        :aria-expanded="open"
        :aria-invalid="Boolean(message)"
        autocomplete="off"
        @input="onInput"
        @focus="setOpen(true)"
        @blur="onBlur"
        @keydown="onKey"
      >
      <span
        v-if="loading"
        class="h-spin"
      />
      <HIcon
        v-else
        name="chevron"
      />
    </div>
    <HPopover
      :show="open && filtered.length"
      :coords="coords"
      :bind-panel="bindPanel"
    >
      <ul
        class="h-list"
        role="listbox"
      >
        <li
          v-for="option in filtered"
          :key="String(option.value)"
          class="h-list-item"
          :class="{ 'is-on': sameValue(option.value, modelValue), 'is-active': isActive(option) }"
          role="option"
          @mouseenter="activate(option)"
          @mousedown.prevent="choose(option)"
        >
          {{ option.title }}
        </li>
      </ul>
    </HPopover>
  </HField>
</template>
