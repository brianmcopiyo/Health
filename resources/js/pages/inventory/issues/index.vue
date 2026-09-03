<script setup>
import { formatWhen } from '@/utils/helpers'

definePage({
  meta: { action: 'read', subject: 'Inventory' },
})

const ability = useAbility()
const rows = ref([])
const meta = ref(asPageMeta())
const stores = ref([])
const departments = ref([])
const list = useListQuery(['store_id', 'department_id', 'kind', 'from', 'to'])
const { page, q, filterValues } = list
const formOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const catalog = ref([])
const form = ref({ store_id: null, department_id: null, kind: 'department', items: [{ item_id: null, quantity: 1 }] })

const load = async () => {
  stores.value = asList(await $api('/inventory/stores'))
  departments.value = asList(await $api('/departments').catch(() => []))
  const payload = await $api('/inventory/issues', { query: list.apiQuery() })
  rows.value = asList(payload)
  meta.value = asPageMeta(payload)
}

const openCreate = async () => {
  formError.value = ''
  stores.value = asList(await $api('/inventory/stores'))
  departments.value = asList(await $api('/departments').catch(() => []))
  catalog.value = asList(await $api('/inventory/items', { query: { per_page: 100 } }))
  form.value = { store_id: stores.value.find(item => item.is_default)?.id || null, department_id: null, kind: 'department', items: [{ item_id: null, quantity: 1 }] }
  formOpen.value = true
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/inventory/issues', { method: 'POST', body: { ...form.value, items: form.value.items.filter(item => item.item_id) } })
    formOpen.value = false
    await load()
  })
}

list.sync(load)
const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage title="Department issues" subtitle="Issue stock to wards and departments">
      <HExportActions
        dataset="inventory-issues"
        :query="list.apiQuery()"
        :disabled="pending"
      />
      <HButton
        v-if="ability.can('create', 'Inventory')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Issue stock
      </HButton>
    </HPage>
    <HCard flush>
      <HListToolbar
        v-model:search="q"
        v-model:values="filterValues"
        search-placeholder="Search issues"
        search-button
        :result-count="list.resultCount(meta)"
        :filters="[
          { key: 'store_id', type: 'select', label: 'Store', placeholder: 'All stores', items: stores, itemTitle: 'name', itemValue: 'id', optional: true, empty: null },
          { key: 'kind', type: 'select', label: 'Kind', placeholder: 'All kinds', optional: true, empty: null, more: true, items: [
            { title: 'Department', value: 'department' },
            { title: 'Ward', value: 'ward' },
            { title: 'Dispense', value: 'dispense' },
          ] },
          { key: 'department_id', type: 'select', label: 'Department', placeholder: 'All departments', items: departments, itemTitle: 'name', itemValue: 'id', optional: true, empty: null, more: true },
          { key: 'from', type: 'date', label: 'From', optional: true, empty: null, more: true },
          { key: 'to', type: 'date', label: 'To', optional: true, empty: null, more: true },
        ]"
        @search="list.onSearch(load)"
        @change="list.onChange(load)"
      />
      <HTable
        :loading="pending"
        :headers="[{ title: 'Issue', key: 'reference', fill: true }, { title: 'When', key: 'occurred_at' }]"
        :items="rows"
        empty="No issues"
      >
        <template #cell-reference="{ item }">
          <HCell
            :to="{ name: 'inventory-issues-id', params: { id: item.id } }"
            :secondary="joinContext(item.store?.name, item.department?.name)"
          >
            {{ item.reference }}
          </HCell>
        </template>
        <template #cell-occurred_at="{ item }">
          {{ formatWhen(item.occurred_at) }}
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => list.onPage(value, load)"
      />
    </HCard>
    <HOffcanvas
      v-model="formOpen"
      title="Issue stock"
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
          label="From store"
        />
        <HSelect
          v-model="form.department_id"
          :items="departments"
          item-title="name"
          item-value="id"
          label="Department"
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
          :loading="saving"
          :disabled="saving"
          @click="save"
        >
          Issue
        </HButton>
      </template>
    </HOffcanvas>
  </div>
</template>
