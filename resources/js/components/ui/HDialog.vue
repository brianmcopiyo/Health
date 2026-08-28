<script setup>
defineProps({
  modelValue: Boolean,
  title: String,
  wide: Boolean,
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <div
    v-if="modelValue"
    class="h-dialog-backdrop"
    @click.self="emit('update:modelValue', false)"
  >
    <div
      class="h-dialog"
      :style="wide ? 'width:min(860px,100%)' : ''"
    >
      <div class="h-dialog-head">
        <h3>{{ title }}</h3>
        <HButton
          variant="ghost"
          size="icon"
          @click="emit('update:modelValue', false)"
        >
          <HIcon name="x" />
        </HButton>
      </div>
      <div class="h-dialog-body">
        <slot />
      </div>
      <div
        v-if="$slots.actions"
        class="h-dialog-foot"
      >
        <span />
        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
          <slot name="actions" />
        </div>
      </div>
    </div>
  </div>
</template>
