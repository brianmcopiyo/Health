<script setup>
import { facilityStatuses, labelize, statusColor } from '@/utils/status'

definePage({
  meta: {
    action: 'read',
    subject: 'Facility',
  },
})

const route = useRoute()
const router = useRouter()
const ability = useAbility()
const facility = ref(null)
const types = ref([])
const departments = ref([])
const parents = ref([])
const statusOpen = ref(false)
const editOpen = ref(false)
const removing = ref(false)
const saving = ref(false)
const formError = ref('')
const statusForm = ref({
  status: 'available',
  current_utilization: 0,
  resource_notes: '',
})
const form = ref({
  name: '',
  code: '',
  facility_type_id: null,
  parent_id: null,
  department_id: null,
  status: 'available',
  capacity: 1,
  current_utilization: 0,
  resource_notes: '',
  notes: '',
})

const fillStatus = record => {
  statusForm.value = {
    status: record.status,
    current_utilization: record.current_utilization,
    resource_notes: record.resource_notes,
  }
}

const load = async () => {
  facility.value = await $api(`/facilities/${route.params.id}`)
  fillStatus(facility.value)
}

await withPageLoad(load)

const openStatus = () => {
  formError.value = ''
  fillStatus(facility.value)
  statusOpen.value = true
}

const saveStatus = async () => {
  await wrapSave(saving, formError, async () => {
    facility.value = await $api(`/facilities/${facility.value.id}/status`, {
      method: 'PATCH',
      body: statusForm.value,
    })
    statusOpen.value = false
  })
}

const openEdit = async () => {
  formError.value = ''
  types.value = asList(await $api('/facility-types'))
  departments.value = asList(await $api('/departments').catch(() => []))
  parents.value = asList(await $api('/facilities', { query: { per_page: 80 } }).catch(() => []))
    .filter(item => item.id !== facility.value.id)
  form.value = {
    name: facility.value.name,
    code: facility.value.code,
    facility_type_id: facility.value.facility_type_id,
    parent_id: facility.value.parent_id,
    department_id: facility.value.department_id,
    status: facility.value.status,
    capacity: facility.value.capacity,
    current_utilization: facility.value.current_utilization,
    resource_notes: facility.value.resource_notes,
    notes: facility.value.notes,
  }
  editOpen.value = true
}

const saveEdit = async () => {
  await wrapSave(saving, formError, async () => {
    facility.value = await $api(`/facilities/${facility.value.id}`, {
      method: 'PUT',
      body: form.value,
    })
    editOpen.value = false
    await load()
  })
}

const removeFacility = async () => {
  await wrapSave(saving, formError, async () => {
    await $api(`/facilities/${facility.value.id}`, { method: 'DELETE' })
    await router.push({ name: 'facilities' })
  })
}
</script>

