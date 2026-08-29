<script setup>
import { applySession, clearSession, resolveHomeRoute } from '@/utils/session'
import { buildNavigation, pageMeta } from '@/navigation/modules'
import { asList, pageLoadError, pageLoading } from '@/composables/usePageLoad'
import { useCookie } from '@/composables/useCookie'
import { useProfilePhoto } from '@/composables/useProfilePhoto'

const route = useRoute()
const router = useRouter()
const ability = useAbility()
const userData = useCookie('userData')
const accessToken = useCookie('accessToken')
const collapsed = useCookie('hmsSidebarCollapsed', { default: () => false })
const { photoUrl } = useProfilePhoto()
const navOpen = ref(false)
const menuOpen = ref(false)
const noticeOpen = ref(false)
const contextOpen = ref(false)
const overlayNav = ref(false)
const workspace = ref(null)
const toolsRef = ref(null)
const contextRef = ref(null)

const navGroups = computed(() => buildNavigation(ability, userData.value))
const meta = computed(() => pageMeta(route.name))
const memberships = computed(() => userData.value?.memberships || [])
const canSwitchHospital = computed(() => memberships.value.length > 1)
const prefs = computed(() => userData.value?.preferences || {})
const notices = computed(() => {
  const data = workspace.value || {}
  const items = []
  const referrals = asList(data.referrals)
  const labs = asList(data.lab_orders)
  const rx = asList(data.prescriptions)
  const encounters = asList(data.my_encounters)
  const invoices = asList(data.invoices)
  if (referrals.length && prefs.value.referrals !== false)
    items.push({ key: 'referrals', icon: 'transfer', title: `${referrals.length} pending referral${referrals.length === 1 ? '' : 's'}`, to: 'referrals' })
  if (encounters.length && prefs.value.encounters !== false)
    items.push({ key: 'encounters', icon: 'stethoscope', title: `${encounters.length} open encounter${encounters.length === 1 ? '' : 's'} assigned to you`, to: userData.value?.homeRoute || 'reception' })
  if (labs.length && prefs.value.laboratory !== false)
    items.push({ key: 'laboratory', icon: 'flask', title: `${labs.length} laboratory order${labs.length === 1 ? '' : 's'} in queue`, to: 'laboratory' })
  if (rx.length && prefs.value.pharmacy !== false)
    items.push({ key: 'pharmacy', icon: 'pill', title: `${rx.length} prescription${rx.length === 1 ? '' : 's'} awaiting pharmacy`, to: 'pharmacy' })
  if (invoices.length && prefs.value.invoices !== false)
    items.push({ key: 'invoices', icon: 'receipt', title: `${invoices.length} invoice${invoices.length === 1 ? '' : 's'} awaiting payment`, to: 'billing' })
  return items
})

const availabilityOptions = [
  { title: 'Available', value: 'available' },
  { title: 'Busy', value: 'busy' },
  { title: 'Away', value: 'away' },
]

const isActive = to => {
  const name = String(route.name || '')
  if (name === to)
    return true
  if (to === 'admin')
    return false
  return name.startsWith(`${to}-`)
}

const syncOverlay = () => {
  overlayNav.value = window.matchMedia('(max-width: 1100px)').matches
}

const closePopovers = () => {
  menuOpen.value = false
  noticeOpen.value = false
  contextOpen.value = false
}

const onDocumentClick = event => {
  const insideTools = toolsRef.value?.contains(event.target)
  const insideContext = contextRef.value?.contains(event.target)
  if (!insideTools && !insideContext)
    closePopovers()
}

