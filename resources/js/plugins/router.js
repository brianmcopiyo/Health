import { createRouter, createWebHistory } from 'vue-router/auto'
import { setupGuards } from '@/plugins/router-guards'
import { useCookie } from '@/composables/useCookie'
import { resolveHomeRoute } from '@/utils/session'

export const redirects = [
  {
    path: '/',
    name: 'index',
    meta: { public: true },
    redirect: to => {
      const userData = useCookie('userData').value
      if (userData)
        return { ...resolveHomeRoute(userData), query: to.query }

      return { name: 'login', query: to.query }
    },
  },
]

export const errorRoutes = [
  {
    path: '/errors/:code',
    name: 'errors-code',
    component: () => import('@/pages/errors/[code].vue'),
    meta: { layout: 'blank', public: true },
  },
]

export const accountRoutes = [
  {
    path: '/account/profile',
    name: 'account-profile',
    component: () => import('@/pages/account/profile.vue'),
  },
  {
    path: '/account/security',
    name: 'account-security',
    component: () => import('@/pages/account/security.vue'),
  },
]

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  extendRoutes: pages => {
    const reserved = new Set(['errors-code', 'account-profile', 'account-security'])
    const rest = pages.filter(page => !reserved.has(page.name))
    return [...redirects, ...errorRoutes, ...accountRoutes, ...rest]
  },
})

setupGuards(router)

export default function (app) {
  app.use(router)
}
