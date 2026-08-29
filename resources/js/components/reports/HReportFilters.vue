<script setup>
const props = defineProps({
  modelValue: { type: Object, required: true },
  schema: { type: Object, default: () => ({ filters: [] }) },
  options: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue'])

const filters = computed(() => props.schema?.filters || [])
const has = key => filters.value.includes(key)

const set = (key, value) => {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}

const typeKey = computed(() => props.schema?.type_key || 'kind')
const typeLabel = computed(() => props.schema?.type_label || 'Type')
</script>

<template>
  <div class="h-report-filters">
    <HDatePicker
      v-if="has('from')"
      :model-value="modelValue.from"
      label="From"
      @update:model-value="set('from', $event)"
    />
    <HDatePicker
      v-if="has('to')"
      :model-value="modelValue.to"
      label="To"
      @update:model-value="set('to', $event)"
    />
    <HSelect
      v-if="has('department_id')"
      :model-value="modelValue.department_id"
      label="Department"
      :items="options.departments || []"
      @update:model-value="set('department_id', $event)"
    />
    <HSelect
      v-if="has('facility_id')"
      :model-value="modelValue.facility_id"
      label="Facility"
      :items="options.facilities || []"
      @update:model-value="set('facility_id', $event)"
    />
    <HSelect
      v-if="has('clinician_id')"
      :model-value="modelValue.clinician_id"
      label="Staff"
      :items="options.clinicians || []"
      @update:model-value="set('clinician_id', $event)"
    />
    <HSelect
      v-if="has('status')"
      :model-value="modelValue.status"
      label="Status"
      :items="schema.statuses || []"
      @update:model-value="set('status', $event)"
    />
    <HSelect
      v-if="has('patient_type')"
      :model-value="modelValue.patient_type"
      :label="typeLabel"
      :items="schema.types || []"
      @update:model-value="set('patient_type', $event)"
    />
    <HSelect
      v-if="has('kind') && typeKey === 'kind'"
      :model-value="modelValue.kind"
      :label="typeLabel"
      :items="schema.types || []"
      @update:model-value="set('kind', $event)"
    />
  </div>
</template>
