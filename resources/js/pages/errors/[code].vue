<script setup>
import { errorCatalog, resolveError } from '@/utils/errors'
import { resolveHomeRoute } from '@/utils/session'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const route = useRoute()
const router = useRouter()
const userData = useCookie('userData')

const code = computed(() => {
  const value = Number(route.params.code)
  return errorCatalog[value] ? value : 404
})

const meta = computed(() => resolveError(code.value))
const home = computed(() => resolveHomeRoute(userData.value))

watch(code, value => {
  if (value === 401) {
    router.replace({ name: 'login', query: { reason: 'expired' } })
    return
  }

  if (String(route.params.code) !== String(value))
    router.replace({ name: 'errors-code', params: { code: String(value) } })
}, { immediate: true })
</script>

<template>
  <HErrorPage :code="code">
    <HButton
      v-if="meta.action === 'login'"
      :to="{ name: 'login', query: { reason: code === 401 || code === 419 ? 'expired' : undefined } }"
    >
      Sign in
    </HButton>
    <HButton
      v-else-if="userData && home.name !== 'not-authorized'"
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
      variant="ghost"
      @click="router.go(0)"
    >
      Refresh
    </HButton>
  </HErrorPage>
</template>
