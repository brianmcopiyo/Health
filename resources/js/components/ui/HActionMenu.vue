<script setup>
import { routeTo } from '@/utils/helpers'

const props = defineProps({
  label: { type: String, default: 'Actions' },
  align: { type: String, default: 'end' },
  compact: { type: Boolean, default: null },
  actions: { type: Array, default: () => [] },
})

const slots = useSlots()

const { open, triggerRef, coords, bindPanel, toggle, close } = usePopover({
  align: toRef(props, 'align'),
  matchWidth: false,
  minWidth: 196,
})

const visible = computed(() => (props.actions || []).filter(action => action && action.if !== false))
const safeActions = computed(() => visible.value.filter(action => !action.danger))
const dangerActions = computed(() => visible.value.filter(action => action.danger))
const hasSlot = computed(() => Boolean(slots.default))
const showMenu = computed(() => visible.value.length > 0 || hasSlot.value)
const isCompact = computed(() => (props.compact === null ? visible.value.length > 0 && !hasSlot.value : props.compact))

const dest = action => routeTo(action.to)

const choose = action => {
  close()
  action.onSelect?.()
}
</script>

<template>
  <div
    v-if="showMenu"
    ref="triggerRef"
    class="h-action-menu"
  >
    <HButton
      variant="ghost"
      :size="isCompact ? 'icon' : 'sm'"
      :aria-label="isCompact ? label : undefined"
      :aria-expanded="open"
      aria-haspopup="menu"
      @click.stop="toggle"
    >
      <template v-if="isCompact">
        <HIcon name="more" />
      </template>
      <template v-else>
        {{ label }}
        <HIcon name="chevron" />
      </template>
    </HButton>
    <HPopover
      :show="open"
      :coords="coords"
      :bind-panel="bindPanel"
      role="menu"
      panel-class="is-menu"
    >
      <template
        v-for="(action, index) in safeActions"
        :key="`safe-${index}`"
      >
        <RouterLink
          v-if="dest(action)"
          class="h-action-item"
          role="menuitem"
          :to="dest(action)"
          @click="close"
        >
          <HIcon
            v-if="action.icon"
            :name="action.icon"
            :size="16"
          />
          {{ action.label }}
        </RouterLink>
        <button
          v-else
          type="button"
          class="h-action-item"
          role="menuitem"
          @click="choose(action)"
        >
          <HIcon
            v-if="action.icon"
            :name="action.icon"
            :size="16"
          />
          {{ action.label }}
        </button>
      </template>
      <div
        v-if="safeActions.length && dangerActions.length"
        class="h-action-sep"
        role="separator"
      />
      <template
        v-for="(action, index) in dangerActions"
        :key="`danger-${index}`"
      >
        <RouterLink
          v-if="dest(action)"
          class="h-action-item is-danger"
          role="menuitem"
          :to="dest(action)"
          @click="close"
        >
          <HIcon
            v-if="action.icon"
            :name="action.icon"
            :size="16"
          />
          {{ action.label }}
        </RouterLink>
        <button
          v-else
          type="button"
          class="h-action-item is-danger"
          role="menuitem"
          @click="choose(action)"
        >
          <HIcon
            v-if="action.icon"
            :name="action.icon"
            :size="16"
          />
          {{ action.label }}
        </button>
      </template>
      <slot :close="close" />
    </HPopover>
  </div>
</template>
