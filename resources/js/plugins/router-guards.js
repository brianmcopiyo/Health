import { canNavigate } from '@/composables/useAbility'
import { useCookie } from '@/composables/useCookie'
import { resolveHomeRoute } from '@/utils/session'

export const setupGuards = router => {
  router.beforeEach(to => {
    if (to.meta.public || to.name === 'index')
      return

    const userData = useCookie('userData')
    const isLoggedIn = !!(userData.value && useCookie('accessToken').value)

    if (to.meta.unauthenticatedOnly) {
      if (isLoggedIn)
        return resolveHomeRoute(userData.value)

      return undefined
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
  })
}
