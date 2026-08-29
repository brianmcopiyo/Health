<script setup>
import { errorCatalog, resolveError } from '@/utils/errors'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const route = useRoute()
const router = useRouter()

const code = computed(() => {
  const value = Number(route.params.code)
  return errorCatalog[value] ? value : 404
})

const meta = computed(() => resolveError(code.value))

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
      v-if="meta.action !== 'login'"
      variant="ghost"
      @click="router.go(0)"
    >
      Refresh
    </HButton>
  </HErrorPage>
</template>
