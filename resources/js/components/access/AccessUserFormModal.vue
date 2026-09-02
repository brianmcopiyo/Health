<script setup>
import { ref, watch } from 'vue'
import { useAbility } from '@/composables/useAbility'
import { wrapSave } from '@/composables/usePageLoad'
import { $api } from '@/utils/api'
import { emptyUserForm, userFormFrom, userPayload, accountStatusItems } from '@/utils/access'

const props = defineProps({
  modelValue: Boolean,
  user: { type: Object, default: null },
  roles: { type: Array, default: () => [] },
  defaultRoleSlug: { type: String, default: '' },
  namePlaceholder: { type: String, default: 'e.g. Grace Adeyemi' },
  emailPlaceholder: { type: String, default: 'e.g. nurse@hospital.org' },
})

const emit = defineEmits(['update:modelValue', 'saved'])
const ability = useAbility()
const saving = ref(false)
const formError = ref('')
const form = ref(emptyUserForm())

watch(() => [props.modelValue, props.user], () => {
  if (!props.modelValue)
    return
  formError.value = ''
  form.value = props.user
    ? userFormFrom(props.user)
    : emptyUserForm({
      role_id: props.roles.find(role => role.slug === props.defaultRoleSlug)?.id ?? props.roles[0]?.id,
    })
}, { immediate: true })

const close = () => emit('update:modelValue', false)

const save = async () => {
  await wrapSave(saving, formError, async () => {
    const payload = userPayload(form.value, props.user, ability.can('manage', 'Hospital'))
    if (props.user)
      await $api(`/users/${props.user.id}`, { method: 'PUT', body: payload })
    else
      await $api('/users', { method: 'POST', body: payload })
    close()
    emit('saved')
  })
}
</script>

<template>
  <HModal
    :model-value="modelValue"
    :title="user ? 'Update user' : 'Add user'"
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
      <HInput
        v-model="form.email"
        label="Email"
        type="email"
        icon="mail"
        :placeholder="emailPlaceholder"
        :disabled="Boolean(user)"
        :hint="user ? 'Sign-in email cannot be changed' : undefined"
        required
      />
      <HInput
        span
        v-model="form.password"
        :label="user ? 'New password' : 'Password'"
        :optional="Boolean(user)"
        :required="!user"
        type="password"
        icon="lock"
        placeholder="At least 8 characters"
        :hint="user ? 'Leave blank to keep the current password' : ''"
      />
      <HSelect
        v-model="form.role_id"
        :items="roles"
        item-title="name"
        item-value="id"
        label="Role"
        required
      />
      <HSelect
        v-model="form.status"
        :items="accountStatusItems"
        item-title="title"
        item-value="value"
        label="Status"
        required
      />
      <slot
        name="extra"
        :form="form"
        :ability="ability"
        :user="user"
      />
      <HInput
        v-model="form.job_title"
        label="Job title"
        placeholder="e.g. Charge nurse"
      />
      <HInput
        v-model="form.phone"
        label="Phone"
        type="tel"
        icon="phone"
        placeholder="e.g. 024 555 0100"
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
        :loading="saving"
        :disabled="saving"
        @click="save"
      >
        Save
      </HButton>
    </template>
  </HModal>
</template>
