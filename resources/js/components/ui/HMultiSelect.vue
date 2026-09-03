<script setup>
import { useListHighlight, usePopover } from '@/composables/usePopover'
import { errorText, fieldPlaceholder, normalizeOptions, sameValue, useFieldId } from '@/utils/formOptions'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
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
  searchable: { type: Boolean, default: true },
  selectAll: { type: Boolean, default: true },
  span: Boolean,
})

const emit = defineEmits(['update:modelValue'])
const id = useFieldId('hm')
const query = ref('')
const searchRef = ref(null)
const { open, triggerRef, coords, bindPanel, setOpen, toggle, close } = usePopover({
  matchWidth: true,
  minWidth: 198,
})
const options = computed(() => normalizeOptions(props.items, props.itemTitle, props.itemValue))
const selected = computed(() => props.modelValue || [])
const filtered = computed(() => {
  const term = query.value.trim().toLowerCase()
  if (!term)
    return options.value

  return options.value.filter(item => item.title.toLowerCase().includes(term))
})
const { move, current, isActive, activate } = useListHighlight(filtered, open, () => props.modelValue)
const resolvedPlaceholder = computed(() => fieldPlaceholder(props.placeholder, props.label, 'multi'))
const searchPlaceholder = computed(() => fieldPlaceholder(null, props.label, 'search'))
const message = computed(() => errorText(props.error))
const selectedOptions = computed(() => options.value.filter(item => selected.value.some(value => sameValue(value, item.value))))
const allFilteredSelected = computed(() => filtered.value.length > 0 && filtered.value.every(item => selected.value.some(value => sameValue(value, item.value))))

watch(open, value => {
  query.value = ''
  if (value && props.searchable)
    nextTick(() => searchRef.value?.focus())
})

const isChecked = option => selected.value.some(value => sameValue(value, option.value))

const setSelected = values => emit('update:modelValue', values)

const toggleValue = option => {
  if (isChecked(option))
    setSelected(selected.value.filter(value => !sameValue(value, option.value)))
  else
    setSelected([...selected.value, option.value])
}

const remove = (event, option) => {
  event.stopPropagation()
  setSelected(selected.value.filter(value => !sameValue(value, option.value)))
}

const toggleAll = () => {
  if (allFilteredSelected.value) {
    setSelected(selected.value.filter(value => !filtered.value.some(item => sameValue(item.value, value))))
    return
  }
  const next = [...selected.value]
  filtered.value.forEach(item => {
    if (!next.some(value => sameValue(value, item.value)))
      next.push(item.value)
  })
  setSelected(next)
}

const onTriggerKey = event => {
  if (props.disabled || props.loading)
    return
  if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
    event.preventDefault()
    if (!open.value)
      setOpen(true)
    else if (event.key === 'Enter' || event.key === ' ')
      toggleValue(current() || {})
    else if (event.key === 'ArrowDown')
      move(1)
    else if (event.key === 'ArrowUp')
      move(-1)
  }
}

const onSearchKey = event => {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    move(1)
  }
  else if (event.key === 'ArrowUp') {
    event.preventDefault()
    move(-1)
  }
  else if (event.key === 'Enter') {
    event.preventDefault()
    if (current())
      toggleValue(current())
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
      :id="id"
      ref="triggerRef"
      class="h-control is-chips"
      :class="{ 'is-open': open, 'is-loading': loading }"
      role="combobox"
      :aria-expanded="open"
      :aria-invalid="Boolean(message)"
      tabindex="0"
      @click="toggle"
      @keydown="onTriggerKey"
    >
      <div class="h-chips">
        <span
          v-for="option in selectedOptions"
          :key="String(option.value)"
          class="h-chip"
        >
          {{ option.title }}
          <button
            type="button"
            aria-label="Remove"
            @click="remove($event, option)"
          >
            <HIcon
              name="x"
              :size="12"
            />
          </button>
        </span>
        <span
          v-if="!selectedOptions.length"
          class="is-placeholder"
        >
          {{ resolvedPlaceholder }}
        </span>
      </div>
      <HIcon name="chevron" />
    </div>
    <HPopover
      :show="open"
      :coords="coords"
      :bind-panel="bindPanel"
    >
      <div
        v-if="searchable"
        class="h-popover-search"
      >
        <HIcon name="search" />
        <input
          ref="searchRef"
          v-model="query"
          type="search"
          :placeholder="searchPlaceholder"
          @keydown="onSearchKey"
        >
      </div>
      <button
        v-if="selectAll && filtered.length"
        class="h-list-action"
        type="button"
        @click="toggleAll"
      >
        {{ allFilteredSelected ? 'Clear visible' : 'Select all visible' }}
      </button>
      <ul
        class="h-list"
        role="listbox"
        aria-multiselectable="true"
      >
        <li
          v-for="option in filtered"
          :key="String(option.value)"
          class="h-list-item"
          :class="{ 'is-on': isChecked(option), 'is-active': isActive(option) }"
          role="option"
          :aria-selected="isChecked(option)"
          @mouseenter="activate(option)"
          @mousedown.prevent="toggleValue(option)"
        >
          <span
            class="h-check-box"
            :class="{ 'is-on': isChecked(option) }"
          >
            <HIcon
              v-if="isChecked(option)"
              name="check"
              :size="12"
            />
          </span>
          {{ option.title }}
        </li>
        <li
          v-if="!filtered.length"
          class="h-list-empty"
        >
          No matching options
        </li>
      </ul>
    </HPopover>
  </HField>
</template>
