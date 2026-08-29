<script setup>
const route = useRoute()
const blank = computed(() => route.meta.layout === 'blank')
const renderError = ref(null)
const pageKey = computed(() => `${String(route.name || '')}:${Object.values(route.params).join('/')}`)

watch(() => route.fullPath, () => {
  renderError.value = null
})

onErrorCaptured(error => {
  renderError.value = error?.message || 'This page failed to render.'
  console.error(error)
  return false
})
</script>

<template>
  <HmsShell v-if="!blank">
    <Transition name="h-fade">
      <div
        v-if="renderError"
        class="h-alert"
      >
        {{ renderError }}
      </div>
    </Transition>
    <RouterView
      v-if="!renderError"
      v-slot="{ Component }"
    >
      <Transition name="h-page">
        <div
          v-if="Component"
          :key="pageKey"
          class="h-page-view"
        >
          <Suspense :timeout="0">
            <component :is="Component" />
            <template #fallback>
              <HPageLoader />
            </template>
          </Suspense>
        </div>
      </Transition>
    </RouterView>
  </HmsShell>
  <RouterView
    v-else
    v-slot="{ Component }"
  >
    <Transition name="h-page">
      <div
        v-if="Component"
        :key="pageKey"
        class="h-page-view"
      >
        <Suspense :timeout="0">
          <component :is="Component" />
        </Suspense>
      </div>
    </Transition>
  </RouterView>
  <HToastHost />
</template>
