<script setup>
import FacilityBoard from '@/components/hms/FacilityBoard.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'Bed',
  },
})
</script>

<template>
  <FacilityBoard
    module-key="beds"
    title="Beds"
    subject="Bed"
  />
</template>
