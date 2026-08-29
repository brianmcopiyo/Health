<script setup>
import AccessUserList from '@/components/access/AccessUserList.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'User',
  },
})

const ability = useAbility()
const hospitals = ref([])

const loadHospitals = async () => {
  if (ability.can('manage', 'Hospital'))
    hospitals.value = asList(await $api('/hospitals'))
}

loadHospitals()
</script>

<template>
  <AccessUserList
    subtitle="Hospital access and role assignment"
    empty="No users in this hospital"
    default-role-slug="nurse"
    name-placeholder="e.g. Grace Adeyemi"
    email-placeholder="e.g. nurse@hospital.org"
    :extra-headers="[{ title: 'Hospital', key: 'hospital.name' }]"
  >
    <template #form-extra="{ form, ability: can }">
      <HSelect
        v-if="can.can('manage', 'Hospital')"
        v-model="form.hospital_id"
        :items="hospitals"
        item-title="name"
        item-value="id"
        label="Hospital"
      />
    </template>
    <template #cell-hospital.name="{ item }">
      {{ item.hospital?.name || '—' }}
    </template>
  </AccessUserList>
</template>
