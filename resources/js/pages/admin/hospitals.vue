<script setup>
definePage({
  meta: {
    action: 'manage',
    subject: 'Hospital',
  },
})

const hospitals = ref([])
const isDialogVisible = ref(false)
const editing = ref(null)
const form = ref({
  name: '',
  code: '',
  city: '',
  region: '',
  phone: '',
  email: '',
  address: '',
  is_active: true,
})

const headers = [
  { title: 'Hospital', key: 'name' },
  { title: 'Code', key: 'code' },
  { title: 'City', key: 'city' },
  { title: 'Region', key: 'region' },
  { title: 'Active', key: 'is_active' },
  { title: 'Actions', key: 'actions' },
]

const load = async () => {
  hospitals.value = asList(await $api('/hospitals'))
}

const openCreate = () => {
  editing.value = null
  form.value = {
    name: '',
    code: '',
    city: '',
    region: '',
    phone: '',
    email: '',
    address: '',
    is_active: true,
  }
  isDialogVisible.value = true
}

const openEdit = item => {
  editing.value = item
  form.value = { ...item }
  isDialogVisible.value = true
}

const save = async () => {
  if (editing.value)
    await $api(`/hospitals/${editing.value.id}`, { method: 'PUT', body: form.value })
  else
    await $api('/hospitals', { method: 'POST', body: form.value })

  isDialogVisible.value = false
  await load()
}

await withPageLoad(load)
</script>

<template>
  <div>
    <HPage
      title="Hospitals"
      subtitle="Network hospital registry"
    >
      <HButton @click="openCreate">
        <HIcon name="plus" />
        Add hospital
      </HButton>
    </HPage>

    <HCard>
      <HTable
        :headers="headers"
        :items="hospitals"
        empty="No hospitals registered"
      >
        <template #cell-is_active="{ item }">
          <HBadge :tone="item.is_active ? 'success' : 'secondary'">
            {{ item.is_active ? 'Active' : 'Inactive' }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <HButton
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
      :title="editing ? 'Update hospital' : 'Add hospital'"
    >
      <div class="h-stack">
        <HInput
          v-model="form.name"
          label="Name"
        />
        <HInput
          v-model="form.code"
          label="Code"
        />
        <HInput
          v-model="form.city"
          label="City"
        />
        <HInput
          v-model="form.region"
          label="Region"
        />
        <HInput
          v-model="form.phone"
          label="Phone"
        />
        <HInput
          v-model="form.email"
          label="Email"
        />
        <HInput
          v-model="form.address"
          label="Address"
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
