<script setup>
import { resolveError } from '@/utils/errors'
import { resolveHomeRoute } from '@/utils/session'

const props = defineProps({
  code: { type: [Number, String], default: 404 },
  title: String,
  copy: String,
  icon: String,
})

const router = useRouter()
const userData = useCookie('userData')
const meta = computed(() => resolveError(props.code))
const heading = computed(() => props.title || meta.value.title)
const body = computed(() => props.copy || meta.value.copy)
const mark = computed(() => props.icon || meta.value.icon)
const home = computed(() => resolveHomeRoute(userData.value))
const signedIn = computed(() => Boolean(userData.value))
const exit = computed(() => {
  if (meta.value.action === 'login' || !signedIn.value || home.value.name === 'not-authorized')
    return { name: 'login', query: Number(props.code) === 401 || Number(props.code) === 419 ? { reason: 'expired' } : undefined }

  return home.value
})
const exitLabel = computed(() => exit.value.name === 'login' ? 'Sign in' : 'Open workspace')

const goBack = () => {
  if (window.history.length > 1) {
    router.back()
    return
  }

  router.replace(exit.value)
}
</script>

<template>
  <div class="h-error">
    <section class="h-error-art">
      <div>
        <p class="hms-kicker">
          Caregrid
        </p>
        <h2>Clinical work, kept on a clear path.</h2>
        <p>When a page cannot be opened, the hospital map should say so plainly and send you back to care.</p>
      </div>
      <p>Session, access, and service messages use the same Caregrid language as the rest of the system.</p>
    </section>
    <section class="h-error-panel">
      <div class="h-error-card">
        <div
          class="h-error-mark"
          aria-hidden="true"
        >
          <HIcon
            :name="mark"
            :size="28"
          />
        </div>
        <p class="hms-kicker">
          {{ code }}
        </p>
        <h1>{{ heading }}</h1>
        <p>{{ body }}</p>
        <div class="h-error-actions">
          <HButton :to="exit">
            {{ exitLabel }}
          </HButton>
          <HButton
            variant="ghost"
            @click="goBack"
          >
            Go back
          </HButton>
          <slot />
        </div>
      </div>
    </section>
  </div>
</template>
