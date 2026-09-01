export const errorCatalog = {
  401: {
    title: 'Your session has ended',
    copy: 'Sign in again to continue clinical work in Caregrid.',
    icon: 'lock',
    action: 'login',
  },
  403: {
    title: 'This workspace is closed to you',
    copy: 'Your role does not include this module. Ask a hospital administrator if you need access.',
    icon: 'shield',
  },
  404: {
    title: 'This page is not on the hospital map',
    copy: 'The address may have changed, or the record is no longer in the live register.',
    icon: 'search',
  },
  408: {
    title: 'The request timed out',
    copy: 'Caregrid took too long to respond. Try again in a moment.',
    icon: 'clock',
  },
  410: {
    title: 'This record is no longer available',
    copy: 'It may have been archived, transferred, or removed from the live register.',
    icon: 'x',
  },
  422: {
    title: 'This request could not be processed',
    copy: 'Some of the information sent was not accepted. Go back and try again.',
    icon: 'x',
  },
  419: {
    title: 'This page has expired',
    copy: 'Refresh and sign in again so your session stays secure.',
    icon: 'clock',
    action: 'login',
  },
  429: {
    title: 'Too many requests',
    copy: 'Caregrid paused this action to protect the hospital service. Wait a moment, then retry.',
    icon: 'clock',
  },
  500: {
    title: 'Something went wrong on our side',
    copy: 'The clinical service hit an unexpected error. Try again shortly.',
    icon: 'server',
  },
  502: {
    title: 'The gateway could not be reached',
    copy: 'A connecting service did not respond. Refresh to try the hospital network again.',
    icon: 'refresh',
  },
  503: {
    title: 'Caregrid is temporarily unavailable',
    copy: 'Maintenance or high load is in progress. Clinical teams can retry in a few minutes.',
    icon: 'hospital',
  },
  504: {
    title: 'The hospital network timed out',
    copy: 'A connected service did not finish in time. Refresh to continue.',
    icon: 'clock',
  },
}

export const httpStatus = error => Number(error?.status || error?.response?.status || 0)

export const resolveError = code => {
  const key = Number(code)
  return errorCatalog[key] || errorCatalog[404]
}

export const pageErrorCode = status => {
  const code = Number(status)
  if (!code || code === 401)
    return null
  if (errorCatalog[code])
    return code
  if (code >= 500)
    return 500

  return null
}
