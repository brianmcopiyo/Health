<script setup>
import AccountNav from '@/components/hms/AccountNav.vue'
import { applySession, clearSession } from '@/utils/session'

const route = useRoute()
const router = useRouter()
const ability = useAbility()
const userData = useCookie('userData')
const sessions = ref([])
const activity = ref([])
const passwordOpen = ref(false)
const emailOpen = ref(false)
const revokeOpen = ref(false)
const revokeOthersOpen = ref(false)
const revoking = ref(null)
const saving = ref(false)
const formError = ref('')
const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
})
const emailForm = ref({
  email: '',
  current_password: '',
})

const sessionHeaders = [
  { title: 'Session', key: 'device' },
  { title: 'Last used', key: 'last_used_at' },
  { title: 'Started', key: 'created_at' },
  { title: '', key: 'actions', fit: true },
]

const activityHeaders = [
  { title: 'Action', key: 'action' },
  { title: 'Record', key: 'entity' },
  { title: 'Hospital', key: 'hospital' },
  { title: 'When', key: 'at' },
]

const deviceLabel = item => {
  const agent = String(item.user_agent || '')
  if (/Edg\//.test(agent))
    return 'Microsoft Edge'
  if (/Chrome\//.test(agent))
    return 'Chrome'
  if (/Firefox\//.test(agent))
    return 'Firefox'
  if (/Safari\//.test(agent))
    return 'Safari'
  return item.name || 'Workspace session'
}

const when = value => value ? new Date(value).toLocaleString() : '—'

const load = async () => {
  const [sessionPayload, activityPayload] = await Promise.all([
    $api('/auth/sessions'),
    $api('/auth/activity'),
  ])
  sessions.value = asList(sessionPayload).map(item => ({
    ...item,
    device: deviceLabel(item),
  }))
  activity.value = asList(activityPayload)
}

const openPassword = () => {
  formError.value = ''
  passwordForm.value = { current_password: '', password: '', password_confirmation: '' }
  passwordOpen.value = true
}

const openEmail = () => {
  formError.value = ''
  emailForm.value = { email: userData.value?.email || '', current_password: '' }
  emailOpen.value = true
}

const changePassword = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/auth/password', { method: 'POST', body: passwordForm.value })
    passwordOpen.value = false
    await load()
  })
}

const changeEmail = async () => {
  await wrapSave(saving, formError, async () => {
    const payload = await $api('/auth/email', { method: 'POST', body: emailForm.value })
    applySession(payload, ability)
    emailOpen.value = false
  })
}

const askRevoke = item => {
  formError.value = ''
  revoking.value = item
  revokeOpen.value = true
}

const revokeSession = async () => {
  await wrapSave(saving, formError, async () => {
    const result = await $api(`/auth/sessions/${revoking.value.id}`, { method: 'DELETE' })
    revokeOpen.value = false
    if (result?.signed_out || revoking.value?.is_current) {
      clearSession(ability)
      await router.push({ name: 'login' })
      return
    }
    await load()
  })
}

const revokeOthers = async () => {
  await wrapSave(saving, formError, async () => {
    await $api('/auth/sessions/revoke-others', { method: 'POST' })
    revokeOthersOpen.value = false
    await load()
  })
}

const logout = async () => {
  try {
    await $api('/auth/logout', { method: 'POST' })
  }
  catch {}
  clearSession(ability)
  await router.push({ name: 'login' })
}

await withPageLoad(load)

onMounted(() => {
  if (route.query.action === 'password')
    openPassword()
})
</script>

<template>
  <div class="h-account">
    <HPage
      title="Account & security"
      subtitle="Sign-in, sessions, and activity for your Caregrid account"
    />

    <AccountNav />

    <HCard title="Sign-in">
      <div class="h-account-rows">
        <div class="h-account-row">
          <div>
            <strong>Email</strong>
            <p class="h-muted">
              {{ userData?.email }}
            </p>
          </div>
          <HButton
            variant="ghost"
            size="sm"
            @click="openEmail"
          >
            Change email
          </HButton>
        </div>
        <div class="h-account-row">
          <div>
            <strong>Password</strong>
            <p class="h-muted">
              Other sessions are signed out when you change it
            </p>
          </div>
          <HButton
            variant="ghost"
            size="sm"
            @click="openPassword"
          >
            Change password
          </HButton>
        </div>
        <div class="h-account-row">
          <div>
            <strong>This device</strong>
            <p class="h-muted">
              End the current workspace session
            </p>
          </div>
          <HButton
            variant="ghost"
            size="sm"
            @click="logout"
          >
            <HIcon
              name="logout"
              :size="14"
            />
            Sign out
          </HButton>
        </div>
      </div>
    </HCard>

    <HCard title="Active sessions">
      <template #actions>
        <HButton
          v-if="sessions.some(item => !item.is_current)"
          variant="ghost"
          size="sm"
          @click="revokeOthersOpen = true; formError = ''"
        >
          Sign out other sessions
        </HButton>
      </template>
      <HTable
        :headers="sessionHeaders"
        :items="sessions"
        empty="No active sessions"
      >
        <template #cell-last_used_at="{ item }">
          {{ when(item.last_used_at) }}
        </template>
        <template #cell-created_at="{ item }">
          {{ when(item.created_at) }}
        </template>
        <template #cell-actions="{ item }">
          <HBadge
            v-if="item.is_current"
            tone="success"
          >
            This session
          </HBadge>
          <HButton
            v-else
            variant="ghost"
            size="sm"
            @click="askRevoke(item)"
          >
            Revoke
          </HButton>
        </template>
      </HTable>
    </HCard>

    <HCard title="Activity">
      <p class="h-muted">
        Recent actions you performed in hospitals you can access
      </p>
      <HTable
        :headers="activityHeaders"
        :items="activity"
        empty="No account activity yet"
      >
        <template #cell-action="{ item }">
          {{ item.action }}
        </template>
        <template #cell-at="{ item }">
          {{ when(item.at) }}
        </template>
      </HTable>
    </HCard>

    <HModal
      v-model="passwordOpen"
      title="Change password"
      :error="formError"
    >
      <form
        class="h-form"
        @submit.prevent="changePassword"
      >
        <HInput
          v-model="passwordForm.current_password"
          type="password"
          label="Current password"
          required
        />
        <HInput
          v-model="passwordForm.password"
          type="password"
          label="New password"
          required
        />
        <HInput
          v-model="passwordForm.password_confirmation"
          type="password"
          label="Confirm new password"
          required
        />
      </form>
      <template #actions>
        <HButton
          variant="ghost"
          @click="passwordOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving"
          @click="changePassword"
        >
          Update password
        </HButton>
      </template>
    </HModal>

    <HModal
      v-model="emailOpen"
      title="Change email"
      :error="formError"
    >
      <form
        class="h-form"
        @submit.prevent="changeEmail"
      >
        <HInput
          v-model="emailForm.email"
          type="email"
          label="New email"
          required
        />
        <HInput
          v-model="emailForm.current_password"
          type="password"
          label="Current password"
          required
        />
      </form>
      <template #actions>
        <HButton
          variant="ghost"
          @click="emailOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          :disabled="saving"
          @click="changeEmail"
        >
          Update email
        </HButton>
      </template>
    </HModal>

    <HModal
      v-model="revokeOpen"
      title="Revoke session"
      :error="formError"
    >
      <p>This device will need to sign in again.</p>
      <template #actions>
        <HButton
          variant="ghost"
          @click="revokeOpen = false"
        >
          Keep session
        </HButton>
        <HButton
          variant="danger"
          :disabled="saving"
          @click="revokeSession"
        >
          Revoke
        </HButton>
      </template>
    </HModal>

    <HModal
      v-model="revokeOthersOpen"
      title="Sign out other sessions"
      :error="formError"
    >
      <p>Your current session stays active. Other devices will need to sign in again.</p>
      <template #actions>
        <HButton
          variant="ghost"
          @click="revokeOthersOpen = false"
        >
          Cancel
        </HButton>
        <HButton
          variant="danger"
          :disabled="saving"
          @click="revokeOthers"
        >
          Sign out others
        </HButton>
      </template>
    </HModal>
  </div>
</template>
