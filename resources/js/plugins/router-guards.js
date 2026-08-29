import { nextTick } from 'vue'
import { isNavigationFailure, NavigationFailureType } from 'vue-router'
import { canNavigate } from '@/composables/useAbility'
import { useCookie } from '@/composables/useCookie'
import {
  currentPageNav,
  finishPageNav,
  forceFinishPageNav,
  isNewPage,
  startPageNav,
} from '@/composables/useRouteLoad'
import { resolveHomeRoute } from '@/utils/session'

export const setupGuards = router => {
  let hops = 0

  router.beforeEach((to, from) => {
    hops += 1
    if (hops > 8) {
      hops = 0
      forceFinishPageNav()
      return false
    }

    if (to.name === 'index')
      return

    const begin = () => {
      if (isNewPage(to, from))
        startPageNav()
    }

    if (to.meta.public) {
      begin()
      return
    }

    const userData = useCookie('userData')
    const isLoggedIn = !!(userData.value && useCookie('accessToken').value)

    if (to.meta.unauthenticatedOnly) {
      if (isLoggedIn)
        return resolveHomeRoute(userData.value)

      begin()
      return
    }

    if (!isLoggedIn) {
      const next = to.fullPath !== '/' && to.name !== 'not-authorized' ? to.path : undefined

      return {
        name: 'login',
        query: { ...to.query, to: next },
      }
    }

    if (!canNavigate(to)) {
      const home = resolveHomeRoute(userData.value)
      if (home.name && home.name !== to.name && home.name !== 'not-authorized')
        return home

      return { name: 'not-authorized' }
    }

    begin()
  })

  router.afterEach((to, from, failure) => {
    if (!isNavigationFailure(failure, NavigationFailureType.redirected))
      hops = 0

    if (
      isNavigationFailure(failure, NavigationFailureType.redirected)
      || isNavigationFailure(failure, NavigationFailureType.cancelled)
    )
      return

    if (failure) {
      forceFinishPageNav()
      return
    }

    if (!isNewPage(to, from))
      return

    const id = currentPageNav()
    nextTick(() => finishPageNav(id))
  })

  router.onError(() => {
    forceFinishPageNav()
  })
}
