export const redirects = [
  {
    path: '/',
    name: 'index',
    redirect: to => {
      const userData = useCookie('userData')
      if (userData.value)
        return { name: userData.value.homeRoute || 'reception', query: to.query }

      return { name: 'login', query: to.query }
    },
  },
]

export const routes = []
