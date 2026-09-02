<script setup>
definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const route = useRoute()
const token = computed(() => String(route.query.token || ''))
const email = ref(String(route.query.email || ''))
const password = ref('')
const confirmation = ref('')
const submitting = ref(false)
const formError = ref('')
const errors = ref({})
const done = ref(false)

const submit = async () => {
  if (submitting.value)
    return

  formError.value = ''
  errors.value = {}
  if (password.value !== confirmation.value) {
    errors.value = { password_confirmation: 'The two passwords do not match.' }
    return
  }
  submitting.value = true
  try {
    await $api('/auth/password/reset', {
      method: 'POST',
      body: {
        email: email.value,
        token: token.value,
        password: password.value,
        password_confirmation: confirmation.value,
      },
      onResponseError({ response }) {
        errors.value = response._data?.errors || {}
        formError.value = response._data?.message || 'This reset link is not valid or has expired.'
      },
    })
    done.value = true
  }
  catch (error) {
    if (!formError.value)
      formError.value = error?.data?.message || 'This reset link is not valid or has expired. Ask a hospital administrator to issue a new password.'
  }
  finally {
    submitting.value = false
  }
}
</script>

<template>
  <HAuthStage
    :scene="done ? 'sent' : (token ? 'reset' : 'verify')"
    brand="Caregrid"
    :headline="done
      ? 'Your Caregrid password has been replaced.'
      : token
        ? 'Choose a new password for this hospital account.'
        : 'This reset link is incomplete or has expired.'"
    :lead="done
      ? 'Sign in with the new password. Do not keep it written at a shared ward desk.'
      : token
        ? 'Use a password only you know. After you save it, previous Caregrid sessions on other devices should be treated as closed.'
        : 'Reset links are issued by a hospital administrator. If you opened this page without a complete link, ask them to send a new one.'"
    :points="token && !done
      ? [
        { icon: 'key', title: 'New password', body: 'Pick something you will not write on a duty roster.' },
        { icon: 'shield', title: 'This device', body: 'Sign in here after saving, then lock the screen when you leave.' },
      ]
      : [
        { icon: 'mail', title: 'Complete link', body: 'A valid reset includes a token from your administrator.' },
        { icon: 'users', title: 'Users', body: 'Hospital administrators issue a new password from the staff directory.' },
      ]"
  >
    <div
      v-if="done"
      class="h-auth-copy"
    >
      <p class="hms-kicker">
        Password updated
      </p>
      <h1>Sign in with the new password</h1>
      <p class="h-auth-lead">
        Your previous Caregrid password will no longer open this hospital account.
      </p>
      <HButton
        class="is-block"
        :to="{ name: 'login' }"
      >
        Sign in to Caregrid
      </HButton>
    </div>
    <form
      v-else-if="token"
      class="h-auth-copy"
      @submit.prevent="submit"
    >
      <p class="hms-kicker">
        Reset password
      </p>
      <h1>Set a new Caregrid password</h1>
      <p class="h-auth-lead">
        This replaces the password on the staff account. You will sign in with it on the next screen.
      </p>
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
          v-model="email"
          label="Staff email"
          type="email"
          icon="mail"
          autocomplete="username"
          required
          placeholder="you@hospital.org"
          :error="errors.email"
          :disabled="submitting"
        />
        <HInput
          v-model="password"
          label="New password"
          type="password"
          icon="lock"
          autocomplete="new-password"
          required
          placeholder="Choose a new password"
          :error="errors.password"
          :disabled="submitting"
        />
        <HInput
          v-model="confirmation"
          label="Confirm password"
          type="password"
          icon="lock"
          autocomplete="new-password"
          required
          placeholder="Type it again"
          :error="errors.password_confirmation"
          :disabled="submitting"
        />
        <HButton
          type="submit"
          class="is-block"
          :loading="submitting"
        >
          Save new password
        </HButton>
      </div>
      <p class="h-auth-links">
        <RouterLink :to="{ name: 'login' }">
          Back to sign in
        </RouterLink>
      </p>
    </form>
    <div
      v-else
      class="h-auth-copy"
    >
      <p class="hms-kicker">
        Link not valid
      </p>
      <h1>Ask for a new reset</h1>
      <p class="h-auth-lead">
        This page needs a complete reset link. Open account help and take your staff email to a hospital administrator, or return to sign in if you remember your password.
      </p>
      <div class="h-error-actions">
        <HButton :to="{ name: 'forgot-password' }">
          Account help
        </HButton>
        <HButton
          variant="ghost"
          :to="{ name: 'login' }"
        >
          Sign in
        </HButton>
      </div>
    </div>
  </HAuthStage>
</template>
