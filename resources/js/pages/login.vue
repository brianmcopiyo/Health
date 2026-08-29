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

const login = async () => {
  formError.value = ''
  errors.value = {}
  submitting.value = true
  try {
    const res = await $api('/auth/login', {
      method: 'POST',
      body: credentials.value,
      onResponseError({ response }) {
        errors.value = response._data?.errors || {}
        formError.value = response._data?.message || 'Unable to sign in'
      },
    })
    applySession(res, ability)
    await nextTick()
    const next = String(route.query.to || '')
    const blocked = !next || next === '/' || next.includes('login') || next.includes('not-authorized')
    await router.replace(blocked ? resolveHomeRoute(res.userData) : next)
  }
  catch (error) {
    if (!formError.value)
      formError.value = error?.data?.message || 'Unable to sign in'
  }
  finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="h-auth">
    <section class="h-auth-art">
      <div>
        <p class="hms-kicker">
          Caregrid
        </p>
        <h2>Clinical operations, visibly in control.</h2>
        <p>Live capacity, referrals, and role-based workspaces for every hospital in the network.</p>
      </div>
      <p>One design system. Distinct workspaces for reception, wards, diagnostics, and administration.</p>
    </section>
    <section class="h-auth-panel">
      <form
        class="h-auth-card"
        @submit.prevent="login"
      >
        <p class="hms-kicker">
          Sign in
        </p>
        <h1>
          Welcome back
        </h1>
        <div
          v-if="route.query.reason === 'expired' && !formError"
          class="h-alert"
        >
          Your session ended. Sign in to return to your workspace.
        </div>
        <div
          v-if="formError"
          class="h-alert"
        >
          {{ formError }}
        </div>
        <div class="h-stack">
          <HInput
            v-model="credentials.email"
            label="Email"
            type="email"
            icon="mail"
            autocomplete="username"
            required
            placeholder="you@hospital.org"
            :error="errors.email"
            :disabled="submitting"
            :loading="submitting"
          />
          <HInput
            v-model="credentials.password"
            label="Password"
            type="password"
            icon="lock"
            autocomplete="current-password"
            required
            :error="errors.password"
            :disabled="submitting"
            :loading="submitting"
          />
          <HButton
            type="submit"
            class="is-block"
            :disabled="submitting"
          >
            {{ submitting ? 'Signing in…' : 'Enter workspace' }}
          </HButton>
        </div>
        <p class="h-muted">
          Riverside admin: admin@riverside.test / password
        </p>
      </form>
    </section>
  </div>
</template>
