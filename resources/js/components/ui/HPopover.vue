<script setup>
import { popoverStyle } from '@/composables/usePopover'

defineProps({
  show: Boolean,
  coords: { type: Object, required: true },
  bindPanel: Function,
  role: { type: String, default: 'listbox' },
  panelClass: { type: [String, Array, Object], default: '' },
})
</script>

<template>
  <Teleport to="body">
    <HTransition name="h-pop">
      <div
        v-if="show"
        :ref="bindPanel"
        class="h-popover"
        :class="panelClass"
        :role="role"
        :style="popoverStyle(coords)"
      >
        <slot />
      </div>
    </HTransition>
  </Teleport>
</template>
