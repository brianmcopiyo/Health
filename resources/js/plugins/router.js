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

const joinRoutePath = (base, child) => {
  if (child == null || child === '')
    return base || '/'
  if (String(child).startsWith('/'))
    return child
  const left = String(base || '').replace(/\/$/, '')
  return `${left}/${child}`.replace(/\/{2,}/g, '/')
}

const flattenRoutes = (routes, prefix = '') => {
  const out = []
  for (const route of routes || []) {
    const path = joinRoutePath(prefix, route.path)
    const children = route.children || []
    if (!children.length) {
      out.push({ ...route, path })
      continue
    }

    // App.vue is the only RouterView. Hoist list+detail files such as
    // admin/users.vue + admin/users/[id].vue into sibling routes.
    if (route.component || route.redirect) {
      const { children: _nested, ...page } = route
      out.push({ ...page, path })
    }

    out.push(...flattenRoutes(children, path))
  }
  return out
}

const namedLocationPath = to => {
  if (!to || typeof to !== 'object' || !to.name)
    return null
  const name = String(to.name)
  if (name.endsWith('-id') && to.params?.id)
    return `/${name.slice(0, -3).replaceAll('-', '/')}/${to.params.id}`
  if (!name.includes('-') && !to.params)
    return `/${name}`
  return null
}

export const router = createRouter({
  history: createWebHistory('/'),
  extendRoutes: pages => {
    const reserved = new Set(['account-profile', 'account-security'])
    const rest = flattenRoutes(pages).filter(page => !reserved.has(page.name))
    return [...redirects, ...accountRoutes, ...rest]
  },
})

const resolveLocation = router.resolve.bind(router)
router.resolve = (to, current) => {
  try {
    return resolveLocation(to, current)
  } catch (error) {
    const path = namedLocationPath(to)
    if (path)
      return resolveLocation({ path, query: to.query, hash: to.hash }, current)
    throw error
  }
}

setupGuards(router)

export default function (app) {
  app.use(router)
}
