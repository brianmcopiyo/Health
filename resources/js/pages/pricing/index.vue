<script setup>
import { labelize } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'PriceList',
  },
})

const ability = useAbility()
const list = useListQuery(['tab', 'kind', 'type', 'is_active'])
const { page, q, filterValues } = list
if (!list.values.tab)
  list.values.tab = 'lists'
const lists = ref([])
const rules = ref([])
const packages = ref([])
const meta = ref(asPageMeta())
const listOpen = ref(false)
const ruleOpen = ref(false)
const packageOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const listForm = ref({ name: '', kind: 'self_pay', tax_inclusive: false, is_default: false })
const ruleForm = ref({ name: '', type: 'discount_percent', scope: 'service', value: 0, is_active: true })
const packageForm = ref({ name: '', unit_price: 0 })
const tab = computed(() => list.values.tab || 'lists')

const filters = computed(() => {
  const items = [
    { key: 'tab', type: 'segmented', empty: 'lists', clearable: false, options: [
      { title: 'Lists', value: 'lists' },
      { title: 'Rules', value: 'rules' },
      { title: 'Packages', value: 'packages' },
    ] },
  ]
  if (tab.value === 'lists') {
    items.push(
      { key: 'kind', type: 'select', label: 'Kind', placeholder: 'All kinds', optional: true, empty: null, items: [
        { title: 'Self pay', value: 'self_pay' },
        { title: 'Insurance', value: 'insurance' },
        { title: 'Patient', value: 'customer' },
        { title: 'Promotional', value: 'promotional' },
        { title: 'Department', value: 'department' },
      ] },
      { key: 'is_active', type: 'select', label: 'Status', placeholder: 'All statuses', optional: true, empty: null, more: true, items: [
        { title: 'Active', value: '1' },
        { title: 'Inactive', value: '0' },
      ] },
    )
  }
  else if (tab.value === 'rules') {
    items.push(
      { key: 'type', type: 'select', label: 'Type', placeholder: 'All types', optional: true, empty: null, items: [
        { title: 'Percent discount', value: 'discount_percent' },
        { title: 'Fixed discount', value: 'discount_fixed' },
        { title: 'Override', value: 'override' },
        { title: 'Promotional', value: 'promotional' },
      ] },
      { key: 'is_active', type: 'select', label: 'Status', placeholder: 'All statuses', optional: true, empty: null, more: true, items: [
        { title: 'Active', value: '1' },
        { title: 'Inactive', value: '0' },
      ] },
    )
  }
  else {
    items.push(
      { key: 'is_active', type: 'select', label: 'Status', placeholder: 'All statuses', optional: true, empty: null, items: [
        { title: 'Active', value: '1' },
        { title: 'Inactive', value: '0' },
      ] },
    )
  }
  return items
})

const pricingQuery = extra => {
  const query = list.apiQuery(extra)
  delete query.tab
  return query
}

const load = async () => {
  if (tab.value === 'lists') {
    const payload = await $api('/price-lists', { query: pricingQuery() })
    lists.value = asList(payload)
    meta.value = asPageMeta(payload)
  }
  else if (tab.value === 'rules') {
    const payload = await $api('/pricing-rules', { query: pricingQuery() })
    rules.value = asList(payload)
    meta.value = asPageMeta(payload)
  }
  else {
    const payload = await $api('/service-packages', { query: pricingQuery() })
    packages.value = asList(payload)
    meta.value = asPageMeta(payload)
  }
}

const saveList = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/price-lists', { method: 'POST', body: listForm.value })
    listOpen.value = false
    await load()
  })
}

const saveRule = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/pricing-rules', { method: 'POST', body: ruleForm.value })
    ruleOpen.value = false
    await load()
  })
}

const savePackage = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/service-packages', { method: 'POST', body: packageForm.value })
    packageOpen.value = false
    await load()
  })
}

list.sync(load)
const { pending } = usePageQuery(load)
</script>

