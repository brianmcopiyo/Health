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
  <HErrorPage code="403">
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
  </HErrorPage>
</template>
