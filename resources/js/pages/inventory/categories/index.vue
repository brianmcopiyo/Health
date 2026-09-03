<script setup>
definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const ability = useAbility()
const rows = ref([])
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const form = ref({ name: '', parent_id: null })

const load = async () => {
  rows.value = asList(await $api('/inventory/categories'))
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/inventory/categories', { method: 'POST', body: form.value })
    formOpen.value = false
    form.value = { name: '', parent_id: null }
    await load()
  })
}

const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Categories"
      subtitle="Medicines, supplies, consumables, and equipment"
    >
      <HExportActions
        dataset="inventory-categories"
        :disabled="pending"
      />
      <HButton
        v-if="ability.can('create', 'Inventory')"
        @click="formOpen = true"
      >
        <HIcon name="plus" />
        Add category
      </HButton>
    </HPage>
    <HCard flush>
      <HTable
        :loading="pending"
        :headers="[{ title: 'Category', key: 'name', fill: true }, { title: 'Items', key: 'items_count' }]"
        :items="rows"
        empty="No categories"
      >
        <template #cell-name="{ item }">
          <HCell :secondary="item.parent?.name">
            {{ item.name }}
          </HCell>
        </template>
      </HTable>
    </HCard>
    <HModal
      v-model="formOpen"
      title="Add category"
      :error="formError"
      :persistent="saving"
    >
      <HFormGrid>
        <HInput
          v-model="form.name"
          label="Name"
          required
        />
        <HSelect
          v-model="form.parent_id"
          :items="rows"
          item-title="name"
          item-value="id"
          label="Parent"
          clearable
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
