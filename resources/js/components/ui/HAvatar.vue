<script setup>
const props = defineProps({
  src: String,
  name: String,
  initials: String,
  size: { type: [Number, String], default: 36 },
  status: String,
  rail: Boolean,
})

const label = computed(() => {
  if (props.initials)
    return props.initials
  const parts = String(props.name || 'U').split(/\s+/).filter(Boolean)
  return parts.map(part => part[0]).slice(0, 2).join('').toUpperCase() || 'U'
})

const faceStyle = computed(() => {
  const size = Number(props.size) || 36
  return {
    width: `${size}px`,
    height: `${size}px`,
    fontSize: `${Math.max(11, Math.round(size * 0.34))}px`,
  }
})
</script>

<template>
  <span
    class="h-avatar"
    :class="{ 'is-rail': rail, [`is-${status}`]: status }"
  >
    <span
      class="h-avatar-face"
      :style="faceStyle"
    >
      <img
        v-if="src"
        :src="src"
        alt=""
      >
      <template v-else>
        {{ label }}
      </template>
    </span>
    <i
      v-if="status"
      class="h-avatar-status"
      :class="`is-${status}`"
    />
  </span>
</template>
