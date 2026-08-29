<script setup>
definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const ability = useAbility()
const stores = ref([])
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const form = ref({ name: '', code: '', type: 'warehouse', is_default: false })

const load = async () => {
  stores.value = asList(await $api('/inventory/stores'))
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/inventory/stores', { method: 'POST', body: form.value })
    formOpen.value = false
    await load()
  })
}

const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Stores"
      subtitle="Pharmacy, warehouse, and ward stock locations"
    >
      <HExportActions
        dataset="inventory-stores"
        :disabled="pending"
      />
      <HButton
        v-if="ability.can('create', 'Inventory')"
        @click="formOpen = true"
      >
        <HIcon name="plus" />
        Add store
      </HButton>
    </HPage>
    <HCard flush>
      <HTable
        :loading="pending"
        :headers="[{ title: 'Store', key: 'name' }, { title: 'Code', key: 'code' }, { title: 'Type', key: 'type' }]"
        :items="stores"
        empty="No stores"
      >
        <template #cell-name="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'inventory-stores-id', params: { id: item.id } }"
          >
            {{ item.name }}
          </RouterLink>
        </template>
      </HTable>
    </HCard>
    <HModal
      v-model="formOpen"
      title="Add store"
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
          v-model="form.code"
          label="Code"
          required
        />
        <HSelect
          v-model="form.type"
          :items="[{ id: 'warehouse', name: 'Warehouse' }, { id: 'pharmacy', name: 'Pharmacy' }, { id: 'department', name: 'Department' }, { id: 'ward', name: 'Ward' }]"
          item-title="name"
          item-value="id"
          label="Type"
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
          :disabled="saving"
          @click="save"
        >
          Save
        </HButton>
      </template>
    </HModal>
  </div>
</template>
