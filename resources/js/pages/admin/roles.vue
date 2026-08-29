<script setup>
import AccessRoleBoard from '@/components/access/AccessRoleBoard.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'Role',
  },
})
</script>

<template>
  <AccessRoleBoard
    workspaces-endpoint="/modules/workspaces"
    default-workspace="reception"
    name-placeholder="e.g. Charge nurse"
  />
</template>
