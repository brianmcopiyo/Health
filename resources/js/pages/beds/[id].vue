<script setup>
import FacilityRecord from '@/components/hms/FacilityRecord.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'Bed',
  },
})
</script>

<template>
  <FacilityRecord kind="bed" />
</template>
