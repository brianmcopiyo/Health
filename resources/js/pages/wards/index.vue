<script setup>
import FacilityBoard from '@/components/hms/FacilityBoard.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'Ward',
  },
})
</script>

<template>
  <FacilityBoard
    module-key="wards"
    title="Wards"
    subject="Ward"
  />
</template>
