<script setup>
const props = defineProps({
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' },
  to: { type: [String, Object], default: null },
  disabled: Boolean,
  type: { type: String, default: 'button' },
})

const dest = computed(() => {
  if (!props.to)
    return null
  if (typeof props.to !== 'string')
    return props.to
  if (props.to.startsWith('/') || props.to.startsWith('http'))
    return props.to
  return { name: props.to }
})
</script>

<template>
  <RouterLink
    v-if="dest"
    :to="dest"
    class="h-btn"
    :class="{ 'is-ghost': variant === 'ghost', 'is-danger': variant === 'danger', 'is-ok': variant === 'ok', 'is-small': size === 'sm', 'is-icon': size === 'icon' }"
  >
    <slot />
  </RouterLink>
  <button
    v-else
    :type="type"
    class="h-btn"
    :class="{ 'is-ghost': variant === 'ghost', 'is-danger': variant === 'danger', 'is-ok': variant === 'ok', 'is-small': size === 'sm', 'is-icon': size === 'icon' }"
    :disabled="disabled"
  >
    <slot />
  </button>
</template>
