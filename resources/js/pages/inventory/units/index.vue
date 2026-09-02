<script setup>
definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const ability = useAbility()
const rows = ref([])
const formOpen = ref(false)
const convertOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const form = ref({ name: '', symbol: '' })
const conversion = ref({ from_unit_id: null, to_unit_id: null, factor: 1 })

const load = async () => {
  rows.value = asList(await $api('/inventory/units'))
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/inventory/units', { method: 'POST', body: form.value })
    formOpen.value = false
    form.value = { name: '', symbol: '' }
    await load()
  })
}

const saveConversion = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/inventory/unit-conversions', { method: 'POST', body: conversion.value })
    convertOpen.value = false
    conversion.value = { from_unit_id: null, to_unit_id: null, factor: 1 }
    await load()
  })
}

const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Units"
      subtitle="Issue units and pack conversions"
    >
      <HExportActions
        dataset="inventory-units"
        :disabled="pending"
      />
      <HButton
        v-if="ability.can('create', 'Inventory')"
        variant="ghost"
        @click="convertOpen = true"
      >
        Add conversion
      </HButton>
      <HButton
        v-if="ability.can('create', 'Inventory')"
        @click="formOpen = true"
      >
        <HIcon name="plus" />
        Add unit
      </HButton>
    </HPage>
    <HCard flush>
      <HTable
        :loading="pending"
        :headers="[{ title: 'Unit', key: 'name' }, { title: 'Symbol', key: 'symbol' }]"
        :items="rows"
        empty="No units"
      />
    </HCard>
    <HModal
      v-model="formOpen"
      title="Add unit"
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
          v-model="form.symbol"
          label="Symbol"
          required
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
    <HModal
      v-model="convertOpen"
      title="Add conversion"
      :error="formError"
      :persistent="saving"
    >
      <HFormGrid>
        <HSelect
          v-model="conversion.from_unit_id"
          :items="rows"
          item-title="name"
          item-value="id"
          label="From"
        />
        <HSelect
          v-model="conversion.to_unit_id"
          :items="rows"
          item-title="name"
          item-value="id"
          label="To"
        />
        <HNumber
          v-model="conversion.factor"
          label="Factor"
          :min="0.000001"
        />
      </HFormGrid>
      <template #actions>
        <HButton
          variant="ghost"
          @click="convertOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :loading="saving"
          :disabled="saving"
          @click="saveConversion"
        >
          Save
        </HButton>
      </template>
    </HModal>
  </div>
</template>
