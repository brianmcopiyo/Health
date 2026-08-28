<script setup>
import { errorText, useFieldId } from '@/utils/formOptions'

const props = defineProps({
  label: String,
  hint: String,
  description: String,
  error: [String, Array],
  required: Boolean,
  optional: Boolean,
  htmlFor: String,
  disabled: Boolean,
})

const fallbackId = useFieldId()
const controlId = computed(() => props.htmlFor || fallbackId)
const message = computed(() => errorText(props.error))
const hintText = computed(() => props.description || props.hint || '')

provide('hField', {
  id: controlId,
  error: message,
  disabled: computed(() => props.disabled),
})
</script>

<template>
  <div
    class="h-field"
    :class="{ 'is-invalid': message, 'is-disabled': disabled }"
  >
    <label
      v-if="label"
      class="h-field-label"
      :for="controlId"
    >
      <span>{{ label }}</span>
      <em
        v-if="required"
        class="h-req"
      >Required</em>
      <em
        v-else-if="optional"
        class="h-opt"
      >Optional</em>
    </label>
    <p
      v-if="hintText"
      class="h-field-hint"
      :id="`${controlId}-hint`"
    >
      {{ hintText }}
    </p>
    <slot :id="controlId" />
    <p
      v-if="message"
      class="error"
      :id="`${controlId}-error`"
      role="alert"
    >
      {{ message }}
    </p>
  </div>
</template>
