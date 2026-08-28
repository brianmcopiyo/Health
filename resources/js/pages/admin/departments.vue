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
  <div>
    <HPage
      title="Departments"
      subtitle="Map hospital departments to modules"
    >
      <HButton
        v-if="ability.can('manage', 'Department')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Add department
      </HButton>
    </HPage>

    <HCard>
      <HTable
        :headers="[
          { title: 'Name', key: 'name' },
          { title: 'Module', key: 'module_key' },
          { title: 'Active', key: 'is_active' },
          { title: 'Facilities', key: 'facilities_count' },
          { title: 'Actions', key: 'actions' },
        ]"
        :items="departments"
        empty="No departments configured"
      >
        <template #cell-is_active="{ item }">
          {{ item.is_active ? 'Yes' : 'No' }}
        </template>
        <template #cell-actions="{ item }">
          <HButton
            v-if="ability.can('manage', 'Department')"
            variant="ghost"
            size="icon"
            @click="openEdit(item)"
          >
            <HIcon name="edit" />
          </HButton>
        </template>
      </HTable>
    </HCard>

    <HDialog
      v-model="isDialogVisible"
      :title="editing ? 'Update department' : 'Add department'"
    >
      <div class="h-stack">
        <HInput
          v-model="form.name"
          label="Name"
        />
        <HSelect
          v-model="form.module_key"
          :items="catalog"
          item-title="title"
          item-value="key"
          label="Module"
        />
        <label class="h-check">
          <input
            v-model="form.is_active"
            type="checkbox"
          >
          Active
        </label>
      </div>
      <template #actions>
        <HButton
          variant="ghost"
          @click="isDialogVisible = false"
        >
          Cancel
        </HButton>
        <HButton @click="save">
          Save
        </HButton>
      </template>
    </HDialog>
  </div>
</template>
