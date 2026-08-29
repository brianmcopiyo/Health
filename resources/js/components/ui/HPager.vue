<script setup>
defineProps({
  meta: {
    type: Object,
    default: () => ({ current_page: 1, last_page: 1, total: 0 }),
  },
})

const emit = defineEmits(['update:page'])
</script>

<template>
  <div
    v-if="meta.last_page > 1"
    class="h-pager"
  >
    <HButton
      variant="ghost"
      size="sm"
      :disabled="meta.current_page <= 1"
      @click="emit('update:page', meta.current_page - 1)"
    >
      Previous
    </HButton>
    <span>Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} records</span>
    <HButton
      variant="ghost"
      size="sm"
      :disabled="meta.current_page >= meta.last_page"
      @click="emit('update:page', meta.current_page + 1)"
    >
      Next
    </HButton>
  </div>
</template>
