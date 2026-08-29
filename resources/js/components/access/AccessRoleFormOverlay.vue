<script setup>
import { computed, ref, watch } from 'vue'
import { wrapSave } from '@/composables/usePageLoad'
import { $api } from '@/utils/api'
import { groupPermissions } from '@/utils/access'

const props = defineProps({
  modelValue: Boolean,
  role: { type: Object, default: null },
  permissions: { type: Array, default: () => [] },
  workspaces: { type: Array, default: () => [] },
  defaultWorkspace: { type: String, default: '' },
  namePlaceholder: { type: String, default: 'e.g. Charge nurse' },
})

const emit = defineEmits(['update:modelValue', 'saved'])
const saving = ref(false)
const formError = ref('')
const form = ref({
  name: '',
  description: '',
  workspace: '',
  permission_ids: [],
})

const groupedPermissions = computed(() => groupPermissions(props.permissions))

watch(() => [props.modelValue, props.role], () => {
  if (!props.modelValue)
    return
  formError.value = ''
  form.value = props.role
    ? {
      name: props.role.name,
      description: props.role.description,
      workspace: props.role.workspace,
      permission_ids: (props.role.permissions || []).map(permission => permission.id),
    }
    : {
      name: '',
      description: '',
      workspace: props.workspaces[0]?.value || props.defaultWorkspace,
      permission_ids: [],
    }
}, { immediate: true })

const close = () => emit('update:modelValue', false)

const save = async () => {
  await wrapSave(saving, formError, async () => {
    if (props.role)
      await $api(`/roles/${props.role.id}`, { method: 'PUT', body: form.value })
    else
      await $api('/roles', { method: 'POST', body: form.value })
    close()
    emit('saved')
  })
}
</script>

<template>
  <HOffcanvas
    :model-value="modelValue"
    :title="role ? 'Update role' : 'Add role'"
    size="lg"
    :error="formError"
    :persistent="saving"
    @update:model-value="val => emit('update:modelValue', val)"
  >
    <fieldset
      class="h-form-grid"
      :disabled="saving"
    >
      <HInput
        v-model="form.name"
        label="Name"
        :placeholder="namePlaceholder"
        required
      />
      <HSelect
        v-model="form.workspace"
        :items="workspaces"
        item-title="title"
        item-value="value"
        label="Workspace"
        required
      />
      <HTextarea
        span
        v-model="form.description"
        label="Description"
        placeholder="What this role can do"
        hint="Shown to administrators when assigning this role"
      />
      <HMultiSelect
        v-for="(group, name) in groupedPermissions"
        :key="name"
        span
        v-model="form.permission_ids"
        :items="group"
        item-title="name"
        item-value="id"
        :label="name"
        :placeholder="`Choose ${String(name).toLowerCase()} permissions`"
      />
    </fieldset>
    <template #actions>
      <HButton
        variant="ghost"
        :disabled="saving"
        @click="close"
      >
        Cancel
      </HButton>
      <HButton
        :disabled="saving"
        @click="save"
      >
        Save
      </HButton>
    </template>
  </HOffcanvas>
</template>