<template>
  <div>
    <HPage
      :title="facility?.name || 'Facility'"
      :subtitle="facility ? `${facility.type?.name || ''} · ${facility.code}` : ''"
    >
      <HButton
        variant="ghost"
        :to="{ name: 'facilities' }"
      >
        <HIcon name="back" />
        Facilities
      </HButton>
      <HButton
        v-if="facility && ability.can('update', 'Facility')"
        variant="ghost"
        @click="openEdit"
      >
        <HIcon name="edit" />
        Edit
      </HButton>
      <HButton
        v-if="facility && ability.can('update', 'Facility')"
        @click="openStatus"
      >
        Update status
      </HButton>
      <HActionMenu v-if="facility && ability.can('manage', 'Facility')">
        <template #default="{ close }">
          <button
            type="button"
            class="h-action-item is-danger"
            @click="formError = ''; removing = true; close()"
          >
            Remove
          </button>
        </template>
      </HActionMenu>
    </HPage>

    <div
      v-if="!facility"
      class="h-alert"
    >
      This facility could not be loaded.
    </div>

    <template v-else>
      <HGrid
        cols="4"
        kind="stats"
      >
        <HStat
          icon="hospital"
          title="Capacity"
          :value="facility.capacity"
          hint="Rated unit capacity"
        />
        <HStat
          icon="users"
          title="In use"
          :value="facility.current_utilization"
          hint="Current occupancy"
        />
        <HStat
          icon="check"
          title="Remaining"
          :value="facility.remaining_capacity"
          hint="Capacity still open"
          tone="ok"
        />
      </HGrid>
      <div class="h-detail">
        <HCard title="Status">
          <div class="h-stack">
            <HBadge :tone="statusColor(facility.status)">
              {{ labelize(facility.status) }}
            </HBadge>
            <p>{{ facility.resource_notes || 'No resource notes yet.' }}</p>
            <p
              v-if="facility.notes"
              class="h-muted"
            >
              {{ facility.notes }}
            </p>
          </div>
        </HCard>
        <HCard title="Related">
          <div class="h-metric">
            <span>Department</span>
            <strong>{{ facility.department?.name || '—' }}</strong>
          </div>
          <div class="h-metric">
            <span>Parent</span>
            <strong>
              <RouterLink
                v-if="facility.parent?.id"
                class="h-inline-link"
                :to="{ name: 'facilities-id', params: { id: facility.parent.id } }"
              >
                {{ facility.parent.name }}
              </RouterLink>
              <template v-else>
                —
              </template>
            </strong>
          </div>
        </HCard>
      </div>
      <HCard
        v-if="facility.children?.length"
        title="Child units"
        flush
      >
        <HTable
          :headers="[
            { title: 'Unit', key: 'name' },
            { title: 'Type', key: 'type.name' },
            { title: 'Status', key: 'status' },
          ]"
          :items="facility.children"
          empty="No child units"
        >
          <template #cell-name="{ item }">
            <RouterLink
              class="h-inline-link"
              :to="{ name: 'facilities-id', params: { id: item.id } }"
            >
              {{ item.name }}
            </RouterLink>
          </template>
          <template #cell-status="{ item }">
            <HBadge :tone="statusColor(item.status)">
              {{ labelize(item.status) }}
            </HBadge>
          </template>
        </HTable>
      </HCard>
    </template>

    <HModal
      v-model="statusOpen"
      title="Update status"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
        class="h-stack"
        :disabled="saving"
      >
        <HSelect
          v-model="statusForm.status"
          :items="facilityStatuses"
          label="Status"
        />
        <HNumber
          v-model="statusForm.current_utilization"
          label="Current utilization"
          :min="0"
        />
        <HTextarea
          v-model="statusForm.resource_notes"
          label="Resource availability"
        />
      </fieldset>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="statusOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving"
          @click="saveStatus"
        >
          Save
        </HButton>
      </template>
    </HModal>

    <HOffcanvas
      v-model="editOpen"
      title="Update facility"
      size="lg"
      :error="formError"
      :persistent="saving"
    >
      <fieldset
        class="h-stack"
        :disabled="saving"
      >
        <HInput
          v-model="form.name"
          label="Name"
          required
        />
        <div class="h-form-grid">
          <HInput
            v-model="form.code"
            label="Code"
            required
          />
          <HSelect
            v-model="form.facility_type_id"
            :items="types"
            item-title="name"
            item-value="id"
            label="Type"
            required
          />
        </div>
        <HSelect
          v-model="form.parent_id"
          :items="parents"
          item-title="name"
          item-value="id"
          label="Parent unit"
        />
        <HSelect
          v-model="form.department_id"
          :items="departments"
          item-title="name"
          item-value="id"
          label="Department"
        />
        <div class="h-form-grid is-3">
          <HSelect
            v-model="form.status"
            :items="facilityStatuses"
            label="Status"
          />
          <HNumber
            v-model="form.capacity"
            label="Capacity"
            :min="1"
          />
          <HNumber
            v-model="form.current_utilization"
            label="Current utilization"
            :min="0"
          />
        </div>
        <HTextarea
          v-model="form.resource_notes"
          label="Resource availability"
        />
      </fieldset>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="editOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving"
          @click="saveEdit"
        >
          Save
        </HButton>
      </template>
    </HOffcanvas>

    <HModal
      v-model="removing"
      title="Remove facility"
      :error="formError"
      :persistent="saving"
    >
      <p>Remove {{ facility?.name }}?</p>
      <template #actions>
        <HButton
          variant="ghost"
          :disabled="saving"
          @click="removing = false"
        >
          Keep
        </HButton>
        <HButton
          variant="danger"
          :disabled="saving"
          @click="removeFacility"
        >
          Remove
        </HButton>
      </template>
    </HModal>
  </div>
</template>
