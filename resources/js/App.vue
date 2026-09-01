<script setup>
import { pageLoading, forceFinishPageNav } from '@/composables/useRouteLoad'
import { pageError, setPageError } from '@/composables/usePageLoad'
import { errorCatalog } from '@/utils/errors'

const route = useRoute()
const userData = useCookie('userData')
const signedIn = computed(() => Boolean(userData.value))
const renderError = ref(false)
const pageKey = computed(() => `${String(route.name || '')}:${Object.values(route.params).join('/')}`)

const pageFault = computed(() => pageError.value?.code || (renderError.value ? 500 : null))
const showShell = computed(() => {
  if (route.meta.unauthenticatedOnly)
    return false

  return signedIn.value
})
const canRetry = computed(() => {
  const code = Number(pageFault.value)
  return Boolean(code && errorCatalog[code] && ![401, 403, 404, 410].includes(code))
})

watch(() => route.fullPath, () => {
  renderError.value = false
})

onErrorCaptured(error => {
  forceFinishPageNav()
  renderError.value = true
  setPageError(500)
  console.error(error)
  return false
})

const recover = () => {
  renderError.value = false
  setPageError(null)
}
</script>

<template>
  <HmsShell v-if="showShell">
    <div
      class="h-page-stage"
      :class="{ 'is-loading': pageLoading && !pageFault }"
    >
      <HPageLoader v-show="pageLoading && !pageFault" />
      <HErrorPage
        v-if="pageFault"
        :code="pageFault"
      >
        <HButton
          v-if="canRetry"
          variant="ghost"
          @click="recover"
        >
          Refresh
        </HButton>
      </HErrorPage>
      <RouterView
        v-else
        v-slot="{ Component }"
      >
        <div
          v-if="Component"
          :key="pageKey"
          class="h-page-view"
        >
          <component :is="Component" />
        </div>
      </RouterView>
    </div>
  </HmsShell>
  <HErrorPage
    v-else-if="pageFault"
    :code="pageFault"
  >
    <HButton
      v-if="canRetry"
      variant="ghost"
      @click="recover"
    >
      Refresh
    </HButton>
  </HErrorPage>
  <div
    v-else
    class="h-page-stage"
    :class="{ 'is-loading': pageLoading }"
  >
    <HPageLoader v-show="pageLoading" />
    <RouterView v-slot="{ Component }">
      <div
        v-if="Component"
        :key="pageKey"
        class="h-page-view"
      >
        <component :is="Component" />
      </div>
    </RouterView>
  </div>
  <HToastHost />
</template>
