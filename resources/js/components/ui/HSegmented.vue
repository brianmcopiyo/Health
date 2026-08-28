<script setup>
defineProps({
  modelValue: String,
  options: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <div class="h-segmented">
    <button
      v-for="option in options"
      :key="option.value"
      type="button"
      :class="{ 'is-on': modelValue === option.value }"
      @click="emit('update:modelValue', option.value)"
    >
      {{ option.title }}
    </button>
  </div>
</template>
