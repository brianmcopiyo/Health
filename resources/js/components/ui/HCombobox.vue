<script setup>
import { useListHighlight, usePopover } from '@/composables/usePopover'
import { errorText, normalizeOptions, sameValue, useFieldId } from '@/utils/formOptions'

const props = defineProps({
  modelValue: { default: '' },
  label: String,
  hint: String,
  description: String,
  error: [String, Array],
  items: { type: Array, default: () => [] },
  itemTitle: { type: String, default: 'title' },
  itemValue: { type: String, default: 'value' },
  placeholder: { type: String, default: 'Type or select' },
  required: Boolean,
  optional: Boolean,
  disabled: Boolean,
  loading: Boolean,
  icon: String,
  allowCustom: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue'])
const id = useFieldId('hc')
const query = ref('')
const { open, triggerRef, panelRef, coords, setOpen, close } = usePopover()
const options = computed(() => normalizeOptions(props.items, props.itemTitle, props.itemValue))
const filtered = computed(() => {
  const term = String(query.value || '').trim().toLowerCase()
  if (!term)
    return options.value

  return options.value.filter(item => item.title.toLowerCase().includes(term))
})
const { move, current, isActive } = useListHighlight(filtered, open)
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
        :placeholder="placeholder"
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
    <Teleport to="body">
      <div
        v-if="open && filtered.length"
        ref="panelRef"
        class="h-popover"
        :style="{ top: `${coords.top}px`, left: `${coords.left}px`, width: `${coords.width}px`, maxHeight: `${coords.maxHeight}px` }"
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
            @mousedown.prevent="choose(option)"
          >
            {{ option.title }}
          </li>
        </ul>
      </div>
    </Teleport>
  </HField>
</template>
