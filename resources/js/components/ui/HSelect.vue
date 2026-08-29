<script setup>
import { useListHighlight, usePopover } from '@/composables/usePopover'
import { errorText, fieldPlaceholder, normalizeOptions, sameValue, useFieldId } from '@/utils/formOptions'

const props = defineProps({
  modelValue: { default: null },
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
  clearable: { type: Boolean, default: true },
  searchable: { type: Boolean, default: undefined },
  span: Boolean,
})

const emit = defineEmits(['update:modelValue'])
const id = useFieldId('hs')
const query = ref('')
const searchRef = ref(null)
const { open, triggerRef, coords, bindPanel, setOpen, toggle, close } = usePopover({
  matchWidth: true,
  minWidth: 198,
})
const options = computed(() => normalizeOptions(props.items, props.itemTitle, props.itemValue))
const showSearch = computed(() => props.searchable ?? options.value.length > 5)
const filtered = computed(() => {
  const term = query.value.trim().toLowerCase()
  if (!term)
    return options.value

  return options.value.filter(item => item.title.toLowerCase().includes(term))
})
const { move, current, isActive } = useListHighlight(filtered, open)
const selected = computed(() => options.value.find(item => sameValue(item.value, props.modelValue)) || null)
const resolvedPlaceholder = computed(() => fieldPlaceholder(props.placeholder, props.label, 'select'))
const searchPlaceholder = computed(() => fieldPlaceholder(null, props.label, 'search'))
const message = computed(() => errorText(props.error))

watch(open, value => {
  query.value = ''
  if (value && showSearch.value)
    nextTick(() => searchRef.value?.focus())
})

const choose = option => {
  emit('update:modelValue', option?.value ?? null)
  close()
}

const clear = event => {
  event.stopPropagation()
  emit('update:modelValue', null)
}

const onTriggerKey = event => {
  if (props.disabled || props.loading)
    return
  if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
    event.preventDefault()
    if (!open.value)
      setOpen(true)
    else if (event.key === 'Enter')
      choose(current())
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
    choose(current())
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
      class="h-control h-select-trigger"
      role="combobox"
      tabindex="0"
      :aria-disabled="disabled || loading"
      :aria-expanded="open"
      :aria-invalid="Boolean(message)"
      aria-haspopup="listbox"
      @click="!disabled && !loading && toggle()"
      @keydown="onTriggerKey"
    >
      <span :class="{ 'is-placeholder': !selected }">
        {{ selected?.title || resolvedPlaceholder }}
      </span>
      <button
        v-if="clearable && selected && !disabled"
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
      />
      <HIcon
        v-else
        name="chevron"
      />
    </div>
    <HPopover
      :show="open"
      :coords="coords"
      :bind-panel="bindPanel"
    >
      <div
        v-if="showSearch"
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
          :aria-selected="sameValue(option.value, modelValue)"
          @mousedown.prevent="choose(option)"
        >
          <span>{{ option.title }}</span>
          <HIcon
            v-if="sameValue(option.value, modelValue)"
            name="check"
          />
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
