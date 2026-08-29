<script setup>
definePage({
  meta: {
    action: 'manage',
    subject: 'Hospital',
  },
})

const userData = useCookie('userData')
const hospitals = ref([])
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const editing = ref(null)
const removing = ref(null)
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
  formError.value = ''
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
  formOpen.value = true
}

const openEdit = item => {
  formError.value = ''
  editing.value = item
  form.value = { ...item }
  formOpen.value = true
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    if (editing.value)
      await $api(`/hospitals/${editing.value.id}`, { method: 'PUT', body: form.value })
    else
      await $api('/hospitals', { method: 'POST', body: form.value })

    formOpen.value = false
    await load()
  })
}

const removeHospital = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/hospitals/${removing.value.id}`, { method: 'DELETE' })
    removing.value = null
    await load()
  })
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

    <HCard flush>
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
        <template #cell-name="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'admin-hospitals-id', params: { id: item.id } }"
          >
            {{ item.name }}
          </RouterLink>
        </template>
        <template #cell-actions="{ item }">
          <div class="h-actions">
            <HButton
              variant="ghost"
              size="sm"
              :to="{ name: 'admin-hospitals-id', params: { id: item.id } }"
            >
              View
            </HButton>
            <HButton
              variant="ghost"
              size="icon"
              @click="openEdit(item)"
            >
              <HIcon name="edit" />
            </HButton>
            <HButton
              v-if="userData?.role === 'platform-admin'"
              variant="ghost"
              size="sm"
              @click="formError = ''; removing = item"
            >
              Remove
            </HButton>
          </div>
        </template>
      </HTable>
    </HCard>

    <HOffcanvas
      v-model="formOpen"
      :title="editing ? 'Update hospital' : 'Add hospital'"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
        class="h-stack"
        :disabled="saving"
      >
        <HInput
          v-model="form.name"
          label="Name"
          required
        />
        <HInput
          v-model="form.code"
          label="Code"
          required
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
          type="tel"
          icon="phone"
        />
        <HInput
          v-model="form.email"
          label="Email"
          type="email"
          icon="mail"
        />
        <HTextarea
          v-model="form.address"
          label="Address"
        />
        <HSwitch
          v-model="form.is_active"
          label="Hospital is active"
          hint="Inactive hospitals cannot receive referrals"
        />
      </fieldset>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="formOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving"
          @click="save"
        >
          Save
        </HButton>
      </template>
    </HOffcanvas>

    <HModal
      :model-value="Boolean(removing)"
      title="Remove hospital"
      :error="formError"
      :persistent="saving"
      @update:model-value="val => { if (!val) removing = null }"
    >
      <p>Remove {{ removing?.name }} from the network?</p>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="removing = null"
        >
          Keep
        </HButton>
        <HButton
          variant="danger"
          :disabled="saving"
          @click="removeHospital"
        >
          Remove
        </HButton>
      </template>
    </HModal>
  </div>
</template>
