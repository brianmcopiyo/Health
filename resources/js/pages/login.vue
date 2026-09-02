<script setup>
import { applySession, resolveHomeRoute } from '@/utils/session'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const route = useRoute()
const router = useRouter()
const ability = useAbility()
const submitting = ref(false)
const formError = ref('')
const errors = ref({})
const credentials = ref({
  email: 'admin@riverside.test',
  password: 'password',
})
const expired = computed(() => route.query.reason === 'expired')
const scene = computed(() => expired.value ? 'verify' : 'login')
const headline = computed(() => expired.value
  ? 'Your clinical session ended for safety.'
  : 'Clinical operations, visibly in control.')
const lead = computed(() => expired.value
  ? 'Sign in again to return to the hospital, ward, and records you were using.'
  : 'Live capacity, referrals, and role-based work for every hospital in the Caregrid network.')

const login = async () => {
  if (submitting.value)
    return

  formError.value = ''
  errors.value = {}
  submitting.value = true
  try {
    const res = await $api('/auth/login', {
      method: 'POST',
      body: credentials.value,
      onResponseError({ response }) {
        errors.value = response._data?.errors || {}
        formError.value = response._data?.message || 'Unable to sign in to Caregrid'
      },
    })
    applySession(res, ability)
    await nextTick()
    const next = String(route.query.to || '')
    const blocked = !next || next === '/' || next.includes('login') || next.includes('not-authorized') || next.includes('/errors')
    await router.replace(blocked ? resolveHomeRoute(res.userData) : next)
  }
  catch (error) {
    if (!formError.value)
      formError.value = error?.data?.message || 'Unable to sign in to Caregrid'
  }
  finally {
    submitting.value = false
  }
}
</script>

<template>
  <HAuthStage
    :scene="scene"
    brand="Caregrid"
    :headline="headline"
    :lead="lead"
    :points="[
      { icon: 'bed', title: 'Wards', body: 'Occupancy and bed status as they stand on the floor.' },
      { icon: 'transfer', title: 'Referrals', body: 'Patients moving between hospitals stay on one register.' },
      { icon: 'stethoscope', title: 'Roles', body: 'Reception, diagnostics, and administration each see their work.' },
    ]"
  >
    <form
      class="h-auth-copy"
      @submit.prevent="login"
    >
      <p class="hms-kicker">
        {{ expired ? 'Session ended' : 'Hospital sign in' }}
      </p>
      <h1>
        {{ expired ? 'Continue clinical work' : 'Sign in to Caregrid' }}
      </h1>
      <p class="h-auth-lead">
        Use the staff account issued for your hospital. This desk opens live records, not a public directory.
      </p>
      <HTransition name="h-fade">
        <div
          v-if="expired && !formError"
          class="h-alert"
        >
          Your session ended after a period of inactivity. Sign in to return to the last hospital you were using.
        </div>
      </HTransition>
      <HTransition name="h-fade">
        <div
          v-if="formError"
          class="h-alert"
        >
          {{ formError }}
        </div>
      </HTransition>
      <div class="h-stack">
        <HInput
          v-model="credentials.email"
          label="Hospital email"
          type="email"
          icon="mail"
          autocomplete="username"
          required
          placeholder="you@hospital.org"
          :error="errors.email"
          :disabled="submitting"
        />
        <HInput
          v-model="credentials.password"
          label="Password"
          type="password"
          icon="lock"
          autocomplete="current-password"
          required
          placeholder="Your Caregrid password"
          :error="errors.password"
          :disabled="submitting"
        />
        <HButton
          type="submit"
          class="is-block"
          :loading="submitting"
        >
          Sign in to Caregrid
        </HButton>
      </div>
      <p class="h-auth-links">
        <RouterLink :to="{ name: 'forgot-password' }">
          Need help signing in?
        </RouterLink>
      </p>
      <p class="h-auth-note">
        <HIcon
          name="shield"
          :size="16"
        />
        Keep this password to yourself. Shared hospital desks should lock the screen when you step away.
      </p>
      <p class="h-muted">
        Demonstration hospital: admin@riverside.test · password
      </p>
    </form>
  </HAuthStage>
</template>
