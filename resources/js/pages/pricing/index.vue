<script setup>
import { labelize } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'PriceList',
  },
})

const ability = useAbility()
const tab = ref('lists')
const filterValues = ref({ tab: 'lists' })
const lists = ref([])
const rules = ref([])
const packages = ref([])
const meta = ref(asPageMeta())
const page = ref(1)
const q = ref('')
const listOpen = ref(false)
const ruleOpen = ref(false)
const packageOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const listForm = ref({ name: '', kind: 'self_pay', tax_inclusive: false, is_default: false })
const ruleForm = ref({ name: '', type: 'discount_percent', scope: 'service', value: 0, is_active: true })
const packageForm = ref({ name: '', unit_price: 0 })

const filters = [
  { key: 'tab', type: 'segmented', options: [
    { title: 'Lists', value: 'lists' },
    { title: 'Rules', value: 'rules' },
    { title: 'Packages', value: 'packages' },
  ] },
]

const load = async () => {
  tab.value = filterValues.value.tab
  if (tab.value === 'lists') {
    const payload = await $api('/price-lists', { query: { page: page.value, q: q.value || undefined } })
    lists.value = asList(payload)
    meta.value = asPageMeta(payload)
  }
  else if (tab.value === 'rules') {
    const payload = await $api('/pricing-rules', { query: { page: page.value } })
    rules.value = asList(payload)
    meta.value = asPageMeta(payload)
  }
  else {
    const payload = await $api('/service-packages', { query: { page: page.value } })
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

watch(() => filterValues.value.tab, () => { page.value = 1; load() })
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
        @search="page = 1; load()"
      />
      <HTable
        v-if="tab === 'lists'"
        :loading="pending"
        :headers="[
          { title: 'Name', key: 'name' },
          { title: 'Kind', key: 'kind' },
          { title: 'Items', key: 'items_count' },
        ]"
        :items="lists"
        empty="No price lists yet"
      >
        <template #cell-name="{ item }">
          <RouterLink
            class="h-inline-link"
            :to="{ name: 'pricing-id', params: { id: item.id } }"
          >
            {{ item.name }}
          </RouterLink>
        </template>
        <template #cell-kind="{ item }">
          {{ labelize(item.kind) }}
        </template>
      </HTable>
      <HTable
        v-else-if="tab === 'rules'"
        :loading="pending"
        :headers="[
          { title: 'Name', key: 'name' },
          { title: 'Type', key: 'type' },
          { title: 'Value', key: 'value' },
        ]"
        :items="rules"
        empty="No rules yet"
      />
      <HTable
        v-else
        :loading="pending"
        :headers="[
          { title: 'Name', key: 'name' },
          { title: 'Price', key: 'unit_price' },
        ]"
        :items="packages"
        empty="No packages yet"
      />
      <HPager
        :meta="meta"
        @update:page="value => { page = value; load() }"
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
          :disabled="saving"
          @click="savePackage"
        >
          Save
        </HButton>
      </template>
    </HModal>
  </div>
</template>
