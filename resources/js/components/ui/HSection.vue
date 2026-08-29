<script setup>
defineProps({
  title: String,
  copy: String,
})
</script>

<template>
  <section class="h-section">
    <header
      v-if="title || copy || $slots.actions"
      class="h-section-head"
    >
      <div>
        <h3
          v-if="title"
          class="h-section-title"
        >
          {{ title }}
        </h3>
        <p
          v-if="copy"
          class="h-section-copy"
        >
          {{ copy }}
        </p>
      </div>
      <div
        v-if="$slots.actions"
        class="h-actions"
      >
        <slot name="actions" />
      </div>
    </header>
    <slot />
  </section>
</template>