<template>
  <div>
    <HPage
      title="Pricing"
      subtitle="Hospital price lists, rules, and service packages"
    >
      <HButton
        v-if="tab === 'lists' && ability.can('create', 'PriceList')"
        @click="formError = ''; listOpen = true"
      >
        <HIcon name="plus" />
        New list
      </HButton>
      <HButton
        v-else-if="tab === 'rules' && ability.can('create', 'PriceList')"
        @click="formError = ''; ruleOpen = true"
      >
        <HIcon name="plus" />
        New rule
      </HButton>
      <HButton
        v-else-if="tab === 'packages' && ability.can('create', 'PriceList')"
        @click="formError = ''; packageOpen = true"
      >
        <HIcon name="plus" />
        New package
      </HButton>
    </HPage>

    <HCard flush>
      <HListToolbar
        v-model:search="q"
        v-model:values="filterValues"
        :filters="filters"
        search-placeholder="Search pricing"
        search-button
        :result-count="list.resultCount(meta)"
        @search="list.onSearch(load)"
        @change="list.onChange(load)"
      />
      <HTable
        v-if="tab === 'lists'"
        :loading="pending"
        :headers="[
          { title: 'Name', key: 'name', fill: true },
          { title: 'Items', key: 'items_count' },
        ]"
        :items="lists"
        empty="No price lists yet"
      >
        <template #cell-name="{ item }">
          <HCell
            :to="{ name: 'pricing-id', params: { id: item.id } }"
            :secondary="labelize(item.kind)"
          >
            {{ item.name }}
          </HCell>
        </template>
      </HTable>
      <HTable
        v-else-if="tab === 'rules'"
        :loading="pending"
        :headers="[
          { title: 'Name', key: 'name', fill: true },
          { title: 'Value', key: 'value' },
        ]"
        :items="rules"
        empty="No rules yet"
      >
        <template #cell-name="{ item }">
          <HCell :secondary="labelize(item.type)">
            {{ item.name }}
          </HCell>
        </template>
      </HTable>
      <HTable
        v-else
        :loading="pending"
        :headers="[
          { title: 'Name', key: 'name', fill: true },
          { title: 'Price', key: 'unit_price' },
        ]"
        :items="packages"
        empty="No packages yet"
      >
        <template #cell-name="{ item }">
          <HCell>
            {{ item.name }}
          </HCell>
        </template>
      </HTable>
      <HPager
        :meta="meta"
        @update:page="value => list.onPage(value, load)"
      />
    </HCard>

    <HModal
      v-model="listOpen"
      title="Price list"
      :error="formError"
      :persistent="saving"
    >
      <HInput
        v-model="listForm.name"
        label="Name"
        required
      />
      <HSelect
        v-model="listForm.kind"
        :items="[
          { title: 'Self pay', value: 'self_pay' },
          { title: 'Insurance', value: 'insurance' },
          { title: 'Patient', value: 'customer' },
          { title: 'Promotional', value: 'promotional' },
        ]"
        item-title="title"
        item-value="value"
        label="Kind"
        :clearable="false"
      />
      <template #actions>
        <HButton
          variant="ghost"
          @click="listOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :loading="saving"
          :disabled="saving"
          @click="saveList"
        >
          Save
        </HButton>
      </template>
    </HModal>

    <HModal
      v-model="ruleOpen"
      title="Pricing rule"
      :error="formError"
      :persistent="saving"
    >
      <HInput
        v-model="ruleForm.name"
        label="Name"
        required
      />
      <HSelect
        v-model="ruleForm.type"
        :items="[
          { title: 'Percent discount', value: 'discount_percent' },
          { title: 'Fixed discount', value: 'discount_fixed' },
          { title: 'Override', value: 'override' },
        ]"
        item-title="title"
        item-value="value"
        label="Type"
        :clearable="false"
      />
      <HNumber
        v-model="ruleForm.value"
        label="Value"
        required
      />
      <template #actions>
        <HButton
          variant="ghost"
          @click="ruleOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :loading="saving"
          :disabled="saving"
          @click="saveRule"
        >
          Save
        </HButton>
      </template>
    </HModal>

    <HModal
      v-model="packageOpen"
      title="Service package"
      :error="formError"
      :persistent="saving"
    >
      <HInput
        v-model="packageForm.name"
        label="Name"
        required
      />
      <HNumber
        v-model="packageForm.unit_price"
        label="Package price"
        required
      />
      <template #actions>
        <HButton
          variant="ghost"
          @click="packageOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :loading="saving"
          :disabled="saving"
          @click="savePackage"
        >
          Save
        </HButton>
      </template>
    </HModal>
  </div>
</template>
