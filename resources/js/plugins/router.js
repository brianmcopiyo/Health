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

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  extendRoutes: pages => [...redirects, ...pages],
})

setupGuards(router)

export default function (app) {
  app.use(router)
}
