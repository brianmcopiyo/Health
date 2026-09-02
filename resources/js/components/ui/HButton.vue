<script setup>
const props = defineProps({
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' },
  to: { type: [String, Object], default: null },
  disabled: Boolean,
  loading: Boolean,
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

const busy = computed(() => props.disabled || props.loading)

const onLinkClick = event => {
  if (busy.value)
    event.preventDefault()
}
</script>

<template>
  <RouterLink
    v-if="dest"
    :to="dest"
    class="h-btn"
    :class="{ 'is-ghost': variant === 'ghost', 'is-danger': variant === 'danger', 'is-ok': variant === 'ok', 'is-small': size === 'sm', 'is-icon': size === 'icon', 'is-loading': loading }"
    :aria-disabled="busy ? 'true' : undefined"
    :aria-busy="loading ? 'true' : undefined"
    @click="onLinkClick"
  >
    <span
      v-if="loading"
      class="h-loader"
      aria-hidden="true"
    >
      <span />
      <span />
      <span />
    </span>
    <slot />
  </RouterLink>
  <button
    v-else
    :type="type"
    class="h-btn"
    :class="{ 'is-ghost': variant === 'ghost', 'is-danger': variant === 'danger', 'is-ok': variant === 'ok', 'is-small': size === 'sm', 'is-icon': size === 'icon', 'is-loading': loading }"
    :disabled="busy"
    :aria-busy="loading ? 'true' : undefined"
  >
    <span
      v-if="loading"
      class="h-loader"
      aria-hidden="true"
    >
      <span />
      <span />
      <span />
    </span>
    <slot />
  </button>
</template>
