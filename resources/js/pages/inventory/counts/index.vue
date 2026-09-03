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
const form = ref({ store_id: null, items: [{ item_id: null, counted_quantity: 0 }] })

const load = async () => {
  const payload = await $api('/inventory/counts', { query: { page: page.value } })
  rows.value = asList(payload)
  meta.value = asPageMeta(payload)
}

const openCreate = async () => {
  formError.value = ''
  stores.value = asList(await $api('/inventory/stores'))
  catalog.value = asList(await $api('/inventory/items', { query: { per_page: 100 } }))
  form.value = { store_id: stores.value.find(item => item.is_default)?.id || null, items: [{ item_id: null, counted_quantity: 0 }] }
  formOpen.value = true
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/inventory/counts', { method: 'POST', body: { ...form.value, items: form.value.items.filter(item => item.item_id) } })
    formOpen.value = false
    await load()
  })
}

const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage title="Stock counts" subtitle="Physical counts and variance posting">
      <HExportActions
        dataset="inventory-counts"
        :disabled="pending"
      />
      <HButton
        v-if="ability.can('create', 'Inventory')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Post count
      </HButton>
    </HPage>
    <HCard flush>
      <HTable
        :loading="pending"
        :headers="[{ title: 'Count', key: 'reference', fill: true }, { title: 'When', key: 'counted_at' }]"
        :items="rows"
        empty="No counts"
      >
        <template #cell-reference="{ item }">
          <HCell
            :to="{ name: 'inventory-counts-id', params: { id: item.id } }"
            :secondary="item.store?.name"
          >
            {{ item.reference }}
          </HCell>
        </template>
        <template #cell-counted_at="{ item }">
          {{ formatWhen(item.counted_at) }}
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => { page = value; load() }"
      />
    </HCard>
    <HOffcanvas
      v-model="formOpen"
      title="Post stock count"
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
          v-model="line.counted_quantity"
          label="Counted quantity"
          :min="0"
        />
      </div>
      <HButton
        variant="ghost"
        @click="form.items.push({ item_id: null, counted_quantity: 0 })"
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
          Post
        </HButton>
      </template>
    </HOffcanvas>
  </div>
</template>
