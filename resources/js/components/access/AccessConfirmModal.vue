<script setup>
defineProps({
  modelValue: Boolean,
  title: { type: String, default: 'Confirm' },
  message: String,
  confirmLabel: { type: String, default: 'Remove' },
  cancelLabel: { type: String, default: 'Keep' },
  error: String,
  saving: Boolean,
})

const emit = defineEmits(['update:modelValue', 'confirm'])
</script>

<template>
  <HModal
    :model-value="modelValue"
    :title="title"
    :error="error"
    :persistent="saving"
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <p>{{ message }}</p>
    <template #actions>
      <HButton
        variant="ghost"
        :disabled="saving"
        @click="emit('update:modelValue', false)"
      >
        {{ cancelLabel }}
      </HButton>
      <HButton
        variant="danger"
        :loading="saving"
        :disabled="saving"
        @click="emit('confirm')"
      >
        {{ confirmLabel }}
      </HButton>
    </template>
  </HModal>
</template>
