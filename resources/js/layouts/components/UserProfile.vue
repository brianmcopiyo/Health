<script setup>
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { applySession, clearSession } from '@/utils/session'

const router = useRouter()
const ability = useAbility()
const userData = useCookie('userData')

const memberships = computed(() => userData.value?.memberships || [])
const canSwitch = computed(() => memberships.value.length > 1)

const switchHospital = async hospitalId => {
  if (hospitalId === userData.value?.hospitalId)
    return

  const payload = await $api('/auth/switch-hospital', {
    method: 'POST',
    body: { hospital_id: hospitalId },
  })

  applySession(payload, ability)
  await router.replace({ name: payload.userData.homeRoute || 'reception' })
}

const logout = async () => {
  try {
    await $api('/auth/logout', { method: 'POST' })
  } catch (error) {
  }

  clearSession(ability)
  await router.push('/login')
}
</script>

<template>
  <VBadge
    v-if="userData"
    dot
    bordered
    location="bottom right"
    offset-x="1"
    offset-y="2"
    color="success"
  >
    <VAvatar
      size="38"
      class="cursor-pointer"
      color="primary"
      variant="tonal"
    >
      <VIcon icon="tabler-user" />

      <VMenu
        activator="parent"
        width="280"
        location="bottom end"
        offset="12px"
      >
        <VList>
          <VListItem>
            <VListItemTitle class="font-weight-medium">
              {{ userData.fullName }}
            </VListItemTitle>
            <VListItemSubtitle>
              {{ userData.roleName }}
            </VListItemSubtitle>
            <VListItemSubtitle v-if="userData.hospitalName">
              {{ userData.hospitalName }}
            </VListItemSubtitle>
          </VListItem>

          <VDivider v-if="canSwitch" />

          <VListSubheader v-if="canSwitch">
            Switch hospital
          </VListSubheader>

          <template v-if="canSwitch">
            <VListItem
              v-for="membership in memberships"
              :key="membership.hospitalId"
              :active="membership.hospitalId === userData.hospitalId"
              @click="switchHospital(membership.hospitalId)"
            >
              <VListItemTitle>{{ membership.hospitalName }}</VListItemTitle>
              <VListItemSubtitle>{{ membership.roleName }}</VListItemSubtitle>
            </VListItem>
          </template>

          <PerfectScrollbar :options="{ wheelPropagation: false }">
            <div class="px-4 py-2">
              <VBtn
                block
                size="small"
                color="error"
                append-icon="tabler-logout"
                @click="logout"
              >
                Logout
              </VBtn>
            </div>
          </PerfectScrollbar>
        </VList>
      </VMenu>
    </VAvatar>
  </VBadge>
</template>
