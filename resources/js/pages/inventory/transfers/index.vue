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
const form = ref({ from_store_id: null, to_store_id: null, items: [{ item_id: null, quantity: 1 }] })

const load = async () => {
  const payload = await $api('/inventory/transfers', { query: { page: page.value } })
  rows.value = asList(payload)
  meta.value = asPageMeta(payload)
}

const openCreate = async () => {
  formError.value = ''
  stores.value = asList(await $api('/inventory/stores'))
  catalog.value = asList(await $api('/inventory/items', { query: { per_page: 100 } }))
  form.value = { from_store_id: stores.value.find(item => item.is_default)?.id || null, to_store_id: stores.value.find(item => !item.is_default)?.id || null, items: [{ item_id: null, quantity: 1 }] }
  formOpen.value = true
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/inventory/transfers', { method: 'POST', body: { ...form.value, items: form.value.items.filter(item => item.item_id) } })
    formOpen.value = false
    await load()
  })
}

const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage title="Transfers" subtitle="Move stock between stores">
      <HButton
        v-if="ability.can('create', 'Inventory')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Transfer
      </HButton>
    </HPage>
    <HCard flush>
      <HTable
        :loading="pending"
        :headers="[{ title: 'Transfer', key: 'reference' }, { title: 'From', key: 'from_store.name' }, { title: 'To', key: 'to_store.name' }, { title: 'When', key: 'occurred_at' }]"
        :items="rows"
        empty="No transfers"
      >
        <template #cell-reference="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'inventory-transfers-id', params: { id: item.id } }"
          >
            {{ item.reference }}
          </RouterLink>
        </template>
        <template #cell-occurred_at="{ item }">
          {{ formatWhen(item.occurred_at) }}
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => { page = value; load() }"
      />
    </HCard>
    <HOffcanvas
      v-model="formOpen"
      title="Transfer stock"
      size="lg"
      :error="formError"
      :persistent="saving"
    >
      <HFormGrid>
        <HSelect
          v-model="form.from_store_id"
          :items="stores"
          item-title="name"
          item-value="id"
          label="From"
        />
        <HSelect
          v-model="form.to_store_id"
          :items="stores"
          item-title="name"
          item-value="id"
          label="To"
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
      </div>
      <HButton
        variant="ghost"
        @click="form.items.push({ item_id: null, quantity: 1 })"
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
          :disabled="saving"
          @click="save"
        >
          Transfer
        </HButton>
      </template>
    </HOffcanvas>
  </div>
</template>
