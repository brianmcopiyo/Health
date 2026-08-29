<script setup>
defineProps({
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' },
  to: { type: [String, Object], default: null },
  disabled: Boolean,
  type: { type: String, default: 'button' },
})
</script>

<template>
  <RouterLink
    v-if="to"
    :to="typeof to === 'string' ? { name: to } : to"
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
