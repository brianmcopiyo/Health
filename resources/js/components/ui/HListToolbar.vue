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
  clearLabel: { type: String, default: 'Clear filters' },
  moreLabel: { type: String, default: 'More filters' },
  stacked: Boolean,
})

const emit = defineEmits(['update:search', 'update:values', 'search', 'change', 'clear'])

const { open, triggerRef, coords, bindPanel, toggle } = usePopover({
  align: 'start',
  matchWidth: false,
  minWidth: 280,
})

const useSearch = computed(() => {
  if (props.showSearch === false)
    return false
  if (props.showSearch === true || props.searchPlaceholder != null)
    return true

  return false
})

const emptyFor = filter => {
  if (Object.prototype.hasOwnProperty.call(filter, 'empty'))
    return filter.empty
  if (Object.prototype.hasOwnProperty.call(filter, 'default'))
    return filter.default

  return null
}

const isClearable = filter => {
  if (filter.clearable === false)
    return false
  if (filter.clearable === true)
    return true

  return emptyFor(filter) == null
}

const isDirty = filter => {
  const value = props.values?.[filter.key]
  const empty = emptyFor(filter)
  if (value === empty || value === undefined || value === '')
    return false
  if (value === false && (empty === false || empty === null))
    return false

  return true
}

const primaryFilters = computed(() => props.filters.filter(filter => !filter.more))
const moreFilters = computed(() => props.filters.filter(filter => filter.more))
const moreActive = computed(() => moreFilters.value.filter(isDirty).length)

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

const displayValue = filter => {
  const value = props.values?.[filter.key]
  if (filter.type === 'select' || filter.type === 'segmented' || !filter.type) {
    const titleKey = filter.itemTitle || 'title'
    const valueKey = filter.itemValue || 'value'
    const item = (filter.items || filter.options || []).find(entry => String(entry[valueKey] ?? entry.value) === String(value))
    return item?.[titleKey] ?? item?.name ?? item?.title ?? value
  }

  return value
}

const chips = computed(() => {
  const items = []
  if (useSearch.value && String(props.search || '').trim())
    items.push({ key: 'search', label: `Search: ${String(props.search).trim()}` })
  props.filters.filter(isDirty).forEach(filter => {
    items.push({ key: filter.key, label: `${filter.label}: ${displayValue(filter)}` })
  })
  return items
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

const clearChip = key => {
  if (key === 'search') {
    emit('update:search', '')
    emit('search')
    emit('change', { key: 'search', value: '' })
    return
  }
  const filter = props.filters.find(item => item.key === key)
  setFilter(key, filter ? emptyFor(filter) : null)
}

const onSearchKey = event => {
  if (event.key === 'Enter')
    submit()
}
</script>

<template>
  <div class="h-list-toolbar">
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
        v-for="filter in primaryFilters"
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
          :clearable="isClearable(filter)"
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
          :clearable="isClearable(filter)"
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
          v-else-if="filter.type === 'text' || filter.type === 'number'"
          :class="{ 'is-search': filter.search, 'is-wide': filter.wide }"
          :model-value="values[filter.key]"
          :type="filter.type === 'number' ? 'number' : 'text'"
          :label="filter.label"
          :placeholder="filter.placeholder"
          :icon="filter.icon"
          :clearable="isClearable(filter)"
          :disabled="filter.disabled"
          @update:model-value="setFilter(filter.key, $event)"
          @keyup.enter="submit"
        />
      </template>
      <div
        v-if="moreFilters.length"
        ref="triggerRef"
        class="h-filter-more"
      >
        <HButton
          variant="ghost"
          :aria-expanded="open"
          aria-haspopup="dialog"
          @click.stop="toggle"
        >
          {{ moreLabel }}
          <HBadge
            v-if="moreActive"
            tone="info"
          >
            {{ moreActive }}
          </HBadge>
          <HIcon name="chevron" />
        </HButton>
        <HPopover
          :show="open"
          :coords="coords"
          :bind-panel="bindPanel"
          role="dialog"
          panel-class="is-filters"
        >
          <template
            v-for="filter in moreFilters"
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
              :clearable="isClearable(filter)"
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
              :clearable="isClearable(filter)"
              :min="filter.min"
              :max="filter.max"
              :disabled="filter.disabled"
              @update:model-value="setFilter(filter.key, $event)"
            />
            <HInput
              v-else-if="filter.type === 'text' || filter.type === 'number'"
              :model-value="values[filter.key]"
              :type="filter.type === 'number' ? 'number' : 'text'"
              :label="filter.label"
              :placeholder="filter.placeholder"
              :icon="filter.icon"
              :clearable="isClearable(filter)"
              :disabled="filter.disabled"
              @update:model-value="setFilter(filter.key, $event)"
              @keyup.enter="submit"
            />
            <HSwitch
              v-else-if="filter.type === 'switch'"
              :model-value="Boolean(values[filter.key])"
              :label="filter.label"
              :disabled="filter.disabled"
              @update:model-value="setFilter(filter.key, $event)"
            />
          </template>
        </HPopover>
      </div>
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
    <div
      v-if="chips.length"
      class="h-filter-chips"
    >
      <button
        v-for="chip in chips"
        :key="chip.key"
        type="button"
        class="h-filter-chip"
        :aria-label="`Clear ${chip.label}`"
        @click="clearChip(chip.key)"
      >
        {{ chip.label }}
        <HIcon
          name="x"
          :size="14"
        />
      </button>
    </div>
  </div>
</template>
