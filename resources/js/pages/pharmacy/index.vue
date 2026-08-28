<script setup>
import FacilityBoard from '@/components/hms/FacilityBoard.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'Pharmacy',
  },
})
</script>

<template>
  <FacilityBoard
    module-key="pharmacy"
    title="Pharmacy"
    subject="Pharmacy"
  />
</template>
