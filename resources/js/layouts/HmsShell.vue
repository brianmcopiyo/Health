<script setup>
import { applySession, clearSession, resolveHomeRoute } from '@/utils/session'
import { buildNavigation, pageMeta } from '@/navigation/modules'
import { pageLoadError, pageLoading } from '@/composables/usePageLoad'

const route = useRoute()
const router = useRouter()
const ability = useAbility()
const userData = useCookie('userData')
const navOpen = ref(false)
const menuOpen = ref(false)

const navItems = computed(() => buildNavigation(ability, userData.value))
const meta = computed(() => pageMeta(route.name))
const initials = computed(() => {
  const name = userData.value?.fullName || 'U'
  return name.split(' ').map(part => part[0]).slice(0, 2).join('').toUpperCase()
})
const memberships = computed(() => userData.value?.memberships || [])

watch(() => route.fullPath, () => {
  navOpen.value = false
  menuOpen.value = false
  pageLoadError.value = null
})

const switchHospital = async hospitalId => {
  if (hospitalId === userData.value?.hospitalId)
    return
  const payload = await $api('/auth/switch-hospital', {
    method: 'POST',
    body: { hospital_id: hospitalId },
  })
  applySession(payload, ability)
  await router.replace(resolveHomeRoute(payload.userData))
}

const logout = async () => {
  try {
    await $api('/auth/logout', { method: 'POST' })
  }
  catch {}
  clearSession(ability)
  await router.push({ name: 'login' })
}
</script>

<template>
  <div class="hms-shell">
    <aside
      class="hms-sidebar"
      :class="{ 'is-open': navOpen }"
    >
      <div class="hms-brand">
        <div class="hms-brand-mark">
          <HIcon
            name="cross"
            :size="20"
          />
        </div>
        <div>
          <h1>Caregrid</h1>
          <p>Hospital operations</p>
        </div>
      </div>

      <nav>
        <template
          v-for="(item, index) in navItems"
          :key="item.to || item.heading || index"
        >
          <div
            v-if="item.heading"
            class="hms-nav-label"
          >
            {{ item.heading }}
          </div>
          <RouterLink
            v-else
            :to="{ name: item.to }"
            class="hms-nav-link"
            :class="{ 'is-active': route.name === item.to }"
          >
            <HIcon :name="item.icon" />
            <span>{{ item.title }}</span>
          </RouterLink>
        </template>
      </nav>
    </aside>
    <div
      class="h-sidebar-scrim"
      :class="{ 'is-on': navOpen }"
      @click="navOpen = false"
    />

    <div class="hms-main">
      <header class="hms-header">
        <div style="display:flex;align-items:center;gap:10px;min-width:0">
          <HButton
            class="h-menu-btn"
            variant="ghost"
            size="icon"
            @click="navOpen = !navOpen"
          >
            <HIcon name="menu" />
          </HButton>
          <div class="hms-header-meta">
            <p class="hms-kicker">
              {{ userData?.hospitalName || 'Network' }}
            </p>
            <h2>{{ meta.title }}</h2>
          </div>
        </div>

        <div class="hms-user">
          <button
            class="ghost"
            type="button"
            @click="menuOpen = !menuOpen"
          >
            <strong>{{ userData?.fullName }}</strong>
            <small>{{ userData?.roleName }}</small>
          </button>
          <div class="hms-avatar">
            {{ initials }}
          </div>
          <div
            v-if="menuOpen"
            class="hms-menu"
          >
            <div style="padding:8px 10px;color:var(--muted);font-size:0.85rem">
              {{ userData?.email }}
            </div>
            <template v-if="memberships.length > 1">
              <div style="padding:8px 10px;font-size:0.75rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)">
                Switch hospital
              </div>
              <button
                v-for="membership in memberships"
                :key="membership.hospitalId"
                type="button"
                @click="switchHospital(membership.hospitalId)"
              >
                {{ membership.hospitalName }} · {{ membership.roleName }}
              </button>
            </template>
            <button
              type="button"
              @click="logout"
            >
              Sign out
            </button>
          </div>
        </div>
      </header>

      <main class="hms-content">
        <div
          v-if="pageLoading"
          class="h-progress"
        />
        <div
          v-if="pageLoadError"
          class="h-alert"
        >
          {{ pageLoadError }}
        </div>
        <slot />
      </main>
    </div>
  </div>
</template>
