<script setup>
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const ability = useAbility()
const items = ref([])
const meta = ref(asPageMeta())
const page = ref(1)
const q = ref('')
const kind = ref(null)
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const categories = ref([])
const units = ref([])
const form = ref({ name: '', sku: '', kind: 'medicine', category_id: null, unit_id: null, reorder_level: 10, tracks_batch: true, tracks_expiry: true, is_controlled: false })

const load = async () => {
  const payload = await $api('/inventory/items', { query: { page: page.value, q: q.value || undefined, kind: kind.value || undefined } })
  items.value = asList(payload)
  meta.value = asPageMeta(payload)
}

const openCreate = async () => {
  formError.value = ''
  categories.value = asList(await $api('/inventory/categories'))
  units.value = asList(await $api('/inventory/units'))
  form.value = { name: '', sku: '', kind: 'medicine', category_id: categories.value[0]?.id || null, unit_id: units.value[0]?.id || null, reorder_level: 10, tracks_batch: true, tracks_expiry: true, is_controlled: false }
  formOpen.value = true
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/inventory/items', { method: 'POST', body: form.value })
    formOpen.value = false
    await load()
  })
}

const filterValues = computed({
  get: () => ({ kind: kind.value }),
  set: next => { kind.value = next.kind },
})

const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Inventory items"
      subtitle="Medicines, supplies, consumables, and equipment"
    >
      <HButton
        variant="ghost"
        to="inventory"
      >
        Dashboard
      </HButton>
      <HButton
        v-if="ability.can('create', 'Inventory')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Add item
      </HButton>
    </HPage>
    <HCard flush>
      <HListToolbar
        v-model:search="q"
        v-model:values="filterValues"
        search-placeholder="Search items"
        search-button
        :filters="[
          { key: 'kind', type: 'select', label: 'Type', placeholder: 'All types', items: [
            { id: 'medicine', name: 'Medicine' },
            { id: 'supply', name: 'Supply' },
            { id: 'consumable', name: 'Consumable' },
            { id: 'equipment', name: 'Equipment' },
          ], itemTitle: 'name', itemValue: 'id' },
        ]"
        @search="page = 1; load()"
        @change="page = 1; load()"
      />
      <HTable
        :loading="pending"
        :headers="[
          { title: 'Item', key: 'name' },
          { title: 'SKU', key: 'sku' },
          { title: 'Type', key: 'kind' },
          { title: 'On hand', key: 'stock_quantity' },
          { title: 'Status', key: 'status' },
        ]"
        :items="items"
        empty="No inventory items"
      >
        <template #cell-name="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'inventory-items-id', params: { id: item.id } }"
          >
            {{ item.name }}
          </RouterLink>
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => { page = value; load() }"
      />
    </HCard>
    <HOffcanvas
      v-model="formOpen"
      title="Add inventory item"
      size="lg"
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
          v-model="form.sku"
          label="SKU"
          required
        />
        <HSelect
          v-model="form.kind"
          :items="[{ id: 'medicine', name: 'Medicine' }, { id: 'supply', name: 'Supply' }, { id: 'consumable', name: 'Consumable' }, { id: 'equipment', name: 'Equipment' }]"
          item-title="name"
          item-value="id"
          label="Type"
        />
        <HSelect
          v-model="form.category_id"
          :items="categories"
          item-title="name"
          item-value="id"
          label="Category"
        />
        <HSelect
          v-model="form.unit_id"
          :items="units"
          item-title="name"
          item-value="id"
          label="Unit"
        />
        <HNumber
          v-model="form.reorder_level"
          label="Reorder level"
          :min="0"
        />
        <HSwitch
          v-model="form.tracks_batch"
          label="Batch tracking"
        />
        <HSwitch
          v-model="form.tracks_expiry"
          label="Expiry tracking"
        />
        <HSwitch
          v-model="form.is_controlled"
          label="Controlled item"
        />
      </HFormGrid>
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
    </HOffcanvas>
  </div>
</template>
