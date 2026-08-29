<script setup>
definePage({
  meta: {
    action: 'read',
    subject: 'PriceList',
  },
})

const route = useRoute()
const ability = useAbility()
const record = ref(null)
const services = ref([])
const itemOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const itemForm = ref({ billable_type: 'service', billable_id: null, min_quantity: 1, unit_price: 0 })

const load = async () => {
  record.value = await $api(`/price-lists/${route.params.id}`)
}

const openItem = async () => {
  services.value = asList(await $api('/pricing/services'))
  itemForm.value = { billable_type: 'service', billable_id: null, min_quantity: 1, unit_price: 0 }
  formError.value = ''
  itemOpen.value = true
}

const saveItem = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/price-lists/${record.value.id}/items`, { method: 'POST', body: itemForm.value })
    itemOpen.value = false
    await load()
  })
}

const { pending, run } = usePageQuery(load)
watch(() => route.params.id, () => run())
</script>

<template>
  <HRecord
    :title="record?.name || 'Price list'"
    :subtitle="record?.kind"
    :back="{ name: 'pricing' }"
    back-label="Pricing"
    :loading="pending"
    :missing="!pending && !record"
  >
    <HCard
      v-if="record"
      title="Prices"
      flush
    >
      <template
        v-if="ability.can('update', 'PriceList')"
        #actions
      >
        <HButton
          size="sm"
          @click="openItem"
        >
          Add price
        </HButton>
      </template>
      <HTable
        :headers="[
          { title: 'Type', key: 'billable_type' },
          { title: 'Min qty', key: 'min_quantity' },
          { title: 'Price', key: 'unit_price' },
        ]"
        :items="record.items || []"
        empty="No prices on this list"
      />
    </HCard>

    <HModal
      v-model="itemOpen"
      title="Add price"
      :error="formError"
      :persistent="saving"
    >
      <HSelect
        v-model="itemForm.billable_id"
        :items="services"
        item-title="name"
        item-value="id"
        label="Service"
        required
      />
      <HNumber
        v-model="itemForm.min_quantity"
        label="Min quantity"
      />
      <HNumber
        v-model="itemForm.unit_price"
        label="Unit price"
        required
      />
      <template #actions>
        <HButton
          variant="ghost"
          @click="itemOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving"
          @click="saveItem"
        >
          Save
        </HButton>
      </template>
    </HModal>
  </HRecord>
</template>
