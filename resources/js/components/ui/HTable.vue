<script setup>
import { getByPath } from '@/utils/helpers'

defineProps({
  headers: { type: Array, default: () => [] },
  items: { type: Array, default: () => [] },
  empty: { type: String, default: 'No records yet' },
})
</script>

<template>
  <div class="h-table-wrap">
    <table class="h-table">
      <thead>
        <tr>
          <th
            v-for="header in headers"
            :key="header.key"
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
