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
const departments = ref([])
const catalog = ref([])
const form = ref({ store_id: null, department_id: null, kind: 'department', items: [{ item_id: null, quantity: 1 }] })

const load = async () => {
  const payload = await $api('/inventory/issues', { query: { page: page.value } })
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

const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage title="Department issues" subtitle="Issue stock to wards and departments">
      <HButton
        v-if="ability.can('create', 'Inventory')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Issue stock
      </HButton>
    </HPage>
    <HCard flush>
      <HTable
        :loading="pending"
        :headers="[{ title: 'Issue', key: 'reference' }, { title: 'Store', key: 'store.name' }, { title: 'When', key: 'occurred_at' }]"
        :items="rows"
        empty="No issues"
      >
        <template #cell-reference="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'inventory-issues-id', params: { id: item.id } }"
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
          :disabled="saving"
          @click="save"
        >
          Issue
        </HButton>
      </template>
    </HOffcanvas>
  </div>
</template>
