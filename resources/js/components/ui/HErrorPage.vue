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
const home = computed(() => resolveHomeRoute(userData.value))
const signedIn = computed(() => Boolean(userData.value))
const needsLogin = computed(() => meta.value.action === 'login' || !signedIn.value || home.value.name === 'not-authorized')
const exit = computed(() => {
  if (needsLogin.value)
    return { name: 'login', query: Number(props.code) === 401 || Number(props.code) === 419 ? { reason: 'expired' } : undefined }

  return home.value
})
const exitLabel = computed(() => meta.value.next || (needsLogin.value ? 'Sign in to Caregrid' : 'Open Caregrid'))
const canRefresh = computed(() => ![401, 403, 404, 410].includes(Number(props.code)))

const goBack = () => {
  if (window.history.length > 1) {
    router.back()
    return
  }

  router.replace(exit.value)
}

const refresh = () => {
  window.location.reload()
}
</script>

<template>
  <HAuthStage
    kind="error"
    :scene="code"
    :tone="meta.tone"
    brand="Caregrid"
    :headline="meta.artTitle"
    :lead="meta.artCopy"
  >
    <div class="h-error-copy">
      <p class="hms-kicker">
        {{ meta.label }} · {{ code }}
      </p>
      <h1>{{ heading }}</h1>
      <p>{{ body }}</p>
      <p
        v-if="meta.hint"
        class="h-error-hint"
      >
        {{ meta.hint }}
      </p>
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
        <HButton
          v-if="canRefresh"
          variant="ghost"
          @click="refresh"
        >
          Try again
        </HButton>
        <slot />
      </div>
    </div>
  </HAuthStage>
</template>
