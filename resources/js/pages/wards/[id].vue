<script setup>
import FacilityRecord from '@/components/hms/FacilityRecord.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'Ward',
  },
})
</script>

<template>
  <FacilityRecord kind="ward" />
</template>
