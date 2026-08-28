<script setup>
import { clearSession, resolveHomeRoute } from '@/utils/session'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const router = useRouter()
const ability = useAbility()
const userData = useCookie('userData')
const home = computed(() => resolveHomeRoute(userData.value))

const signOut = async () => {
  try {
    await $api('/auth/logout', { method: 'POST' })
  }
  catch {}
  clearSession(ability)
  await router.push({ name: 'login' })
}
</script>

<template>
  <div class="blank-state">
    <p class="hms-kicker">
      Caregrid
    </p>
    <h1>You do not have access to this workspace.</h1>
    <p>Ask your hospital administrator if you need a different role.</p>
    <div style="display:flex;gap:10px;justify-content:center;margin-top:18px;flex-wrap:wrap">
      <HButton
        v-if="userData && home.name !== 'not-authorized'"
        :to="home"
      >
        Open workspace
      </HButton>
      <HButton
        v-else
        :to="{ name: 'login' }"
      >
        Sign in
      </HButton>
      <HButton
        v-if="userData"
        variant="ghost"
        @click="signOut"
      >
        Sign out
      </HButton>
    </div>
  </div>
</template>
