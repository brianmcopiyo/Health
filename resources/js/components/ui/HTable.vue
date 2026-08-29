<script setup>
import { getByPath } from '@/utils/helpers'

defineProps({
  headers: { type: Array, default: () => [] },
  items: { type: Array, default: () => [] },
  empty: { type: String, default: 'No records yet' },
})

const fitKeys = new Set([
  'actions',
  'status',
  'code',
  'sex',
  'type',
  'phone',
  'mrn',
  'number',
  'capacity',
  'current_utilization',
  'remaining_capacity',
  'remaining',
  'stock_qty',
  'reorder_level',
  'strength',
  'total',
  'utilization',
  'quantity',
  'workspace',
  'is_active',
  'facilities_count',
  'dispatched_at',
])

const fillKeys = new Set([
  'patient_name',
  'full_name',
  'first_name',
  'name',
  'title',
  'reason',
  'chief_complaint',
  'item_name',
  'address',
  'description',
  'email',
  'origin',
  'destination',
])

const numKeys = new Set([
  'capacity',
  'current_utilization',
  'remaining_capacity',
  'remaining',
  'stock_qty',
  'reorder_level',
  'total',
  'utilization',
  'quantity',
  'facilities_count',
])

const leaf = header => String(header.key || '').split('.').pop()

const colClass = header => {
  const last = leaf(header)
  const classes = []
  if (header.align === 'end' || last === 'actions')
    classes.push('is-end')
  if (header.align === 'num' || header.numeric || numKeys.has(last))
    classes.push('is-num')
  const fit = header.fit === true || (header.fit !== false && (last === 'actions' || fitKeys.has(last)))
  const fill = header.fill === true || (!fit && header.fill !== false && fillKeys.has(last))
  if (fill)
    classes.push('is-fill')
  else if (fit)
    classes.push('is-fit')
  return classes
}
</script>

<template>
  <div class="h-table-wrap">
    <table class="h-table">
      <thead>
        <tr>
          <th
            v-for="header in headers"
            :key="header.key"
            :class="colClass(header)"
            :style="header.width ? { width: header.width } : undefined"
          >
            {{ header.title }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="!items.length">
          <td
            :colspan="headers.length"
            class="h-empty"
          >
            {{ empty }}
          </td>
        </tr>
        <tr
          v-for="(item, index) in items"
          :key="item.id ?? index"
        >
          <td
            v-for="header in headers"
            :key="header.key"
            :class="colClass(header)"
          >
            <slot
              :name="'cell-' + header.key"
              :item="item"
            >
              {{ getByPath(item, header.key) }}
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
