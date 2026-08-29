<script setup>
import { useToast } from '@/composables/useToast'

const { toasts, dismiss } = useToast()
</script>

<template>
  <Teleport to="body">
    <div
      class="h-toasts"
      aria-live="polite"
      aria-relevant="additions"
    >
      <TransitionGroup name="h-toast">
        <div
          v-for="item in toasts"
          :key="item.id"
          class="h-toast"
          :class="`is-${item.tone}`"
          role="status"
        >
          <p>{{ item.message }}</p>
          <button
            type="button"
            class="h-toast-close"
            aria-label="Dismiss notification"
            @click="dismiss(item.id)"
          >
            <HIcon
              name="x"
              :size="14"
            />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>
