<script setup>
import { pageLoading, forceFinishPageNav } from '@/composables/useRouteLoad'
import { resolveHomeRoute } from '@/utils/session'

const route = useRoute()
const blank = computed(() => route.meta.layout === 'blank')
const renderError = ref(null)
const pageKey = computed(() => `${String(route.name || '')}:${Object.values(route.params).join('/')}`)
const home = computed(() => resolveHomeRoute(useCookie('userData').value))

watch(() => route.fullPath, () => {
  renderError.value = null
})

onErrorCaptured(error => {
  forceFinishPageNav()
  renderError.value = error?.message || 'This page failed to render.'
  console.error(error)
  return false
})

const retry = () => {
  renderError.value = null
}
</script>

<template>
  <HmsShell v-if="!blank">
    <Transition name="h-fade">
      <div
        v-if="renderError"
        class="h-alert"
      >
        <span>{{ renderError }}</span>
        <div class="h-actions">
          <HButton
            size="sm"
            @click="retry"
          >
            Try again
          </HButton>
          <HButton
            size="sm"
            variant="ghost"
            :to="home"
          >
            Open workspace
          </HButton>
        </div>
      </div>
    </Transition>
    <div
      class="h-page-stage"
      :class="{ 'is-loading': pageLoading }"
    >
      <HPageLoader v-show="pageLoading" />
      <RouterView
        v-if="!renderError"
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
    v-else-if="renderError"
    code="500"
    :copy="renderError"
  />
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