watch(() => route.fullPath, () => {
  navOpen.value = false
  closePopovers()
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
  closePopovers()
  await loadWorkspace()
  await router.replace(resolveHomeRoute(payload.userData))
}

const setAvailability = async value => {
  if (value === userData.value?.availability)
    return
  const payload = await $api('/auth/profile', {
    method: 'PUT',
    body: { availability: value },
  })
  applySession(payload, ability)
}

const logout = async () => {
  try {
    await $api('/auth/logout', { method: 'POST' })
  }
  catch {}
  clearSession(ability)
  await router.push({ name: 'login' })
}

const loadWorkspace = async () => {
  try {
    workspace.value = await $api('/workspace')
  }
  catch {
    workspace.value = null
  }
}

const goNotice = item => {
  noticeOpen.value = false
  if (item.to)
    router.push({ name: item.to })
}

const media = typeof window !== 'undefined' ? window.matchMedia('(max-width: 1100px)') : null
const onMedia = event => { overlayNav.value = event.matches }

onMounted(() => {
  syncOverlay()
  document.addEventListener('click', onDocumentClick)
  if (media?.addEventListener)
    media.addEventListener('change', onMedia)
  else
    media?.addListener(onMedia)
  if (userData.value && accessToken.value) {
    $api('/auth/me').then(payload => applySession(payload, ability)).catch(() => {})
    loadWorkspace()
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
  if (media?.removeEventListener)
    media.removeEventListener('change', onMedia)
  else
    media?.removeListener(onMedia)
})
</script>

<template>
  <div
    class="hms-shell"
    :class="{ 'is-collapsed': collapsed && !overlayNav, 'is-nav-open': navOpen }"
  >
    <aside
      class="hms-sidebar"
      :class="{ 'is-open': navOpen }"
    >
      <div class="hms-brand">
        <div
          class="hms-brand-mark"
          aria-hidden="true"
        >
          <HIcon
            name="cross"
            :size="22"
          />
        </div>
        <div class="hms-brand-copy">
          <p class="hms-brand-product">
            Caregrid
          </p>
          <p class="hms-brand-tenant">
            {{ userData?.hospitalName || 'Network operations' }}
          </p>
        </div>
      </div>

      <nav class="hms-nav">
        <section
          v-for="group in navGroups"
          :key="group.section || group.heading"
          class="hms-nav-group"
          :class="[`is-${group.section || 'item'}`, { 'is-untitled': !group.heading }]"
        >
          <p
            v-if="group.heading"
            class="hms-nav-label"
          >
            {{ group.heading }}
          </p>
          <RouterLink
            v-for="item in group.items"
            :key="item.to"
            :to="{ name: item.to }"
            class="hms-nav-link"
            :class="{ 'is-active': isActive(item.to) }"
            :title="item.title"
          >
            <span class="hms-nav-icon">
              <HIcon
                :name="item.icon"
                :size="18"
              />
            </span>
            <span class="hms-nav-text">{{ item.title }}</span>
          </RouterLink>
        </section>
      </nav>

      <div class="hms-rail-foot">
        <RouterLink
          class="hms-rail-user"
          :title="userData?.fullName"
          :to="{ name: 'account-profile' }"
        >
          <HAvatar
            :src="photoUrl"
            :name="userData?.fullName"
            :size="32"
            :status="userData?.availability"
            rail
          />
          <div class="hms-rail-user-copy">
            <strong>{{ userData?.fullName }}</strong>
            <small>{{ userData?.roleName }}</small>
          </div>
        </RouterLink>
        <button
          class="hms-collapse"
          type="button"
          :aria-label="collapsed ? 'Expand navigation' : 'Collapse navigation'"
          @click="collapsed = !collapsed"
        >
          <HIcon
            name="panel"
            :size="18"
          />
        </button>
      </div>
    </aside>

    <div
      class="h-sidebar-scrim"
      :class="{ 'is-on': navOpen }"
      @click="navOpen = false"
    />

    <div class="hms-main">
      <header class="hms-header">
        <div class="hms-header-inner">
          <div class="h-header-cluster">
            <HButton
              class="h-menu-btn"
              variant="ghost"
              size="icon"
              @click="navOpen = !navOpen"
            >
              <HIcon name="menu" />
            </HButton>
            <div
              ref="contextRef"
              class="hms-context-wrap"
            >
              <button
                v-if="canSwitchHospital"
                class="hms-context is-switch"
                type="button"
                @click.stop="contextOpen = !contextOpen; menuOpen = false; noticeOpen = false"
              >
                <span>{{ userData?.hospitalName || 'Network operations' }}</span>
                <HIcon
                  name="chevron"
                  :size="14"
                />
              </button>
              <p
                v-else
                class="hms-context"
              >
                {{ userData?.hospitalName || 'Network operations' }}
              </p>
              <div
                v-if="contextOpen"
                class="hms-menu is-context"
              >
                <p class="hms-menu-label">
                  Hospital
                </p>
                <button
                  v-for="membership in memberships"
                  :key="membership.hospitalId"
                  type="button"
                  :class="{ 'is-on': membership.hospitalId === userData?.hospitalId }"
                  @click="switchHospital(membership.hospitalId)"
                >
                  <span>
                    <strong>{{ membership.hospitalName }}</strong>
                    <small>{{ membership.roleName }}</small>
                  </span>
                </button>
              </div>
            </div>
            <nav
              class="hms-crumb"
              aria-label="Breadcrumb"
            >
              <span
                v-if="meta.heading && meta.heading !== meta.title"
                class="is-context"
              >{{ meta.heading }}</span>
              <span
                v-if="meta.heading && meta.heading !== meta.title"
                class="hms-crumb-sep is-context"
                aria-hidden="true"
              >/</span>
              <strong>{{ meta.title }}</strong>
            </nav>
          </div>

          <div
            ref="toolsRef"
            class="hms-header-tools"
            @click.stop
          >
            <div class="hms-notice">
              <button
                class="hms-icon-btn"
                type="button"
                aria-label="Notifications"
                @click="noticeOpen = !noticeOpen; menuOpen = false; contextOpen = false"
              >
                <HIcon
                  name="bell"
                  :size="18"
                />
                <em
                  v-if="notices.length"
                  class="hms-notice-count"
                >{{ notices.length }}</em>
              </button>
              <div
                v-if="noticeOpen"
                class="hms-menu is-notices"
              >
                <p class="hms-menu-label">
                  Attention
                </p>
                <button
                  v-for="item in notices"
                  :key="item.key"
                  type="button"
                  class="hms-menu-item"
                  @click="goNotice(item)"
                >
                  <HIcon
                    :name="item.icon"
                    :size="16"
                  />
                  <span>{{ item.title }}</span>
                </button>
                <p
                  v-if="!notices.length"
                  class="h-muted"
                >
                  Nothing needs attention right now.
                </p>
                <div class="hms-menu-sep" />
                <RouterLink
                  class="hms-menu-item"
                  :to="{ name: 'account-profile' }"
                >
                  <HIcon
                    name="settings"
                    :size="16"
                  />
                  <span>Notification preferences</span>
                </RouterLink>
              </div>
            </div>

            <div class="hms-user">
              <button
                class="hms-user-trigger"
                type="button"
                @click="menuOpen = !menuOpen; noticeOpen = false; contextOpen = false"
              >
                <span class="hms-user-copy">
                  <strong>{{ userData?.fullName }}</strong>
                  <small>{{ userData?.roleName }}</small>
                </span>
                <HAvatar
                  :src="photoUrl"
                  :name="userData?.fullName"
                  :size="34"
                  :status="userData?.availability"
                />
              </button>
              <div
                v-if="menuOpen"
                class="hms-menu is-account"
              >
                <div class="hms-menu-identity">
                  <HAvatar
                    :src="photoUrl"
                    :name="userData?.fullName"
                    :size="44"
                    :status="userData?.availability"
                  />
                  <div>
                    <strong>{{ userData?.fullName }}</strong>
                    <small>{{ userData?.jobTitle || userData?.roleName }}</small>
                    <small>{{ [userData?.departmentName, userData?.hospitalName || 'Network operations'].filter(Boolean).join(' · ') }}</small>
                  </div>
                </div>
                <div class="hms-avail">
                  <button
                    v-for="option in availabilityOptions"
                    :key="option.value"
                    type="button"
                    :class="{ 'is-on': (userData?.availability || 'available') === option.value }"
                    @click="setAvailability(option.value)"
                  >
                    {{ option.title }}
                  </button>
                </div>
                <div class="hms-menu-sep" />
                <RouterLink
                  class="hms-menu-item"
                  :to="{ name: 'account-profile' }"
                >
                  <HIcon
                    name="user"
                    :size="16"
                  />
                  <span>Profile</span>
                </RouterLink>
                <RouterLink
                  class="hms-menu-item"
                  :to="{ name: 'account-security' }"
                >
                  <HIcon
                    name="lock"
                    :size="16"
                  />
                  <span>Account & security</span>
                </RouterLink>
                <RouterLink
                  class="hms-menu-item"
                  :to="{ name: 'account-security', query: { action: 'password' } }"
                >
                  <HIcon
                    name="shield"
                    :size="16"
                  />
                  <span>Change password</span>
                </RouterLink>
                <template v-if="canSwitchHospital">
                  <div class="hms-menu-sep" />
                  <p class="hms-menu-label">
                    Switch hospital
                  </p>
                  <button
                    v-for="membership in memberships"
                    :key="membership.hospitalId"
                    type="button"
                    :class="{ 'is-on': membership.hospitalId === userData?.hospitalId }"
                    @click="switchHospital(membership.hospitalId)"
                  >
                    {{ membership.hospitalName }} · {{ membership.roleName }}
                  </button>
                </template>
                <div class="hms-menu-sep" />
                <button
                  type="button"
                  class="hms-menu-item is-danger"
                  @click="logout"
                >
                  <HIcon
                    name="logout"
                    :size="16"
                  />
                  <span>Sign out</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </header>

      <main
        class="hms-content"
        @click="closePopovers"
      >
        <div class="hms-page">
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
        </div>
      </main>
    </div>
  </div>
</template>
