<script setup>
import FacilityBoard from '@/components/hms/FacilityBoard.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'Theatre',
  },
})
</script>

<template>
  <FacilityBoard
    module-key="theatre"
    title="Theatre"
    subject="Theatre"
  />
</template>
