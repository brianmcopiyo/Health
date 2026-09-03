<script setup>
import { bloodGroups, kinshipOptions, sexOptions } from '@/utils/clinicalOptions'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Patient',
  },
})

const ability = useAbility()
const patients = ref([])
const meta = ref(asPageMeta())
const list = useListQuery(['status', 'sex'])
const { page, q, filterValues } = list
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const editing = ref(null)
const form = ref({
  first_name: '',
  last_name: '',
  sex: null,
  date_of_birth: null,
  phone: '',
  address: '',
  mrn: '',
  blood_group: '',
  national_id: '',
  next_of_kin_name: '',
  next_of_kin_phone: '',
  next_of_kin_relation: '',
})

const load = async () => {
  const payload = await $api('/patients', { query: list.apiQuery() })
  patients.value = asList(payload)
  meta.value = asPageMeta(payload)
}

const openCreate = () => {
  formError.value = ''
  editing.value = null
  form.value = {
    first_name: '',
    last_name: '',
    sex: null,
    date_of_birth: null,
    phone: '',
    address: '',
    mrn: '',
    blood_group: '',
    national_id: '',
    next_of_kin_name: '',
    next_of_kin_phone: '',
    next_of_kin_relation: '',
  }
  formOpen.value = true
}

const openEdit = item => {
  formError.value = ''
  editing.value = item
  form.value = {
    first_name: item.first_name,
    last_name: item.last_name,
    sex: item.sex,
    date_of_birth: item.date_of_birth,
    phone: item.phone,
    address: item.address,
    mrn: item.mrn,
    blood_group: item.blood_group,
    national_id: item.national_id,
    next_of_kin_name: item.next_of_kin_name,
    next_of_kin_phone: item.next_of_kin_phone,
    next_of_kin_relation: item.next_of_kin_relation,
  }
  formOpen.value = true
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    if (editing.value)
      await $api(`/patients/${editing.value.id}`, { method: 'PUT', body: form.value })
    else
      await $api('/patients', { method: 'POST', body: form.value })

    formOpen.value = false
    await load()
  })
}

list.sync(load)
const { pending } = usePageQuery(load)

const today = new Date().toISOString().slice(0, 10)
</script>

<template>
  <div>
    <HPage
      title="Patients"
      subtitle="Hospital patient register"
    >
      <HExportActions
        dataset="patients"
        :query="list.apiQuery()"
        :disabled="pending"
      />
      <HButton
        v-if="ability.can('create', 'Patient')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Register patient
      </HButton>
    </HPage>

    <HCard flush>
      <HListToolbar
        v-model:search="q"
        v-model:values="filterValues"
        search-placeholder="Search MRN, name or phone"
        search-button
        :result-count="list.resultCount(meta)"
        :filters="[
          { key: 'status', type: 'select', label: 'Status', placeholder: 'All statuses', optional: true, empty: null, items: [
            { title: 'Active', value: 'active' },
            { title: 'Admitted', value: 'admitted' },
            { title: 'Discharged', value: 'discharged' },
            { title: 'Deceased', value: 'deceased' },
            { title: 'Transferred', value: 'transferred' },
          ] },
          { key: 'sex', type: 'select', label: 'Sex', placeholder: 'All', optional: true, empty: null, items: sexOptions, more: true },
        ]"
        @search="list.onSearch(load)"
        @change="list.onChange(load)"
      />
      <HTable
        :loading="pending"
        :headers="[
          { title: 'Name', key: 'full_name', fill: true },
          { title: 'Sex', key: 'sex' },
          { title: 'Status', key: 'status' },
          { title: 'Actions', key: 'actions' },
        ]"
        :items="patients"
        empty="No patients registered yet"
      >
        <template #cell-full_name="{ item }">
          <HCell
            :to="{ name: 'patients-id', params: { id: item.id } }"
            :secondary="joinContext(item.mrn, item.phone)"
          >
            {{ item.full_name }}
          </HCell>
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <HActionMenu
            :actions="[
              { label: 'View', icon: 'eye', to: { name: 'patients-id', params: { id: item.id } } },
              { label: 'Edit', icon: 'edit', if: ability.can('update', 'Patient'), onSelect: () => openEdit(item) },
            ]"
          />
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => list.onPage(value, load)"
      />
    </HCard>

    <HModal
      v-model="formOpen"
      :title="editing ? 'Update patient' : 'Register patient'"
      size="lg"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
        class="h-form-grid"
        :disabled="saving"
      >
        <HInput
          v-model="form.first_name"
          label="First name"
          placeholder="Enter first name"
          required
        />
        <HInput
          v-model="form.last_name"
          label="Last name"
          placeholder="Enter last name"
          required
        />
        <HRadioGroup
          v-model="form.sex"
          :items="sexOptions"
          label="Sex"
        />
        <HDatePicker
          v-model="form.date_of_birth"
          label="Date of birth"
          :max="today"
        />
        <HInput
          v-model="form.phone"
          label="Phone"
          type="tel"
          icon="phone"
          placeholder="e.g. 024 555 0100"
        />
        <HInput
          v-model="form.mrn"
          label="MRN"
          optional
          hint="Leave blank to generate automatically"
          placeholder="e.g. RGH-0042"
        />
        <HInput
          v-model="form.national_id"
          label="National ID"
          placeholder="e.g. GHA-123-456-789"
        />
        <HCombobox
          v-model="form.blood_group"
          :items="bloodGroups"
          label="Blood group"
          placeholder="Type or select blood group"
        />
        <HInput
          span
          v-model="form.address"
          label="Address"
          placeholder="Street, city or area"
        />
      </fieldset>
      <fieldset
        class="h-form-grid is-3"
        :disabled="saving"
      >
        <HInput
          v-model="form.next_of_kin_name"
          label="Next of kin"
          placeholder="Full name"
        />
        <HInput
          v-model="form.next_of_kin_phone"
          label="Next of kin phone"
          type="tel"
          icon="phone"
          placeholder="e.g. 024 555 0100"
        />
        <HCombobox
          v-model="form.next_of_kin_relation"
          :items="kinshipOptions"
          label="Relation"
          placeholder="e.g. Spouse"
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
    </HModal>
  </div>
</template>
