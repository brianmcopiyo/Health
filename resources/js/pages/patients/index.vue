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
const page = ref(1)
const search = ref('')
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
  const payload = await $api('/patients', { query: { page: page.value, q: search.value || undefined } })
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
        :query="{ q: search || undefined }"
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
        v-model:search="search"
        search-placeholder="Search MRN, name or phone"
        search-button
        @search="page = 1; load()"
      />
      <HTable
        :loading="pending"
        :headers="[
          { title: 'MRN', key: 'mrn' },
          { title: 'Name', key: 'full_name' },
          { title: 'Sex', key: 'sex' },
          { title: 'Phone', key: 'phone' },
          { title: 'Status', key: 'status' },
          { title: 'Actions', key: 'actions' },
        ]"
        :items="patients"
        empty="No patients registered yet"
      >
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-actions="{ item }">
          <div class="h-actions">
            <HButton
              variant="ghost"
              size="icon"
              :to="{ name: 'patients-id', params: { id: item.id } }"
            >
              <HIcon name="eye" />
            </HButton>
            <HButton
              v-if="ability.can('update', 'Patient')"
              variant="ghost"
              size="icon"
              @click="openEdit(item)"
            >
              <HIcon name="edit" />
            </HButton>
          </div>
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => { page = value; load() }"
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
          :disabled="saving"
          @click="save"
        >
          Save
        </HButton>
      </template>
    </HModal>
  </div>
</template>
