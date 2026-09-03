<script setup>
definePage({
  meta: {
    action: 'manage',
    subject: 'Hospital',
  },
})

const userData = useCookie('userData')
const all = ref([])
const list = useListQuery(['is_active'])
const { q, filterValues } = list
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const editing = ref(null)
const removing = ref(null)
const form = ref({
  name: '',
  city: '',
  region: '',
  phone: '',
  email: '',
  address: '',
  is_active: true,
})

const headers = [
  { title: 'Hospital', key: 'name', fill: true },
  { title: 'Active', key: 'is_active' },
  { title: 'Actions', key: 'actions' },
]

const hospitals = computed(() => {
  const term = String(q.value || '').trim().toLowerCase()
  const active = list.values.is_active
  return all.value.filter(item => {
    if (term && !`${item.name || ''} ${item.city || ''} ${item.region || ''}`.toLowerCase().includes(term))
      return false
    if (active === '1')
      return Boolean(item.is_active)
    if (active === '0')
      return !item.is_active
    return true
  })
})

const load = async () => {
  all.value = asList(await $api('/hospitals'))
}

const openCreate = () => {
  formError.value = ''
  editing.value = null
  form.value = {
    name: '',
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

list.sync(load)
const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Hospitals"
      subtitle="Network hospital registry"
    >
      <HExportActions
        dataset="hospitals"
        :disabled="pending"
      />
      <HButton @click="openCreate">
        <HIcon name="plus" />
        Add hospital
      </HButton>
    </HPage>

    <HCard flush>
      <HListToolbar
        v-model:search="q"
        v-model:values="filterValues"
        search-placeholder="Search hospitals"
        search-button
        :result-count="list.resultCount({ total: hospitals.length })"
        :filters="[
          { key: 'is_active', type: 'select', label: 'Status', placeholder: 'All statuses', optional: true, empty: null, items: [
            { title: 'Active', value: '1' },
            { title: 'Inactive', value: '0' },
          ] },
        ]"
        @search="list.onSearch(load)"
        @change="list.onChange(load)"
      />
      <HTable
        :loading="pending"
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
          <HCell
            :to="{ name: 'admin-hospitals-id', params: { id: item.id } }"
            :secondary="joinContext(item.city, item.region)"
          >
            {{ item.name }}
          </HCell>
        </template>
        <template #cell-actions="{ item }">
          <HActionMenu
            :actions="[
              { label: 'View', icon: 'eye', to: { name: 'admin-hospitals-id', params: { id: item.id } } },
              { label: 'Edit', icon: 'edit', onSelect: () => openEdit(item) },
              { label: 'Remove', icon: 'trash', danger: true, if: userData?.role === 'platform-admin', onSelect: () => { formError = ''; removing = item } },
            ]"
          />
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
        class="h-form-grid"
        :disabled="saving"
      >
        <HInput
          v-model="form.name"
          label="Name"
          placeholder="e.g. Riverside General"
          required
        />
        <HInput
          v-model="form.city"
          label="City"
          placeholder="e.g. Accra"
        />
        <HInput
          v-model="form.region"
          label="Region"
          placeholder="e.g. Greater Accra"
        />
        <HInput
          v-model="form.phone"
          label="Phone"
          type="tel"
          icon="phone"
          placeholder="e.g. 030 255 0100"
        />
        <HInput
          v-model="form.email"
          label="Email"
          type="email"
          icon="mail"
          placeholder="e.g. info@hospital.org"
        />
        <HTextarea
          span
          v-model="form.address"
          label="Address"
          placeholder="Street, city or area"
        />
        <HSwitch
          span
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
          :loading="saving"
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
          :loading="saving"
          :disabled="saving"
          @click="removeHospital"
        >
          Remove
        </HButton>
      </template>
    </HModal>
  </div>
</template>
