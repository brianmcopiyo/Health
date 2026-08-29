<script setup>
defineProps({
  label: { type: String, default: 'More' },
  align: { type: String, default: 'end' },
})

const open = ref(false)
const root = ref(null)

const close = () => {
  open.value = false
}

const toggle = () => {
  open.value = !open.value
}

const onDoc = event => {
  if (root.value && !root.value.contains(event.target))
    close()
}

onMounted(() => document.addEventListener('click', onDoc))
onBeforeUnmount(() => document.removeEventListener('click', onDoc))
</script>

<template>
  <div
    ref="root"
    class="h-action-menu"
    :class="`is-${align}`"
  >
    <HButton
      variant="ghost"
      size="sm"
      :aria-expanded="open"
      aria-haspopup="menu"
      @click.stop="toggle"
    >
      {{ label }}
      <HIcon name="chevron" />
    </HButton>
    <div
      v-if="open"
      class="h-action-menu-list"
      role="menu"
    >
      <slot :close="close" />
    </div>
  </div>
</template>
