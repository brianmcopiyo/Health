<script setup>
const props = defineProps({
  search: { type: String, default: '' },
  searchLabel: { type: String, default: 'Search' },
  searchPlaceholder: String,
  showSearch: { type: Boolean, default: undefined },
  searchButton: Boolean,
  searchButtonLabel: { type: String, default: 'Search' },
  searchMode: { type: String, default: 'submit' },
  filters: { type: Array, default: () => [] },
  values: { type: Object, default: () => ({}) },
  loading: Boolean,
  resultCount: { type: [Number, String], default: null },
  showClear: { type: [Boolean, String], default: 'auto' },
  clearLabel: { type: String, default: 'Clear' },
  stacked: Boolean,
})

const emit = defineEmits(['update:search', 'update:values', 'search', 'change', 'clear'])

const useSearch = computed(() => {
  if (props.showSearch === false)
    return false
  if (props.showSearch === true || props.searchPlaceholder != null)
    return true

  return false
})

const emptyFor = filter => (Object.prototype.hasOwnProperty.call(filter, 'empty') ? filter.empty : null)

const isDirty = filter => {
  const value = props.values?.[filter.key]
  const empty = emptyFor(filter)
  if (value === empty || value === undefined || value === '')
    return false
  if (value === false && (empty === false || empty === null))
    return false

  return true
}

const hasActive = computed(() => {
  if (useSearch.value && String(props.search || '').trim())
    return true

  return props.filters.some(isDirty)
})

const clearVisible = computed(() => {
  if (props.showClear === false)
    return false
  if (props.showClear === true)
    return true

  return hasActive.value
})

const setSearch = value => {
  emit('update:search', value)
  if (props.searchMode === 'live') {
    emit('search')
    emit('change', { key: 'search', value })
  }
}

const setFilter = (key, value) => {
  emit('update:values', { ...props.values, [key]: value })
  emit('change', { key, value })
}

const submit = () => emit('search')

const clear = () => {
  const next = { ...props.values }
  props.filters.forEach(filter => {
    next[filter.key] = emptyFor(filter)
  })
  emit('update:search', '')
  emit('update:values', next)
  emit('clear')
  emit('search')
  emit('change', { key: null, value: null })
}

const onSearchKey = event => {
  if (event.key === 'Enter')
    submit()
}
</script>

<template>
  <HToolbar :stacked="stacked">
    <HInput
      v-if="useSearch"
      class="is-search"
      :model-value="search"
      :label="searchLabel"
      :placeholder="searchPlaceholder"
      icon="search"
      clearable
      @update:model-value="setSearch"
      @keyup.enter="onSearchKey"
    />
    <template
      v-for="filter in filters"
      :key="filter.key"
    >
      <HSelect
        v-if="filter.type === 'select' || !filter.type"
        :model-value="values[filter.key]"
        :items="filter.items || []"
        :item-title="filter.itemTitle || 'title'"
        :item-value="filter.itemValue || 'value'"
        :label="filter.label"
        :placeholder="filter.placeholder"
        :optional="filter.optional"
        :clearable="filter.clearable !== false"
        :disabled="filter.disabled"
        :loading="filter.loading"
        @update:model-value="setFilter(filter.key, $event)"
      />
      <HDatePicker
        v-else-if="filter.type === 'date'"
        :model-value="values[filter.key]"
        :label="filter.label"
        :placeholder="filter.placeholder"
        :optional="filter.optional"
        :min="filter.min"
        :max="filter.max"
        :disabled="filter.disabled"
        @update:model-value="setFilter(filter.key, $event)"
      />
      <HSegmented
        v-else-if="filter.type === 'segmented'"
        :model-value="values[filter.key]"
        :options="filter.options || filter.items || []"
        @update:model-value="setFilter(filter.key, $event)"
      />
      <HSwitch
        v-else-if="filter.type === 'switch'"
        :model-value="Boolean(values[filter.key])"
        :label="filter.label"
        :disabled="filter.disabled"
        @update:model-value="setFilter(filter.key, $event)"
      />
      <HInput
        v-else-if="filter.type === 'text'"
        :class="{ 'is-search': filter.search, 'is-wide': filter.wide }"
        :model-value="values[filter.key]"
        :label="filter.label"
        :placeholder="filter.placeholder"
        :icon="filter.icon"
        :clearable="filter.clearable !== false"
        :disabled="filter.disabled"
        @update:model-value="setFilter(filter.key, $event)"
        @keyup.enter="submit"
      />
    </template>
    <slot />
    <HButton
      v-if="searchButton"
      variant="ghost"
      @click="submit"
    >
      {{ searchButtonLabel }}
    </HButton>
    <HButton
      v-if="clearVisible"
      variant="ghost"
      @click="clear"
    >
      {{ clearLabel }}
    </HButton>
    <template
      v-if="resultCount != null || $slots.actions"
      #actions
    >
      <span
        v-if="resultCount != null"
        class="h-muted"
      >{{ resultCount }}</span>
      <slot name="actions" />
    </template>
  </HToolbar>
</template>
