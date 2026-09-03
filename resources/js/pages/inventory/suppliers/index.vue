<script setup>
definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const ability = useAbility()
const rows = ref([])
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const form = ref({ name: '', phone: '', email: '' })

const load = async () => {
  rows.value = asList(await $api('/inventory/suppliers'))
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/inventory/suppliers', { method: 'POST', body: form.value })
    formOpen.value = false
    form.value = { name: '', phone: '', email: '' }
    await load()
  })
}

const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Suppliers"
      subtitle="Medical stores and vendors"
    >
      <HExportActions
        dataset="inventory-suppliers"
        :disabled="pending"
      />
      <HButton
        v-if="ability.can('create', 'Inventory')"
        @click="formOpen = true"
      >
        <HIcon name="plus" />
        Add supplier
      </HButton>
    </HPage>
    <HCard flush>
      <HTable
        :loading="pending"
        :headers="[{ title: 'Supplier', key: 'name', fill: true }]"
        :items="rows"
        empty="No suppliers"
      >
        <template #cell-name="{ item }">
          <HCell :secondary="joinContext(item.phone, item.email)">
            {{ item.name }}
          </HCell>
        </template>
      </HTable>
    </HCard>
    <HModal
      v-model="formOpen"
      title="Add supplier"
      :error="formError"
      :persistent="saving"
    >
      <HFormGrid>
        <HInput
          v-model="form.name"
          label="Name"
          required
        />
        <HInput
          v-model="form.phone"
          label="Phone"
        />
        <HInput
          v-model="form.email"
          label="Email"
        />
      </HFormGrid>
      <template #actions>
        <HButton
          variant="ghost"
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
