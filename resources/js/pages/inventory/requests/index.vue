<script setup>
import { formatWhen } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

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
const form = ref({ to_store_id: null, items: [{ item_id: null, quantity: 1 }] })

const load = async () => {
  const payload = await $api('/inventory/requests', { query: { page: page.value } })
  rows.value = asList(payload)
  meta.value = asPageMeta(payload)
}

const openCreate = async () => {
  formError.value = ''
  stores.value = asList(await $api('/inventory/stores'))
  catalog.value = asList(await $api('/inventory/items', { query: { per_page: 100 } }))
  form.value = { to_store_id: stores.value.find(item => item.is_default)?.id || null, items: [{ item_id: null, quantity: 1 }] }
  formOpen.value = true
}

const save = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/inventory/requests', { method: 'POST', body: { ...form.value, items: form.value.items.filter(item => item.item_id) } })
    formOpen.value = false
    await load()
  })
}

const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage title="Stock requests" subtitle="Request stock from pharmacy or central store">
      <HButton
        v-if="ability.can('create', 'Inventory')"
        @click="openCreate"
      >
        <HIcon name="plus" />
        Request stock
      </HButton>
    </HPage>
    <HCard flush>
      <HTable
        :loading="pending"
        :headers="[{ title: 'Request', key: 'reference' }, { title: 'Store', key: 'to_store.name' }, { title: 'Status', key: 'status' }, { title: 'When', key: 'requested_at' }]"
        :items="rows"
        empty="No requests"
      >
        <template #cell-reference="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'inventory-requests-id', params: { id: item.id } }"
          >
            {{ item.reference }}
          </RouterLink>
        </template>
        <template #cell-status="{ item }">
          <HBadge :tone="statusColor(item.status)">
            {{ labelize(item.status) }}
          </HBadge>
        </template>
        <template #cell-requested_at="{ item }">
          {{ formatWhen(item.requested_at) }}
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => { page = value; load() }"
      />
    </HCard>
    <HOffcanvas
      v-model="formOpen"
      title="Request stock"
      :error="formError"
      :persistent="saving"
    >
      <HFormGrid>
        <HSelect
          v-model="form.to_store_id"
          :items="stores"
          item-title="name"
          item-value="id"
          label="Supplying store"
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
          Submit
        </HButton>
      </template>
    </HOffcanvas>
  </div>
</template>
