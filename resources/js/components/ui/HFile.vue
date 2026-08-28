<script setup>
import { errorText, useFieldId } from '@/utils/formOptions'

const props = defineProps({
  modelValue: { default: null },
  label: String,
  hint: String,
  description: String,
  error: [String, Array],
  placeholder: { type: String, default: 'Drop files or browse' },
  required: Boolean,
  optional: Boolean,
  disabled: Boolean,
  accept: String,
  multiple: Boolean,
})

const emit = defineEmits(['update:modelValue'])
const id = useFieldId('hf')
const dragging = ref(false)

const files = computed(() => {
  if (!props.modelValue)
    return []
  if (Array.isArray(props.modelValue))
    return props.modelValue
  if (typeof FileList !== 'undefined' && props.modelValue instanceof FileList)
    return Array.from(props.modelValue)

  return [props.modelValue]
})

const setFiles = list => {
  const next = Array.from(list || [])
  emit('update:modelValue', props.multiple ? next : (next[0] || null))
}

const onChange = event => setFiles(event.target.files)

const onDrop = event => {
  dragging.value = false
  if (props.disabled)
    return
  setFiles(event.dataTransfer?.files)
}

const remove = index => {
  if (props.multiple)
    emit('update:modelValue', files.value.filter((_, i) => i !== index))
  else
    emit('update:modelValue', null)
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
    <label
      class="h-file"
      :class="{ 'is-over': dragging, 'is-disabled': disabled }"
      @dragover.prevent="dragging = true"
      @dragleave="dragging = false"
      @drop.prevent="onDrop"
    >
      <input
        :id="id"
        class="h-sr"
        type="file"
        :accept="accept"
        :multiple="multiple"
        :disabled="disabled"
        :required="required && !files.length"
        :aria-invalid="Boolean(errorText(error))"
        @change="onChange"
      >
      <HIcon name="upload" />
      <span>{{ placeholder }}</span>
    </label>
    <ul
      v-if="files.length"
      class="h-file-list"
    >
      <li
        v-for="(file, index) in files"
        :key="file.name + index"
      >
        <span>{{ file.name }}</span>
        <button
          type="button"
          class="h-control-btn"
          aria-label="Remove file"
          @click="remove(index)"
        >
          <HIcon name="x" />
        </button>
      </li>
    </ul>
  </HField>
</template>
