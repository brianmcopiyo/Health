<script setup>
definePage({
  meta: {
    action: 'read',
    subject: 'Department',
  },
})

const ability = useAbility()
const departments = ref([])
const catalog = ref([])
const isDialogVisible = ref(false)
const editing = ref(null)
const form = ref({
  name: '',
  module_key: 'reception',
  is_active: true,
})

const load = async () => {
  departments.value = asList(await $api('/departments'))
  catalog.value = asList(await $api('/modules/catalog'))
}

const openCreate = () => {
  editing.value = null
  form.value = { name: '', module_key: catalog.value[0]?.key || 'reception', is_active: true }
  isDialogVisible.value = true
}

const openEdit = item => {
  editing.value = item
  form.value = { name: item.name, module_key: item.module_key, is_active: item.is_active }
  isDialogVisible.value = true
}

const save = async () => {
  if (editing.value)
    await $api(`/departments/${editing.value.id}`, { method: 'PUT', body: form.value })
  else
    await $api('/departments', { method: 'POST', body: form.value })

  isDialogVisible.value = false
  await load()
}

await withPageLoad(load)
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Departments</VCardTitle>
      <template #append>
        <VBtn
          v-if="ability.can('manage', 'Department')"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          Add department
        </VBtn>
      </template>
    </VCardItem>
    <VDataTable
      :headers="[
        { title: 'Name', key: 'name' },
        { title: 'Module', key: 'module_key' },
        { title: 'Active', key: 'is_active' },
        { title: 'Facilities', key: 'facilities_count' },
        { title: 'Actions', key: 'actions', sortable: false },
      ]"
      :items="departments"
    >
      <template #item.is_active="{ item }">
        {{ item.is_active ? 'Yes' : 'No' }}
      </template>
      <template #item.actions="{ item }">
        <IconBtn
          v-if="ability.can('manage', 'Department')"
          @click="openEdit(item)"
        >
          <VIcon icon="tabler-edit" />
        </IconBtn>
      </template>
    </VDataTable>
  </VCard>

  <VDialog
    v-model="isDialogVisible"
    max-width="520"
  >
    <VCard :title="editing ? 'Update department' : 'Add department'">
      <VCardText>
        <AppTextField
          v-model="form.name"
          label="Name"
          class="mb-4"
        />
        <AppSelect
          v-model="form.module_key"
          :items="catalog"
          item-title="title"
          item-value="key"
          label="Module"
          class="mb-4"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          @click="isDialogVisible = false"
        >
          Cancel
        </VBtn>
        <VBtn @click="save">
          Save
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
