<script setup>
import { formatWhen } from '@/utils/helpers'
import { labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Inventory',
  },
})

const ability = useAbility()
const dash = ref(null)

const load = async () => {
  dash.value = await $api('/inventory/dashboard')
}

const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Inventory"
      subtitle="Medical stock, stores, and movements"
    >
      <HButton
        v-if="ability.can('read', 'Inventory')"
        variant="ghost"
        to="inventory-stock"
      >
        Stock
      </HButton>
      <HButton
        v-if="ability.can('create', 'Inventory')"
        to="inventory-receipts"
      >
        Receive
      </HButton>
    </HPage>

    <HGrid
      v-if="dash"
      cols="4"
      kind="stats"
    >
      <HStat
        icon="pill"
        title="Stock value"
        :value="dash.stock_value"
      />
      <HStat
        icon="flask"
        title="Items in stock"
        :value="dash.items_in_stock"
      />
      <HStat
        icon="bell"
        title="Low stock"
        :value="dash.low_stock?.count || 0"
        :tone="dash.low_stock?.count ? 'warn' : 'ok'"
      />
      <HStat
        icon="calendar"
        title="Expiring"
        :value="dash.expiring?.count || 0"
        :tone="dash.expiring?.count ? 'warn' : 'ok'"
      />
      <HStat
        icon="bell"
        title="Expired"
        :value="dash.expired?.count || 0"
        :tone="dash.expired?.count ? 'warn' : 'ok'"
      />
    </HGrid>

    <HCard
      title="Stock requiring attention"
      flush
    >
      <HTable
        :loading="pending"
        :headers="[
          { title: 'Item', key: 'name' },
          { title: 'On hand', key: 'stock_quantity' },
          { title: 'Reorder', key: 'reorder_level' },
        ]"
        :items="dash?.low_stock?.items || []"
        empty="No low-stock items"
      >
        <template #cell-name="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'inventory-items-id', params: { id: item.id } }"
          >
            {{ item.name }}
          </RouterLink>
        </template>
      </HTable>
    </HCard>

    <HCard
      title="Recent movements"
      flush
    >
      <HTable
        :loading="pending"
        :headers="[
          { title: 'When', key: 'occurred_at' },
          { title: 'Item', key: 'item.name' },
          { title: 'Type', key: 'type' },
          { title: 'Qty', key: 'quantity' },
        ]"
        :items="dash?.recent_movements || []"
        empty="No movements yet"
      >
        <template #cell-occurred_at="{ item }">
          {{ formatWhen(item.occurred_at) }}
        </template>
        <template #cell-type="{ item }">
          <HBadge :tone="statusColor(item.type)">
            {{ labelize(item.type) }}
          </HBadge>
        </template>
      </HTable>
    </HCard>

    <HCard
      title="Recent receipts"
      flush
    >
      <HTable
        :headers="[{ title: 'Receipt', key: 'reference' }, { title: 'Store', key: 'store.name' }, { title: 'When', key: 'received_at' }]"
        :items="dash?.recent_receipts || []"
        empty="No receipts yet"
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
    </HCard>

    <HCard
      title="Recent transfers"
      flush
    >
      <HTable
        :headers="[{ title: 'Transfer', key: 'reference' }, { title: 'From', key: 'from_store.name' }, { title: 'To', key: 'to_store.name' }]"
        :items="dash?.recent_transfers || []"
        empty="No transfers yet"
      >
        <template #cell-reference="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'inventory-transfers-id', params: { id: item.id } }"
          >
            {{ item.reference }}
          </RouterLink>
        </template>
      </HTable>
    </HCard>

    <div class="h-actions">
      <HButton
        variant="ghost"
        to="inventory-items"
      >
        Items
      </HButton>
      <HButton
        variant="ghost"
        to="inventory-categories"
      >
        Categories
      </HButton>
      <HButton
        variant="ghost"
        to="inventory-units"
      >
        Units
      </HButton>
      <HButton
        variant="ghost"
        to="inventory-suppliers"
      >
        Suppliers
      </HButton>
      <HButton
        variant="ghost"
        to="inventory-stores"
      >
        Stores
      </HButton>
      <HButton
        variant="ghost"
        to="inventory-locations"
      >
        Locations
      </HButton>
      <HButton
        variant="ghost"
        to="inventory-batches"
      >
        Batches
      </HButton>
      <HButton
        variant="ghost"
        to="inventory-movements"
      >
        Movements
      </HButton>
      <HButton
        variant="ghost"
        to="inventory-transfers"
      >
        Transfers
      </HButton>
      <HButton
        variant="ghost"
        to="inventory-issues"
      >
        Issues
      </HButton>
      <HButton
        variant="ghost"
        to="inventory-requests"
      >
        Requests
      </HButton>
      <HButton
        variant="ghost"
        to="inventory-returns"
      >
        Returns
      </HButton>
      <HButton
        variant="ghost"
        to="inventory-adjustments"
      >
        Adjustments
      </HButton>
      <HButton
        variant="ghost"
        to="inventory-counts"
      >
        Counts
      </HButton>
    </div>
  </div>
</template>
