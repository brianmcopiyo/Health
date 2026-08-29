<script setup>
defineProps({
  title: String,
  flush: Boolean,
})
</script>

<template>
  <section
    class="h-card"
    :class="{ 'is-flush': flush }"
  >
    <div
      v-if="title || $slots.actions || $slots.title"
      class="h-card-head"
    >
      <h3 v-if="title">
        {{ title }}
      </h3>
      <slot name="title" />
      <div
        v-if="$slots.actions"
        class="h-actions"
      >
        <slot name="actions" />
      </div>
    </div>
    <div class="h-card-body">
      <slot />
    </div>
  </section>
</template>
