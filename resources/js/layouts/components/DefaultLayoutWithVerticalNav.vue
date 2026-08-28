<script setup>
import { themeConfig } from '@themeConfig'
import Footer from '@/layouts/components/Footer.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import { VerticalNavLayout } from '@layouts'
import { buildNavigation } from '@/navigation/vertical'
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
  <VerticalNavLayout :nav-items="navItems">
    <template #navbar="{ toggleVerticalOverlayNavActive }">
      <div class="d-flex h-100 align-center">
        <IconBtn
          id="vertical-nav-toggle-btn"
          class="ms-n3 d-lg-none"
          @click="toggleVerticalOverlayNavActive(true)"
        >
          <VIcon
            size="26"
            icon="tabler-menu-2"
          />
        </IconBtn>

        <div class="text-body-1 font-weight-medium text-truncate">
          {{ userData?.hospitalName || themeConfig.app.title }}
          <span
            v-if="userData?.roleName"
            class="text-medium-emphasis ms-2"
          >{{ userData.roleName }}</span>
        </div>

        <VSpacer />

        <NavbarThemeSwitcher />
        <UserProfile />
      </div>
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
  </VerticalNavLayout>
</template>
