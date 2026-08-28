<script setup>
import { themeConfig } from '@themeConfig'
import Footer from '@/layouts/components/Footer.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import { HorizontalNavLayout } from '@layouts'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { buildNavigation } from '@/navigation/horizontal'
import { pageLoadError } from '@/composables/usePageLoad'

const isFallbackStateActive = ref(false)
const refLoadingIndicator = ref(null)
const userData = useCookie('userData')
const ability = useAbility()
const route = useRoute()
const navItems = computed(() => buildNavigation(ability, userData.value))
const pageError = ref(null)

watch(() => route.fullPath, () => {
  pageError.value = null
  pageLoadError.value = null
})

watch(pageLoadError, value => {
  if (value)
    pageError.value = value
})

onErrorCaptured(error => {
  pageError.value = error?.data?.message || error?.message || 'This page failed to load'
  isFallbackStateActive.value = false

  return false
})

watch([
  isFallbackStateActive,
  refLoadingIndicator,
], () => {
  if (isFallbackStateActive.value && refLoadingIndicator.value)
    refLoadingIndicator.value.fallbackHandle()
  if (!isFallbackStateActive.value && refLoadingIndicator.value)
    refLoadingIndicator.value.resolveHandle()
}, { immediate: true })
</script>

<template>
  <HorizontalNavLayout :nav-items="navItems">
    <template #navbar>
      <RouterLink
        to="/"
        class="app-logo d-flex align-center gap-x-3"
      >
        <VNodeRenderer :nodes="themeConfig.app.logo" />
        <h1 class="app-title font-weight-bold leading-normal text-xl text-capitalize">
          {{ themeConfig.app.title }}
        </h1>
      </RouterLink>
      <VSpacer />
      <NavbarThemeSwitcher />
      <UserProfile />
    </template>

    <AppLoadingIndicator ref="refLoadingIndicator" />

    <VAlert
      v-if="pageError"
      type="error"
      class="mb-4"
      closable
      @click:close="pageError = null"
    >
      {{ pageError }}
    </VAlert>

    <RouterView v-slot="{ Component }">
      <Suspense
        v-if="Component"
        :timeout="0"
        @fallback="isFallbackStateActive = true"
        @resolve="isFallbackStateActive = false"
      >
        <component
          :is="Component"
          :key="route.fullPath"
        />
        <template #fallback>
          <div class="d-flex justify-center align-center py-16">
            <VProgressCircular
              indeterminate
              color="primary"
            />
          </div>
        </template>
      </Suspense>
      <div
        v-else
        class="d-flex justify-center align-center py-16"
      >
        <VProgressCircular
          indeterminate
          color="primary"
        />
      </div>
    </RouterView>

    <template #footer>
      <Footer />
    </template>

    <TheCustomizer />
  </HorizontalNavLayout>
</template>
