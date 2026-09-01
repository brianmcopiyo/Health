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

const fillNamedPath = (recordPath, params = {}) => {
  if (!recordPath)
    return null

  const filled = String(recordPath).replace(/:([^/?]+)\??/g, (_, key) => {
    const value = params[key]
    return value == null || value === '' ? '' : encodeURIComponent(String(value))
  }).replace(/\/{2,}/g, '/').replace(/\/$/, '')

  return filled || '/'
}

export const router = createRouter({
  history: createWebHistory('/'),
  extendRoutes: pages => {
    const reserved = new Set(['account-profile', 'account-security'])
    const rest = flattenRoutes(pages).filter(page => !reserved.has(page.name))
    const fallback = rest.filter(page => page.name === '$error')
    const pagesOnly = rest.filter(page => page.name !== '$error')
    return [...redirects, ...accountRoutes, ...pagesOnly, ...fallback]
  },
})

const resolveLocation = router.resolve.bind(router)
router.resolve = (to, current) => {
  try {
    return resolveLocation(to, current)
  } catch (error) {
    if (!to || typeof to !== 'object' || !to.name)
      throw error
    const record = router.getRoutes().find(route => route.name === to.name)
    const path = fillNamedPath(record?.path, to.params)
    if (path)
      return resolveLocation({ path, query: to.query, hash: to.hash }, current)
    throw error
  }
}

setupGuards(router)

export default function (app) {
  app.use(router)
}
