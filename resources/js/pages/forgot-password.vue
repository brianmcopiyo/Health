<script setup>
definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const email = ref('')
const sent = ref(false)

const submit = () => {
  sent.value = true
}
</script>

<template>
  <HAuthStage
    :scene="sent ? 'sent' : 'forgot'"
    brand="Caregrid"
    :headline="sent ? 'Share this email with your hospital administrator.' : 'Staff access is issued, not guessed.'"
    :lead="sent
      ? 'A hospital administrator can restore or replace your Caregrid password from Users. They will look you up by this email.'
      : 'Caregrid accounts belong to hospital teams. If you cannot sign in, your administrator can restore access without circulating passwords.'"
    :points="sent
      ? [
        { icon: 'users', title: 'Users', body: 'Administrators reset staff accounts from the hospital directory.' },
        { icon: 'shield', title: 'Safety', body: 'Passwords are never shown in full once they have been issued.' },
      ]
      : [
        { icon: 'hospital', title: 'Issued accounts', body: 'Reception, wards, and diagnostics each receive a named login.' },
        { icon: 'lock', title: 'Forgotten password', body: 'Ask an administrator rather than sharing a colleague’s sign-in.' },
        { icon: 'shield', title: 'Duty desks', body: 'Lock the screen when you leave a shared hospital workstation.' },
      ]"
  >
    <form
      v-if="!sent"
      class="h-auth-card"
      @submit.prevent="submit"
    >
      <p class="hms-kicker">
        Account help
      </p>
      <h1>Can’t sign in to Caregrid?</h1>
      <p class="h-auth-lead">
        Enter the email on your staff account. You will take it to a hospital administrator, who can restore access from Users.
      </p>
      <div class="h-stack">
        <HInput
          v-model="email"
          label="Staff email"
          type="email"
          icon="mail"
          autocomplete="username"
          required
          placeholder="you@hospital.org"
        />
        <HButton
          type="submit"
          class="is-block"
        >
          Continue with this email
        </HButton>
      </div>
      <p class="h-auth-links">
        <RouterLink :to="{ name: 'login' }">
          Back to sign in
        </RouterLink>
        <RouterLink :to="{ name: 'reset-password' }">
          I already have a reset link
        </RouterLink>
      </p>
      <p class="h-auth-note">
        <HIcon
          name="shield"
          :size="16"
        />
        Do not send your password by message. Administrators issue a new one; they do not need the old one.
      </p>
    </form>
    <div
      v-else
      class="h-auth-card"
    >
      <p class="hms-kicker">
        Next step
      </p>
      <h1>Ask your hospital administrator</h1>
      <p class="h-auth-lead">
        Tell them you need Caregrid access restored for <strong>{{ email }}</strong>. They can set a new password and confirm your role still matches your duty.
      </p>
      <div class="h-error-actions">
        <HButton :to="{ name: 'login' }">
          Return to sign in
        </HButton>
        <HButton
          variant="ghost"
          @click="sent = false"
        >
          Use a different email
        </HButton>
      </div>
      <p class="h-auth-note">
        <HIcon
          name="lock"
          :size="16"
        />
        After access is restored, sign in on this device only and lock the screen when you leave the desk.
      </p>
    </div>
  </HAuthStage>
</template>
