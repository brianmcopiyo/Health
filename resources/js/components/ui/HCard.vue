<script setup>
defineProps({
  title: String,
})
</script>

<template>
  <section class="h-card">
    <div
      v-if="title || $slots.actions || $slots.title"
      class="h-card-head"
    >
      <h3 v-if="title">
        {{ title }}
      </h3>
      <slot name="title" />
      <div>
        <slot name="actions" />
      </div>
    </div>
    <div class="h-card-body">
      <slot />
    </div>
  </section>
</template>
