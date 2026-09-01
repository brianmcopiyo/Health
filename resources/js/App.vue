<script setup>
import { pageLoading, forceFinishPageNav } from '@/composables/useRouteLoad'
import { pageError, setPageError } from '@/composables/usePageLoad'

const route = useRoute()
const blank = computed(() => route.meta.layout === 'blank')
const pageKey = computed(() => `${String(route.name || '')}:${Object.values(route.params).join('/')}`)
const pageFault = computed(() => pageError.value?.code || null)

if (typeof window !== 'undefined' && window.__PAGE_ERROR__) {
  setPageError(window.__PAGE_ERROR__)
  window.__PAGE_ERROR__ = null
}

onErrorCaptured(error => {
  forceFinishPageNav()
  setPageError(500)
  console.error(error)
  return false
})
</script>

<template>
  <HErrorPage
    v-if="pageFault"
    :code="pageFault"
  />
  <HmsShell v-else-if="!blank">
    <div
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
  </HmsShell>
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
