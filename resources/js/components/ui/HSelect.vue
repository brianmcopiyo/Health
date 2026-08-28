<script setup>
const props = defineProps({
  modelValue: { default: null },
  label: String,
  items: { type: Array, default: () => [] },
  itemTitle: { type: String, default: 'title' },
  itemValue: { type: String, default: 'value' },
  placeholder: { type: String, default: 'Select' },
})

const emit = defineEmits(['update:modelValue'])

const options = computed(() => props.items.map(item => {
  if (item === null || ['string', 'number'].includes(typeof item))
    return { title: item === null ? props.placeholder : String(item), value: item }

  return {
    title: item[props.itemTitle] ?? item.name ?? item.title,
    value: item[props.itemValue] ?? item.id ?? item.value,
  }
}))

const current = computed(() => props.modelValue === null || props.modelValue === undefined ? '' : String(props.modelValue))

const change = event => {
  const raw = event.target.value
  if (raw === '') {
    emit('update:modelValue', null)
    return
  }
  const option = options.value.find(item => String(item.value) === raw)
  emit('update:modelValue', option ? option.value : raw)
}
</script>

<template>
  <label class="h-field">
    <span v-if="label">{{ label }}</span>
    <select
      :value="current"
      @change="change"
    >
      <option value="">
        {{ placeholder }}
      </option>
      <option
        v-for="option in options"
        :key="String(option.value)"
        :value="String(option.value ?? '')"
      >
        {{ option.title }}
      </option>
    </select>
  </label>
</template>
