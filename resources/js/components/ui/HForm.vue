<script setup>
const props = defineProps({
  wide: Boolean,
  loading: Boolean,
  fields: { type: Number, default: 6 },
})

defineEmits(['submit'])
const showSkel = useDelayedVisible(() => props.loading)
</script>

<template>
  <form
    class="h-form"
    :class="{ 'is-wide': wide }"
    @submit.prevent="$emit('submit')"
  >
    <div
      v-if="loading || showSkel"
      class="h-form-grid"
      :class="{ 'is-hold': !showSkel }"
    >
      <div
        v-for="n in fields"
        :key="n"
        class="h-field"
      >
        <span class="h-skeleton is-label" />
        <span class="h-skeleton is-control" />
      </div>
    </div>
    <template v-else>
      <slot />
      <div
        v-if="$slots.actions"
        class="h-form-actions"
      >
        <slot name="actions" />
      </div>
    </template>
  </form>
</template>
