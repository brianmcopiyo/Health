<script setup>
import { useExport } from '@/composables/useExport'

const props = defineProps({
  dataset: { type: String, required: true },
  query: { type: Object, default: () => ({}) },
  disabled: Boolean,
})

const { exporting, exportDataset } = useExport()
</script>

<template>
  <HButton
    variant="ghost"
    :disabled="!!exporting || disabled"
    @click="exportDataset(props.dataset, props.query, 'pdf')"
  >
    <HIcon name="receipt" />
    Export PDF
  </HButton>
  <HButton
    variant="ghost"
    :disabled="!!exporting || disabled"
    @click="exportDataset(props.dataset, props.query, 'xlsx')"
  >
    <HIcon name="download" />
    Export Excel
  </HButton>
</template>
