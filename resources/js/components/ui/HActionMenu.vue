<script setup>
const props = defineProps({
  label: { type: String, default: 'More' },
  align: { type: String, default: 'end' },
})

const { open, triggerRef, coords, bindPanel, toggle, close } = usePopover({
  align: toRef(props, 'align'),
  matchWidth: false,
  minWidth: 196,
})
</script>

<template>
  <div
    ref="triggerRef"
    class="h-action-menu"
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
    <HPopover
      :show="open"
      :coords="coords"
      :bind-panel="bindPanel"
      role="menu"
      panel-class="is-menu"
    >
      <slot :close="close" />
    </HPopover>
  </div>
</template>
