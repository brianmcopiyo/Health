<script setup>
import { formatWhen } from '@/utils/helpers'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const ability = useAbility()
const rows = ref([])
const meta = ref(asPageMeta())
const page = ref(1)
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const stores = ref([])
const catalog = ref([])
const suppliers = ref([])
const form = ref({ store_id: null, supplier_id: null, notes: '', items: [{ item_id: null, quantity: 1, batch_number: '', expiry_date: '' }] })

const load = async () => {
  const payload = await $api('/inventory/receipts', { query: { page: page.value } })
  rows.value = asList(payload)
  meta.value = asPageMeta(payload)
}

const openCreate = async () => {
  formError.value = ''
  stores.value = asList(await $api('/inventory/stores'))
  catalog.value = asList(await $api('/inventory/items', { query: { per_page: 100 } }))
  suppliers.value = asList(await $api('/inventory/suppliers'))
  form.value = { store_id: stores.value.find(item => item.is_default)?.id || stores.value[0]?.id || null, supplier_id: suppliers.value[0]?.id || null, notes: '', items: [{ item_id: null, quantity: 1, batch_number: '', expiry_date: '' }] }
  formOpen.value = true
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/inventory/receipts', { method: 'POST', body: { ...form.value, items: form.value.items.filter(item => item.item_id) } })
    formOpen.value = false
    await load()
  })
}

const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Goods receipts"
      subtitle="Receive stock into a store"
    >
      <HExportActions
        dataset="inventory-receipts"
        :disabled="pending"
      />
      <HButton
        v-if="ability.can('create', 'Inventory')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Receive stock
      </HButton>
    </HPage>
    <HCard flush>
      <HTable
        :loading="pending"
        :headers="[{ title: 'Receipt', key: 'reference' }, { title: 'Store', key: 'store.name' }, { title: 'When', key: 'received_at' }]"
        :items="rows"
        empty="No receipts"
      >
        <template #cell-reference="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'inventory-receipts-id', params: { id: item.id } }"
          >
            {{ item.reference }}
          </RouterLink>
        </template>
        <template #cell-received_at="{ item }">
          {{ formatWhen(item.received_at) }}
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => { page = value; load() }"
      />
    </HCard>
    <HOffcanvas
      v-model="formOpen"
      title="Receive stock"
      size="lg"
      :error="formError"
      :persistent="saving"
    >
      <HFormGrid>
        <HSelect
          v-model="form.store_id"
          :items="stores"
          item-title="name"
          item-value="id"
          label="Store"
        />
        <HSelect
          v-model="form.supplier_id"
          :items="suppliers"
          item-title="name"
          item-value="id"
          label="Supplier"
        />
      </HFormGrid>
      <div
        v-for="(line, index) in form.items"
        :key="index"
        class="h-form-grid"
      >
        <HSelect
          v-model="line.item_id"
          :items="catalog"
          item-title="name"
          item-value="id"
          label="Item"
        />
        <HNumber
          v-model="line.quantity"
          label="Quantity"
          :min="0.001"
        />
        <HInput
          v-model="line.batch_number"
          label="Batch"
        />
        <HDatePicker
          v-model="line.expiry_date"
          label="Expiry"
        />
      </div>
      <HButton
        variant="ghost"
        @click="form.items.push({ item_id: null, quantity: 1, batch_number: '', expiry_date: '' })"
      >
        Add line
      </HButton>
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
          Receive
        </HButton>
      </template>
    </HOffcanvas>
  </div>
</template>
