<script setup>
const route = useRoute()
const blank = computed(() => route.meta.layout === 'blank')
const renderError = ref(null)

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
    <div
      v-if="renderError"
      class="h-alert"
    >
      {{ renderError }}
    </div>
    <RouterView
      v-else
      v-slot="{ Component }"
    >
      <Suspense
        v-if="Component"
        :timeout="0"
      >
        <component :is="Component" />
        <template #fallback>
          <div class="h-progress" />
        </template>
      </Suspense>
    </RouterView>
  </HmsShell>
  <RouterView
    v-else
    v-slot="{ Component }"
  >
    <Suspense
      v-if="Component"
      :timeout="0"
    >
      <component :is="Component" />
    </Suspense>
  </RouterView>
</template>
