<script setup>
import FacilityBoard from '@/components/hms/FacilityBoard.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'Laboratory',
  },
})
</script>

<template>
  <FacilityBoard
    module-key="laboratory"
    title="Laboratory"
    subject="Laboratory"
  />
</template>
