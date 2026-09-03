<script setup>
import { routeTo } from '@/utils/helpers'

const props = defineProps({
  to: { type: [String, Object], default: null },
  secondary: { type: String, default: '' },
})

const dest = computed(() => routeTo(props.to) || null)
</script>

<template>
  <div class="h-cell-stack">
    <RouterLink
      v-if="dest"
      class="h-inline-link"
      :to="dest"
    >
      <slot />
    </RouterLink>
    <span
      v-else
      class="h-cell-primary"
    >
      <slot />
    </span>
    <span
      v-if="secondary"
      class="h-muted"
    >{{ secondary }}</span>
    <slot name="secondary" />
  </div>
</template>
