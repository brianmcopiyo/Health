<script setup>
defineProps({
  name: { type: String, default: 'h-fade' },
  mode: String,
  appear: Boolean,
})
</script>

<template>
  <Transition
    :name="name"
    :mode="mode"
    :appear="appear"
  >
    <slot />
  </Transition>
</template>
