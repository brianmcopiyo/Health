<script setup>
const route = useRoute()
const blank = computed(() => route.meta.layout === 'blank')
</script>

<template>
  <HmsShell v-if="!blank">
    <RouterView v-slot="{ Component }">
      <Suspense v-if="Component" :timeout="0">
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
    <Suspense v-if="Component" :timeout="0">
      <component :is="Component" />
    </Suspense>
  </RouterView>
</template>
