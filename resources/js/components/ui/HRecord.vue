<script setup>
import { statusColor, labelize } from '@/utils/status'

defineProps({
  title: String,
  subtitle: String,
  status: String,
  back: [String, Object],
  backLabel: { type: String, default: 'Back' },
  tabs: { type: Array, default: () => [] },
  tab: String,
  loading: Boolean,
  missing: Boolean,
  missingMessage: { type: String, default: 'This record could not be loaded.' },
})

const emit = defineEmits(['update:tab'])
</script>

<template>
  <div class="h-record">
    <HPage
      :title="title || 'Record'"
      :subtitle="subtitle"
    >
      <HButton
        v-if="back"
        variant="ghost"
        :to="back"
      >
        <HIcon name="back" />
        {{ backLabel }}
      </HButton>
      <HBadge
        v-if="status"
        :tone="statusColor(status)"
      >
        {{ labelize(status) }}
      </HBadge>
      <slot name="actions" />
    </HPage>

    <div
      v-if="loading && !missing"
      class="h-muted"
    >
      Loading record…
    </div>

    <div
      v-else-if="missing"
      class="h-alert"
    >
      {{ missingMessage }}
    </div>

    <template v-else>
      <nav
        v-if="tabs.length"
        class="h-record-tabs"
      >
        <button
          v-for="item in tabs"
          :key="item.value"
          type="button"
          :class="{ 'is-on': tab === item.value }"
          @click="emit('update:tab', item.value)"
        >
          {{ item.title }}
        </button>
      </nav>
      <slot />
    </template>
  </div>
</template>
