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
        <h1 style="font-family:var(--display);font-size:2.1rem;margin:6px 0 18px">
          Welcome back
        </h1>
        <div
          v-if="formError"
          class="h-alert"
        >
          {{ formError }}
        </div>
        <div class="h-grid cols-1" style="gap:14px">
          <HInput
            v-model="credentials.email"
            label="Email"
            type="email"
            :error="errors.email"
          />
          <HInput
            v-model="credentials.password"
            label="Password"
            type="password"
            :error="errors.password"
          />
          <HButton
            type="submit"
            :disabled="submitting"
          >
            {{ submitting ? 'Signing in…' : 'Enter workspace' }}
          </HButton>
        </div>
        <p style="margin-top:18px;color:var(--muted);font-size:0.88rem">
          Riverside admin: admin@riverside.test / password
        </p>
      </form>
    </section>
  </div>
</template>
