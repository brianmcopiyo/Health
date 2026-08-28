<script setup>
import FacilityBoard from '@/components/hms/FacilityBoard.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'Imaging',
  },
})
</script>

<template>
  <FacilityBoard
    module-key="imaging"
    title="Imaging"
    subject="Imaging"
  />
</template>
